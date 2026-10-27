<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * F9 — مسار ما بعد التحليل: زرع المحتوى (8 خطوات · 61 بند · 25 سكريبت) من data/checklist_seed.json.
 * الزرع بيضيف الناقص بس (بالـ key) — أي تعديل من الإعدادات مبيتمسحش. لو الملف اتغيّر بيتقرا تاني.
 */

if (!defined('DV_PATH_EXPECTED')) {
    define('DV_PATH_EXPECTED', serialize(['steps' => 8, 'items' => 61, 'scripts' => 25]));
}

/** بنود التعليم الأوتوماتيك (PRD F9 §5): item_key ← القاعدة. بتتطبق لو الـ JSON مافيهوش auto_rule */
if (!function_exists('dv_path_auto_rules')) {
    function dv_path_auto_rules()
    {
        return [
            'log_1'    => 'visit:any',
            'log_2'    => 'visit:any',
            'log_3'    => 'visit:next_action',
            'visit_3'  => 'meta:gatekeeper_name',
            'visit_4'  => 'meta:best_time',
            'log_4'    => 'meta:gatekeeper_best_time',
            'visit_5'  => 'outcome:ENV',
            'visit_6'  => 'outcome:DOC',
            'visit_7'  => 'consent',
            'log_5'    => 'consent',
            'visit_8'  => 'outcome:MTG_BOOKED',
            'log_6'    => 'outcome:LOST',
            'follow_1' => 'wa_template:after_visit',
            'follow_2' => 'wa_template:bot_demo',
            'follow_4' => 'visit:second_field_5d',
            'follow_6' => 'wa_template:breakup',
            'follow_8' => 'opt_out',
            'close_1'  => 'outcome:MTG_HELD',
            'close_4'  => 'outcome:OFFER',
            'close_9'  => 'proposal',
            'audit_7'  => 'qr',
        ];
    }
}

if (!function_exists('dv_path_seed_file')) {
    function dv_path_seed_file()
    {
        return dirname(__DIR__) . '/data/checklist_seed.json';
    }
}

/** "قبل الزيارة" / before ← before … */
if (!function_exists('dv_path_phase')) {
    function dv_path_phase($v, $fallback = 'before')
    {
        $v = trim(mb_strtolower((string) $v));
        if (in_array($v, ['before', 'during', 'after'], true)) {
            return $v;
        }
        if (mb_strpos($v, 'قبل') !== false) {
            return 'before';
        }
        if (mb_strpos($v, 'بعد') !== false) {
            return 'after';
        }
        if (mb_strpos($v, 'في') !== false || mb_strpos($v, 'اثناء') !== false || mb_strpos($v, 'أثناء') !== false) {
            return 'during';
        }
        return $fallback;
    }
}

/**
 * يقرا الـ JSON ويوحّده — بيقبل:
 *   {"steps":[{key, phase, scope, title, subtitle, tip, items:[…], scripts:[…]}]}
 *   {"phases":[{key|title, steps:[…]}]}   ·   items كنص أو {key, text, auto_rule}
 *   scripts جوه الخطوة أو في {"scripts":[{step, …}]} برا
 * بيرجع ['steps' => [...], 'counts' => [...], 'warnings' => [...]]
 */
