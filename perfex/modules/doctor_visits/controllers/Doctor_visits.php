<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Doctor_visits extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        dv_ensure_schema(); // لو Perfex ماشغّلش التفعيل/الـ migration بعد رفع نسخة جديدة
    }

    public function index()
    {
        redirect(admin_url('doctor_visits/settings'));
    }

    /** المرحلة 1: صفحة فحص — الإعدادات والجداول والمراحل والقوالب، ونسخة Perfex والـ Hooks المتاحة لتاب الـ Lead */
    public function settings()
    {
        if (!staff_can('manage_settings', DOCTOR_VISITS_MODULE_NAME) && !is_admin()) {
            access_denied(DOCTOR_VISITS_MODULE_NAME);
        }
        $p = db_prefix();

        $data['title']    = _l('dv_menu_settings');
        $data['settings'] = $this->db->query('SELECT * FROM `' . $p . 'dv_settings` ORDER BY staff_id, `key`')->result_array();
        $data['templates'] = $this->db->query('SELECT * FROM `' . $p . 'dv_wa_templates` ORDER BY sort')->result_array();

        $data['tables'] = [];
        foreach (array_keys(dv_schema()) as $t) {
            $ok = $this->db->table_exists($p . $t);
            $data['tables'][$t] = $ok ? (int) $this->db->count_all($p . $t) : null;
        }

        $data['statuses'] = [];
        foreach (dv_stages() as $i => $s) {
            $id = (int) dv_setting('stage_status_' . $i);
            $row = $id ? $this->db->query('SELECT id, name, color FROM `' . $p . 'leads_status` WHERE id = ?', [$id])->row_array() : null;
            $data['statuses'][] = ['stage' => $i, 'progress' => $s['progress'], 'expected' => $s['name'], 'row' => $row];
        }
        $lid = (int) dv_setting('lost_status');
        $data['statuses'][] = ['stage' => '—', 'progress' => '', 'expected' => dv_lost_status_name(),
            'row' => $lid ? $this->db->query('SELECT id, name, color FROM `' . $p . 'leads_status` WHERE id = ?', [$lid])->row_array() : null];

        $data['perfex'] = $this->perfex_info();
        $data['hooks']  = $this->lead_hooks();
        $this->load->view('settings', $data);
    }

    /** نسخة Perfex المتثبتة (من الـ migrations) + PHP + الداتابيز */
    private function perfex_info()
    {
        $info = ['php' => PHP_VERSION, 'db' => $this->db->platform() . ' ' . $this->db->version()];
        $info['app'] = null;
        if (isset($this->app) && method_exists($this->app, 'get_current_db_version')) {
            $info['app'] = $this->app->get_current_db_version();
        } elseif ($this->db->table_exists(db_prefix() . 'migrations')) {
            $row = $this->db->query('SELECT version FROM `' . db_prefix() . 'migrations` LIMIT 1')->row_array();
            $info['app'] = $row['version'] ?? null;
        }
        $info['timezone'] = date_default_timezone_get();
        return $info;
    }

    /**
     * الـ Hooks الموجودة فعلًا في ملفات صفحة الـ Lead على السيرفر ده (PRD §7.2 — "محتاج نتأكد من اسم الـ Hook").
     * بيقرا ملفات الـ views بتاعة الـ leads ويطلّع كل do_action / apply_filters.
     */
    private function lead_hooks()
    {
        $dir  = APPPATH . 'views/admin/leads/';
        $out  = [];
        foreach (glob($dir . '*.php') ?: [] as $file) {
            $src = (string) @file_get_contents($file);
            if (preg_match_all('/hooks\(\)\s*->\s*(do_action|apply_filters)\(\s*[\'"]([a-z0-9_]+)[\'"]/i', $src, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $out[] = ['file' => basename($file), 'type' => $x[1], 'hook' => $x[2]];
                }
            }
        }
        return $out;
    }
}
