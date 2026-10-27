<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * API لـ n8n (PRD §7.3) — مفتوح من غير login، والحماية بالـ Header X-DV-Token + حد 60 طلب في الدقيقة.
 *   POST /doctor_visits/dv_api/qr_event        {code, phone, message, received_at}
 *   POST /doctor_visits/dv_api/visit           {lead_id, staff_id, outcome, note, next_action, next_action_at, lost_reason}
 *   GET  /doctor_visits/dv_api/queue?staff_id=
 *   GET  /doctor_visits/dv_api/followups_due[?staff_id=]
 *   POST /doctor_visits/dv_api/opt_out         {phone}
 * الـ CSRF مقفول للمسارات دي من config/csrf_exclude_uris.php
 */
class Dv_api extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        dv_ensure_schema();
        $this->load->model(DOCTOR_VISITS_MODULE_NAME . '/dv_model');
        $this->auth();
    }

    private function out($data, $status = 200)
    {
        $this->output->set_status_header($status)->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE))->_display();
        exit;
    }

    private function auth()
    {
        $token = (string) $this->input->get_request_header('X-DV-Token', true);
        $real  = (string) dv_setting('api_token');
        if ($real === '' || !hash_equals($real, $token)) {
            $this->out(['error' => 'unauthorized'], 401);
        }
        // Rate limit بسيط: عدّاد للدقيقة الحالية
        $minute = date('YmdHi');
        $cur    = explode('|', (string) get_option('dv_api_rl'));
        $count  = ($cur[0] ?? '') === $minute ? (int) ($cur[1] ?? 0) + 1 : 1;
        update_option('dv_api_rl', $minute . '|' . $count);
        if ($count > (int) dv_setting('api_rate_limit', 0, 60)) {
            $this->out(['error' => 'rate_limited'], 429);
        }
    }

    private function body()
    {
        $json = json_decode((string) $this->input->raw_input_stream, true);
        return is_array($json) ? $json : ($this->input->post() ?: []);
    }

    private function card(array $d)
    {
        return [
            'lead_id' => (int) $d['lead_id'], 'name' => $d['name'], 'specialty' => $d['specialty'], 'building' => $d['building'],
            'floor' => $d['floor'], 'stage' => (int) $d['stage'], 'stage_name' => dv_stage_label($d['stage']), 'progress' => $d['progress'],
            'gatekeeper' => $d['gatekeeper_name'], 'best_time' => $d['best_time'], 'next_action' => $d['next_action'],
            'next_action_at' => $d['next_action_at'], 'next_step' => $d['next_step'], 'why' => $d['why'] ?? [],
            'link' => admin_url('doctor_visits/doctor/' . $d['lead_id']),
        ];
    }

    public function qr_event()
    {
        $b = $this->body();
        try {
            $this->out(['ok' => true] + $this->dv_model->qr_event($b['code'] ?? '', $b['phone'] ?? null, $b['message'] ?? null, $b['received_at'] ?? null));
        } catch (Exception $e) {
            $this->out(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function visit()
    {
        $b = $this->body();
        try {
            $staff = (int) ($b['staff_id'] ?? 0);
            if (!$staff) {
                throw new Exception('staff_id required');
            }
            $id = $this->dv_model->log_visit((int) ($b['lead_id'] ?? 0), $b + ['channel' => $b['channel'] ?? 'phone'], $staff);
            $this->out(['ok' => true, 'visit_id' => $id, 'feedback' => $this->dv_model->feedback]);
        } catch (Exception $e) {
            $this->out(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function queue()
    {
        $staff = (int) $this->input->get('staff_id');
        if (!$staff) {
            $this->out(['error' => 'staff_id required'], 422);
        }
        $q = $this->dv_model->queue($staff);
        $q = array_values(array_filter($q, function ($d) use ($staff) { return (int) $d['assigned_staff_id'] === $staff; }));
        $this->out([
            'staff_id' => $staff, 'today_target' => $this->dv_model->daily_target($staff), 'done_today' => $this->dv_model->visits_on($staff, date('Y-m-d')),
            'quests' => $this->dv_model->quests($staff), 'queue' => array_map([$this, 'card'], array_slice($q, 0, 20)),
        ]);
    }

    public function followups_due()
    {
        $staff = (int) $this->input->get('staff_id') ?: null;
        $this->out(['followups' => array_map([$this, 'card'], $this->dv_model->followups_due($staff))]);
    }

    public function opt_out()
    {
        $b = $this->body();
        try {
            $this->out(['ok' => true, 'updated' => $this->dv_model->opt_out($b['phone'] ?? '')]);
        } catch (Exception $e) {
            $this->out(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