if (!function_exists('dv_path_seed_read')) {
    function dv_path_seed_read($path)
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new Exception('ملف المسار مش موجود: ' . $path);
        }
        $j = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $raw), true);
        if (!is_array($j)) {
            throw new Exception('ملف المسار مش JSON سليم: ' . json_last_error_msg());
        }
        $steps = [];
        if (!empty($j['phases'])) {
            foreach ($j['phases'] as $ph) {
                $phase = dv_path_phase($ph['key'] ?? $ph['phase'] ?? $ph['title'] ?? '');
                foreach ($ph['steps'] ?? [] as $s) {
                    $steps[] = $s + ['phase' => $phase];
                }
            }
        }
        foreach ($j['steps'] ?? [] as $k => $s) {
            $steps[] = is_string($k) && empty($s['key']) ? $s + ['key' => $k] : $s;
        }
        $rules = dv_path_auto_rules();
        $out   = [];
        $warn  = [];
        $keys  = ['items' => [], 'scripts' => []];
        $scripts = [];
        foreach (array_values($steps) as $si => $s) {
            $key = trim((string) ($s['key'] ?? $s['id'] ?? ''));
            if ($key === '' || empty($s['title'])) {
                $warn[] = 'خطوة رقم ' . ($si + 1) . ' من غير key أو title — اتخطت';
                continue;
            }
            $step = [
                'key' => mb_substr($key, 0, 30), 'phase' => dv_path_phase($s['phase'] ?? ''),
                'scope' => ($s['scope'] ?? '') === 'rep_daily' || $key === 'kit' ? 'rep_daily' : 'lead',
                'title' => mb_substr((string) $s['title'], 0, 100), 'subtitle' => isset($s['subtitle']) ? mb_substr((string) $s['subtitle'], 0, 150) : null,
                'tip' => $s['tip'] ?? null, 'sort' => (int) ($s['sort'] ?? $si + 1), 'items' => [], 'scripts' => [],
            ];
            foreach (array_values($s['items'] ?? $s['checklist'] ?? []) as $ii => $it) {
                $it   = is_string($it) ? ['text' => $it] : $it;
                $ikey = trim((string) ($it['key'] ?? $it['id'] ?? $key . '_' . ($ii + 1)));
                if (trim((string) ($it['text'] ?? '')) === '') {
                    $warn[] = 'بند فاضي في خطوة ' . $key;
                    continue;
                }
                if (isset($keys['items'][$ikey])) {
                    $warn[] = 'بند متكرر: ' . $ikey;
                    continue;
                }
                $keys['items'][$ikey] = 1;
                $step['items'][] = [
                    'key' => mb_substr($ikey, 0, 30), 'text' => mb_substr(trim($it['text']), 0, 255),
                    'auto_rule' => $it['auto_rule'] ?? ($rules[$ikey] ?? null), 'sort' => (int) ($it['sort'] ?? $ii + 1),
                ];
            }
            foreach ($s['scripts'] ?? [] as $sc) {
                $scripts[] = $sc + ['step' => $key];
            }
            $out[$key] = $step;
        }
        foreach ($j['scripts'] ?? [] as $sc) {
            $scripts[] = $sc;
        }
        $n = [];
        foreach ($scripts as $sc) {
            $sk = (string) ($sc['step'] ?? $sc['step_key'] ?? '');
            if (!isset($out[$sk]) || trim((string) ($sc['body'] ?? $sc['text'] ?? '')) === '') {
                $warn[] = 'سكريبت من غير خطوة معروفة أو من غير نص: ' . ($sc['key'] ?? '?');
                continue;
            }
            $n[$sk] = ($n[$sk] ?? 0) + 1;
            $ckey   = trim((string) ($sc['key'] ?? $sk . '_s' . $n[$sk]));
            if (isset($keys['scripts'][$ckey])) {
                $warn[] = 'سكريبت متكرر: ' . $ckey;
                continue;
            }
            $keys['scripts'][$ckey] = 1;
            $body = (string) ($sc['body'] ?? $sc['text']);
            if (strpos($body, '___') !== false) {
                $warn[] = 'سكريبت ' . $ckey . ' لسه فيه ___ — لازم تتحوّل لمتغير زي {doctor_name}';
            }
            $out[$sk]['scripts'][] = [
                'key' => mb_substr($ckey, 0, 30), 'audience' => mb_substr((string) ($sc['audience'] ?? ''), 0, 20),
                'situation' => isset($sc['situation']) ? mb_substr((string) $sc['situation'], 0, 150) : null, 'body' => $body,
                'wa_template_key' => $sc['wa_template_key'] ?? null, 'sort' => (int) ($sc['sort'] ?? $n[$sk]),
            ];
        }
        $counts = ['steps' => count($out), 'items' => count($keys['items']), 'scripts' => count($keys['scripts'])];
        foreach (unserialize(DV_PATH_EXPECTED) as $k => $want) {
            if ($counts[$k] !== $want && !($k === 'scripts' && $counts[$k] === 0)) { // السكريبتات اختيارية (مش في صفحة الـ HTML)
                $warn[] = 'العدد مختلف عن الـ PRD: ' . $k . ' = ' . $counts[$k] . ' (المفروض ' . $want . ')';
            }
        }
        foreach (array_diff(array_keys($rules), array_keys($keys['items'])) as $miss) {
            $warn[] = 'بند أوتوماتيك مش موجود في الملف: ' . $miss;
        }
        return ['steps' => array_values($out), 'counts' => $counts, 'warnings' => $warn];
    }
}

/**
 * الزرع: بيضيف الخطوات/البنود/السكريبتات الناقصة بالـ key. مبيعدّلش ولا يمسح الموجود.
 * بيرجع null لو مفيش ملف، أو ['added' => …, 'counts' => …, 'warnings' => …]
 */
if (!function_exists('dv_path_seed')) {
    function dv_path_seed($path = null, $force = false)
    {
        $path = $path ?: dv_path_seed_file();
        if (!is_file($path)) {
            return null;
        }
        $hash = md5_file($path);
        if (!$force && get_option('dv_path_seed_hash') === $hash) {
            return null;
        }
        $CI   = &get_instance();
        $p    = db_prefix();
        $seed = dv_path_seed_read($path);
        $add  = ['steps' => 0, 'items' => 0, 'scripts' => 0];
        $id   = function ($t, $key) use ($CI, $p) {
            $r = $CI->db->query('SELECT id FROM `' . $p . $t . '` WHERE `key` = ?', [$key])->row_array();
            return $r ? (int) $r['id'] : null;
        };
        foreach ($seed['steps'] as $s) {
            $sid = $id('dv_path_steps', $s['key']);
            if (!$sid) {
                $CI->db->insert($p . 'dv_path_steps', ['key' => $s['key'], 'phase' => $s['phase'], 'scope' => $s['scope'], 'title' => $s['title'],
                    'subtitle' => $s['subtitle'], 'tip' => $s['tip'], 'sort' => $s['sort'], 'active' => 1]);
                $sid = (int) $CI->db->insert_id();
                $add['steps']++;
            }
            foreach ($s['items'] as $it) {
                if (!$id('dv_path_items', $it['key'])) {
                    $CI->db->insert($p . 'dv_path_items', ['step_id' => $sid, 'active' => 1] + $it);
                    $add['items']++;
                }
            }
            foreach ($s['scripts'] as $sc) {
                if (!$id('dv_path_scripts', $sc['key'])) {
                    $CI->db->insert($p . 'dv_path_scripts', ['step_id' => $sid, 'active' => 1] + $sc);
                    $add['scripts']++;
                }
            }
        }
        update_option('dv_path_seed_hash', $hash);
        update_option('dv_path_seed_report', json_encode(['added' => $add, 'counts' => $seed['counts'], 'warnings' => $seed['warnings'], 'at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE));
        return ['added' => $add, 'counts' => $seed['counts'], 'warnings' => $seed['warnings']];
    }
}
