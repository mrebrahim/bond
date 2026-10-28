<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Evolution API v2 — الواتساب */
class Dse_evolution
{
    /** @var callable|null للاختبارات: fn($method, $url, $body) => [status, array] */
    public $transport = null;

    private function cfg()
    {
        return [
            'url'      => rtrim((string) get_option('dse_evo_url'), '/'),
            'key'      => (string) get_option('dse_evo_key'),
            'instance' => (string) get_option('dse_evo_instance'),
        ];
    }

    public function configured()
    {
        $c = $this->cfg();
        return $c['url'] !== '' && $c['key'] !== '' && $c['instance'] !== '';
    }

    /** @return array */
    public function request($method, $path, $body = null)
    {
        $c   = $this->cfg();
        $url = $c['url'] . str_replace('{instance}', rawurlencode($c['instance']), $path);
        if ($this->transport) {
            list($status, $json) = call_user_func($this->transport, $method, $url, $body);
        } else {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => ['apikey: ' . $c['key'], 'Content-Type: application/json'],
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
            }
            $raw    = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                throw new Exception('مقدرتش أوصل لـ Evolution: ' . $err);
            }
            $json = json_decode($raw, true);
            if (!is_array($json)) {
                $json = ['raw' => mb_substr((string) $raw, 0, 300)];
            }
        }
        if ($status < 200 || $status >= 300) {
            throw new Exception('Evolution ' . $status . ': ' . mb_substr(json_encode($json, JSON_UNESCAPED_UNICODE), 0, 300));
        }
        return $json;
    }

    public function send_text($to, $text)
    {
        return $this->request('POST', '/message/sendText/{instance}', ['number' => $to, 'text' => $text]);
    }

    /** @return array [jid, link] */
    public function create_group($subject, $description, array $participants)
    {
        $g   = $this->request('POST', '/group/create/{instance}', [
            'subject' => mb_substr($subject, 0, 100), 'description' => $description, 'participants' => array_values($participants),
        ]);
        $jid = (string) ($g['id'] ?? ($g['groupJid'] ?? ($g['group']['id'] ?? '')));
        if (substr($jid, -5) !== '@g.us') {
            throw new Exception('الجروب اتعمل بس مقدرتش أقرا الـ id: ' . mb_substr(json_encode($g, JSON_UNESCAPED_UNICODE), 0, 200));
        }
        $link = null;
        try {
            $inv  = $this->request('GET', '/group/inviteCode/{instance}?groupJid=' . rawurlencode($jid));
            $link = $inv['inviteUrl'] ?? (isset($inv['inviteCode']) ? 'https://chat.whatsapp.com/' . $inv['inviteCode'] : null);
        } catch (Exception $e) {
            // اللينك مش ضروري
        }
        return [$jid, $link];
    }

    /**
     * لينك جروب ← JID. بيقبل كمان الـ JID نفسه (1203...@g.us).
     * لو رقم السيستم مش عضو في الجروب (جروبات العملاء القدام اللي اتعملت من موبايل)، بيدخّله الجروب باللينك (acceptInviteCode).
     */
    public function group_from_link($link)
    {
        $link = trim((string) $link);
        if (preg_match('/^\d[\d-]{8,}@g\.us$/', $link)) {
            return [$link, null];
        }
        $parts = explode('/', $link);
        $code  = explode('?', end($parts))[0];
        if (!preg_match('/^[A-Za-z0-9]{10,}$/', $code)) {
            throw new Exception('ده مش لينك جروب واتساب صحيح (https://chat.whatsapp.com/...)');
        }
        $pick = function ($r) {
            foreach ([$r['id'] ?? null, $r['jid'] ?? null, $r['groupJid'] ?? null, $r['data']['id'] ?? null, $r['group']['id'] ?? null] as $v) {
                if (is_string($v) && substr($v, -5) === '@g.us') {
                    return $v;
                }
            }
            return '';
        };
        $jid = '';
        $why = '';
        try {
            $jid = $pick($this->request('GET', '/group/inviteInfo/{instance}?inviteCode=' . $code));
        } catch (Exception $e) {
            $why = $e->getMessage();
        }
        if ($jid === '') { // رقم السيستم مش في الجروب ← يدخل باللينك
            try {
                $jid = $pick($this->request('GET', '/group/acceptInviteCode/{instance}?inviteCode=' . $code));
            } catch (Exception $e) {
                $why = $e->getMessage();
            }
        }
        if ($jid === '') {
            throw new Exception('مقدرتش أوصل للجروب — اتأكد إن اللينك شغال (مش متقفل أو اتغيّر) وإن رقم السيستم مش محظور فيه، أو ضيف رقم السيستم للجروب بإيدك واربط تاني' . ($why ? ' (' . $why . ')' : ''));
        }
        return [$jid, 'https://chat.whatsapp.com/' . $code];
    }

    /** كل الجروبات اللي رقم السيستم عضو فيها: [[id, subject, size], …] */
    public function fetch_groups()
    {
        $r    = $this->request('GET', '/group/fetchAllGroups/{instance}?getParticipants=false');
        $list = isset($r[0]) || $r === [] ? $r : ($r['groups'] ?? ($r['data'] ?? []));
        $out  = [];
        foreach ((array) $list as $g) {
            $id = (string) ($g['id'] ?? ($g['jid'] ?? ''));
            if (substr($id, -5) === '@g.us') {
                $out[] = ['id' => $id, 'subject' => (string) ($g['subject'] ?? $id), 'size' => (int) ($g['size'] ?? 0)];
            }
        }
        usort($out, function ($a, $b) { return strcmp($a['subject'], $b['subject']); });
        return $out;
    }

    public function add_members($jid, array $numbers)
    {
        return $this->request('POST', '/group/updateParticipant/{instance}?groupJid=' . rawurlencode($jid), [
            'action' => 'add', 'participants' => array_values($numbers),
        ]);
    }

    public function state()
    {
        $s = $this->request('GET', '/instance/connectionState/{instance}');
        return $s['instance']['state'] ?? ($s['state'] ?? 'unknown');
    }
}
