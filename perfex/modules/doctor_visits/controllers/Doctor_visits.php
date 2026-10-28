<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Doctor_visits extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        // الـ helpers من هنا كمان: لو السيرفر شايل نسخة قديمة من doctor_visits.php في الكاش (OPcache) الدوال الجديدة تفضل موجودة
        require_once __DIR__ . '/../helpers/dv_setup.php';
        require_once __DIR__ . '/../helpers/dv_helper.php';
        require_once __DIR__ . '/../helpers/dv_import.php';
        dv_ensure_schema(); // لو Perfex ماشغّلش التفعيل بعد رفع نسخة جديدة
        $this->load->model(DOCTOR_VISITS_MODULE_NAME . '/dv_model');
    }

    // ================================================================= أدوات
    private function can($cap)
    {
        return is_admin() || staff_can($cap, DOCTOR_VISITS_MODULE_NAME);
    }

    private function need_view()
    {
        if (!$this->can('view') && !$this->can('view_own')) {
            access_denied(DOCTOR_VISITS_MODULE_NAME);
        }
    }

    private function need($cap)
    {
        if (!$this->can($cap)) {
            access_denied(DOCTOR_VISITS_MODULE_NAME);
        }
    }

    private function post($k, $default = null)
    {
        $v = $this->input->post($k);
        return ($v === null || $v === '') ? $default : $v;
    }

    /** بينفّذ ويعرض رسالة الخطأ العربي زي ما هي، والـ feedback (Toast / Level up) بيتعرض في الصفحة الجاية */
    private function act(callable $fn, $back, $ok = null)
    {
        try {
            $msg = $fn();
            if ($this->dv_model->feedback) {
                $this->session->set_flashdata('dv_feedback', json_encode($this->dv_model->feedback, JSON_UNESCAPED_UNICODE));
            } elseif ($ok || is_string($msg)) {
                set_alert('success', is_string($msg) ? $msg : $ok);
            }
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect($back);
    }

    private function view($name, array $data)
    {
        $data['dv_feedback'] = json_decode((string) $this->session->flashdata('dv_feedback'), true) ?: [];
        $data['dv_can']      = [
            'create' => $this->can('create'), 'edit' => $this->can('edit'), 'all' => $this->can('view'),
            'settings' => $this->can('manage_settings'), 'delete' => $this->can('delete'),
        ];
        $this->load->view($name, $data);
    }

    private function staff_list()
    {
        return $this->db->query('SELECT staffid, firstname, lastname FROM ' . db_prefix() . 'staff WHERE active = 1 ORDER BY firstname')->result_array();
    }

    // ================================================================= الشاشات
    public function index()
    {
        redirect(admin_url('doctor_visits/queue'));
    }

    /** الداشبورد: الهدف، الـ Streak، القيمة، منطقة التقفيل، المهام، الـ Funnel */
    public function dashboard()
    {
        $this->need_view();
        $me = get_staff_user_id();
        $this->view('dashboard', ['title' => _l('dv_menu_dashboard'), 'd' => $this->dv_model->dashboard($me)]);
    }

    /** أزور مين النهارده (F4) — Mobile-first */
    public function queue()
    {
        $this->need_view();
        $me = get_staff_user_id();
        if ($this->input->post('building_id') !== null) {
            dv_set_setting('current_building', (int) $this->input->post('building_id'), $me);
            redirect(admin_url('doctor_visits/queue'));
        }
        $here = (int) dv_setting('current_building', $me, 0);
        $this->view('queue', [
            'title'     => _l('dv_menu_queue'),
            'doctors'   => $this->dv_model->queue($me, $here ?: null),
            'buildings' => $this->dv_model->buildings(),
            'here'      => $here,
            'target'    => $this->dv_model->daily_target($me),
            'done'      => $this->dv_model->visits_on($me, date('Y-m-d')),
            'zone'      => $this->dv_model->closing_zone($this->can('view') ? null : $me),
            'level'     => $this->dv_model->level($me),
        ]);
    }

    /** Quick Log (F3) — اختار الدكتور ← زرار ← حفظ */
    public function log($lead_id = null)
    {
        $this->need('create');
        if ($this->input->post('outcome')) {
            $lead_id = (int) $this->input->post('lead_id');
            $back    = $this->input->post('back') ?: admin_url('doctor_visits/queue');
            $this->act(function () use ($lead_id) {
                $this->dv_model->log_visit($lead_id, [
                    'outcome' => $this->post('outcome'), 'note' => $this->post('note'), 'next_action' => $this->post('next_action'),
                    'next_action_at' => $this->post('next_action_at'), 'lost_reason' => $this->post('lost_reason'),
                    'visited_at' => $this->post('visited_at'), 'deal_value' => $this->post('deal_value'), 'consent_now' => $this->post('consent_now'),
                ]);
            }, $back);
            return;
        }
        $doc = null;
        if ($lead_id) {
            try {
                $doc = $this->dv_model->doctor($lead_id);
            } catch (Exception $e) {
                set_alert('danger', $e->getMessage());
                redirect(admin_url('doctor_visits/log'));
            }
        }
        $this->view('quick_log', [
            'title'   => _l('dv_menu_log'),
            'doc'     => $doc,
            'doctors' => $doc ? [] : $this->dv_model->doctors(['open' => 1, 'order' => 'b.name, m.floor, l.name']),
            'back'    => $this->input->get('back') ? admin_url(ltrim($this->input->get('back'), '/')) : '',
        ]);
    }

    public function buildings()
    {
        $this->need_view();
        if ($this->input->post('name') !== null) {
            $this->need('create');
            $this->act(function () {
                $id = $this->dv_model->save_building($this->input->post());
                return 'العمارة اتضافت';
            }, admin_url('doctor_visits/buildings'));
            return;
        }
        $this->view('buildings', ['title' => _l('dv_menu_buildings'), 'buildings' => $this->dv_model->buildings()]);
    }

    public function building($id)
    {
        $this->need_view();
        if ($this->input->post('name') !== null) {
            $this->need('edit');
            $this->act(function () use ($id) { $this->dv_model->save_building($this->input->post(), $id); return 'اتحفظ'; }, admin_url('doctor_visits/building/' . (int) $id));
            return;
        }
        try {
            $b = $this->dv_model->building($id);
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
            redirect(admin_url('doctor_visits/buildings'));
        }
        $cov = array_values(array_filter($this->dv_model->buildings(), function ($x) use ($id) { return (int) $x['id'] === (int) $id; }))[0] ?? $b + ['coverage' => 0];
        $this->view('building', [
            'title' => $b['name'], 'b' => $cov, 'doctors' => $this->dv_model->doctors(['building_id' => $id, 'order' => 'm.floor, l.name']),
            'staff' => $this->staff_list(),
        ]);
    }

    public function quick_add()
    {
        $this->need('create');
        $back = $this->input->post('back') ?: admin_url('doctor_visits/building/' . (int) $this->input->post('building_id'));
        try {
            $lead = $this->dv_model->quick_add($this->input->post());
            set_alert('success', 'الدكتور اتضاف');
            redirect($this->input->post('then_log') ? admin_url('doctor_visits/log/' . $lead) : $back);
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
            redirect($back);
        }
    }

    /** صفحة الدكتور: البيانات، الزيارات، الـ QR، الواتساب */
    public function doctor($lead_id)
    {
        $this->need_view();
        try {
            $doc = $this->dv_model->doctor($lead_id);
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
            redirect(admin_url('doctor_visits/queue'));
        }
        $this->view('doctor', [
            'title' => $doc['name'], 'doc' => $doc, 'timeline' => $this->dv_model->timeline($lead_id), 'path' => $this->dv_model->path_card($lead_id),
            'buildings' => $this->dv_model->buildings(), 'staff' => $this->staff_list(), 'qr_link' => $this->dv_model->qr_link($doc),
        ]);
    }

    public function save_doctor($lead_id)
    {
        $this->need('edit');
        $this->act(function () use ($lead_id) {
            $this->dv_model->update_doctor($lead_id, $this->input->post());
            return 'اتحفظ';
        }, admin_url('doctor_visits/doctor/' . (int) $lead_id));
    }

    public function consent($lead_id)
    {
        $this->need('create');
        $this->act(function () use ($lead_id) {
            $this->dv_model->doctor($lead_id);
            $this->dv_model->set_consent($lead_id, (bool) $this->input->post('on'), 'in_person');
            return 'اتسجل';
        }, $this->input->post('back') ?: admin_url('doctor_visits/doctor/' . (int) $lead_id));
    }

    /** زرار الواتساب (F8): القالب المقترح ← افتح واتساب ← "اتبعتت؟" */
    public function wa($lead_id)
    {
        $this->need('create');
        if ($this->input->post('sent') !== null) {
            $back = $this->input->post('back') ?: admin_url('doctor_visits/doctor/' . (int) $lead_id);
            if ($this->input->post('sent') !== '1') {
                redirect($back);
            }
            $this->act(function () use ($lead_id) {
                $this->dv_model->wa_sent($lead_id, $this->post('template'), (int) $this->post('next_days', 2));
            }, $back);
            return;
        }
        try {
            $ctx = $this->dv_model->wa_context($lead_id);
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
            redirect(admin_url('doctor_visits/queue'));
        }
        $this->view('wa', ['title' => _l('dv_wa_title'), 'ctx' => $ctx, 'back' => $this->input->get('back') ? admin_url(ltrim($this->input->get('back'), '/')) : '']);
    }

    // ================================================================= التقارير (F7)
    public function reports()
    {
        $this->need_view();
        $from  = $this->input->get('from') ?: date('Y-m-01');
        $to    = $this->input->get('to') ?: date('Y-m-d');
        $group = in_array($this->input->get('group'), ['building', 'specialty', 'staff'], true) ? $this->input->get('group') : 'building';
        $this->view('reports', [
            'title' => _l('dv_menu_reports'), 'from' => $from, 'to' => $to, 'group' => $group,
            'funnel' => $this->dv_model->funnel($group, $from, $to), 'lost' => $this->dv_model->lost_reasons_report(),
            'cycle' => $this->dv_model->sales_cycle(), 'gatekeepers' => $this->dv_model->gatekeepers_report(),
        ]);
    }

    /** CSV للمدير بس (PRD §9) */
    public function export_csv()
    {
        $this->need('view');
        $from = $this->input->get('from') ?: date('Y-m-01');
        $to   = $this->input->get('to') ?: date('Y-m-d');
        $rows = $this->dv_model->visits_export($from, $to);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="doctor_visits_' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel يقرا العربي
        fputcsv($out, ['visited_at', 'logged_at', 'doctor', 'specialty', 'building', 'staff', 'outcome', 'channel', 'stage_before', 'stage_after', 'lost_reason', 'next_action', 'next_action_at', 'note']);
        foreach ($rows as $r) {
            fputcsv($out, array_values($r));
        }
        fclose($out);
        exit;
    }

    /** استيراد شيت الدكاترة: ارفع ← شوف هيحصل إيه ← أكّد */
    public function import($id = null)
    {
        $this->need('create');
        if (!empty($_FILES['file']['tmp_name'])) {
            $id = null;
            try {
                $f = $_FILES['file'];
                if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024) {
                    throw new Exception('الملف مارفعش — لازم يبقى أقل من 10 ميجا');
                }
                $parsed = dv_import_parse(dv_read_sheets($f['tmp_name'], $f['name']));
                $id     = $this->dv_model->import_save_preview($parsed, basename($f['name']));
            } catch (Exception $e) {
                set_alert('danger', $e->getMessage());
            }
            redirect(admin_url('doctor_visits/import' . ($id ? '/' . $id : '')));
        }
        if ($id && $this->input->post('confirm')) {
            $this->act(function () use ($id) {
                $r = $this->dv_model->import_run($id, $this->input->post('staff_id'));
                return 'اتستورد: ' . $r['new'] . ' دكتور جديد · ' . $r['update'] . ' اتحدّث · ' . $r['visits'] . ' زيارة · ' . $r['buildings'] . ' عمارة جديدة'
                    . ($r['errors'] ? ' — ' . count($r['errors']) . ' سطر فيه مشكلة (تحت)' : '');
            }, admin_url('doctor_visits/import/' . (int) $id));
        }
        $data = ['title' => _l('dv_menu_import'), 'file' => null, 'plan' => null, 'staff' => $this->can('view') ? $this->staff_list() : []];
        if ($id) {
            try {
                $data['file'] = $this->dv_model->import_load($id);
                $data['plan'] = $data['file']['done'] ? null : $this->dv_model->import_plan($data['file']);
            } catch (Exception $e) {
                set_alert('danger', $e->getMessage());
                redirect(admin_url('doctor_visits/import'));
            }
        }
        $this->view('import', $data);
    }

    /** الدكاترة بنفس أعمدة الشيت (CSV يفتح في Excel) — عدّل وارفعه تاني */
    public function export_doctors()
    {
        $this->need_view();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="doctors_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($this->dv_model->import_export_rows() as $r) {
            fputcsv($out, $r);
        }
        fclose($out);
        exit;
    }

    /** دليل السيلز (F9): الشنطة اليومية + كل خطوات المسار مرجع */
    public function manual()
    {
        $this->need_view();
        $this->view('manual', [
            'title' => _l('dv_menu_manual'), 'daily' => $this->dv_model->path_daily(get_staff_user_id()), 'steps' => $this->dv_model->path_steps('lead'),
        ]);
    }

    /**
     * F9 routes: path/toggle (POST lead_id, item_key, checked) · path/daily_toggle (POST item_key, checked) · path/lead/{id} (الكارت لوحده)
     * بيرجع JSON لو الطلب AJAX، وإلا بيرجع للصفحة
     */
    public function path($action = null, $id = null)
    {
        $this->need_view();
        $ajax = $this->input->post('ajax') || strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        if ($action === 'report') { // للمدير بس
            $this->need('view');
            $f = ['staff_id' => (int) $this->input->get('staff_id') ?: null, 'building_id' => (int) $this->input->get('building_id') ?: null,
                'step' => $this->input->get('step') ?: null, 'stalled' => $this->input->get('stalled') ? 1 : null];
            $this->view('path_report', ['title' => _l('dv_menu_path_report'), 'r' => $this->dv_model->path_report($f), 'f' => $f,
                'staff' => $this->staff_list(), 'buildings' => $this->dv_model->buildings()]);
            return;
        }
        if ($action === 'lead') {
            $this->view('_path_card', ['path' => $this->dv_model->path_card((int) $id), 'lead_id' => (int) $id]);
            return;
        }
        if (!in_array($action, ['toggle', 'daily_toggle'], true) || $this->input->post('item_key') === null) {
            show_404();
        }
        $lead = (int) $this->input->post('lead_id');
        $back = $action === 'toggle' ? admin_url('doctor_visits/doctor/' . $lead . '#dv-path') : admin_url('doctor_visits/manual');
        try {
            $on = (bool) $this->input->post('checked');
            if ($action === 'toggle') {
                $this->dv_model->path_toggle($lead, $this->input->post('item_key'), $on);
                $card = $this->dv_model->path_card($lead);
            } else {
                $this->dv_model->path_daily_toggle($this->input->post('item_key'), $on);
                $card = $this->dv_model->path_daily(get_staff_user_id());
            }
            $out = ['ok' => true, 'done' => $card['done'], 'total' => $card['total'], 'next' => $card['next']['text'] ?? null, 'open' => $card['open'],
                'steps' => array_map(function ($s) { return ['key' => $s['key'], 'done' => $s['done'], 'count' => count($s['items']), 'full' => $s['full']]; }, $card['steps']),
                'by' => $on ? sprintf(_l('dv_path_checked_by'), $this->dv_model->staff_name(get_staff_user_id()), date('d/m g:i')) : ''];
        } catch (Exception $e) {
            $out = ['ok' => false, 'error' => $e->getMessage()];
        }
        if ($ajax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!$out['ok']) {
            set_alert('danger', $out['error']);
        }
        redirect($back);
    }

    // ================================================================= الإعدادات
    public function settings()
    {
        if (!$this->can('manage_settings')) {
            access_denied(DOCTOR_VISITS_MODULE_NAME);
        }
        $p = db_prefix();
        if (!empty($_FILES['path_seed']['tmp_name'])) { // F9: محتوى المسار
            try {
                $r = dv_path_seed($_FILES['path_seed']['tmp_name'], true);
                set_alert($r['warnings'] ? 'warning' : 'success', 'المسار: ' . $r['counts']['steps'] . ' خطوة · ' . $r['counts']['items'] . ' بند · ' . $r['counts']['scripts']
                    . ' سكريبت (اتضاف: ' . $r['added']['steps'] . ' · ' . $r['added']['items'] . ' · ' . $r['added']['scripts'] . ')' . ($r['warnings'] ? ' — ' . implode(' · ', $r['warnings']) : ''));
            } catch (Exception $e) {
                set_alert('danger', $e->getMessage());
            }
            redirect(admin_url('doctor_visits/settings#path'));
        }
        if ($this->input->post('save_settings')) {
            $editable = ['monthly_target', 'avg_deal_value', 'sprint_price', 'path_stalled_days', 'default_visits_per_close', 'default_touches_to_win', 'off_days', 'min_daily_for_streak',
                'cold_start_min_wins', 'quest_hour', 'qr_whatsapp_number', 'api_rate_limit', 'xp_rules', 'level_thresholds', 'level_names', 'level_rewards'];
            try {
                foreach ($editable as $k) {
                    $v = $this->input->post($k, false);
                    if ($v === null) {
                        continue;
                    }
                    if (in_array($k, ['xp_rules', 'level_thresholds', 'level_names', 'level_rewards'], true) && json_decode($v, true) === null) {
                        throw new Exception('القيمة "' . $k . '" لازم تبقى JSON صح');
                    }
                    dv_set_setting($k, trim($v));
                }
                set_alert('success', _l('dv_settings_saved'));
            } catch (Exception $e) {
                set_alert('danger', $e->getMessage());
            }
            redirect(admin_url('doctor_visits/settings'));
        }
        if ($this->input->post('regenerate_token')) {
            dv_set_setting('api_token', dv_random_token());
            set_alert('success', 'الـ Token اتغيّر — حدّثه في n8n');
            redirect(admin_url('doctor_visits/settings'));
        }
        if ($this->input->post('save_template')) {
            try {
                $this->dv_model->save_template($this->input->post(null, false), $this->input->post('id') ?: null);
                set_alert('success', 'القالب اتحفظ');
            } catch (Exception $e) {
                set_alert('danger', $e->getMessage());
            }
            redirect(admin_url('doctor_visits/settings#templates'));
        }

        $data['title']     = _l('dv_menu_settings');
        $data['settings']  = $this->db->query('SELECT * FROM `' . $p . 'dv_settings` ORDER BY staff_id, `key`')->result_array();
        $data['templates'] = $this->dv_model->templates(false);
        $data['path_seed'] = json_decode((string) get_option('dv_path_seed_report'), true);
        $data['tables']    = [];
        foreach (array_keys(dv_schema()) as $t) {
            $data['tables'][$t] = $this->db->table_exists($p . $t) ? (int) $this->db->count_all($p . $t) : null;
        }
        $data['statuses'] = [];
        foreach (dv_stages() as $i => $s) {
            $id = (int) dv_setting('stage_status_' . $i);
            $data['statuses'][] = ['stage' => $i, 'progress' => $s['progress'], 'expected' => $s['name'],
                'row' => $id ? $this->db->query('SELECT id, name, color FROM `' . $p . 'leads_status` WHERE id = ?', [$id])->row_array() : null];
        }
        $lid = (int) dv_setting('lost_status');
        $data['statuses'][] = ['stage' => '—', 'progress' => '', 'expected' => dv_lost_status_name(),
            'row' => $lid ? $this->db->query('SELECT id, name, color FROM `' . $p . 'leads_status` WHERE id = ?', [$lid])->row_array() : null];
        $data['perfex']  = $this->perfex_info();
        $data['hooks']   = $this->lead_hooks();
        $data['api_base'] = site_url('doctor_visits/dv_api');
        $this->view('settings', $data);
    }

    private function perfex_info()
    {
        $info = ['php' => PHP_VERSION, 'db' => $this->db->platform() . ' ' . $this->db->version(), 'app' => null, 'timezone' => date_default_timezone_get()];
        if (isset($this->app) && method_exists($this->app, 'get_current_db_version')) {
            $info['app'] = $this->app->get_current_db_version();
        } elseif ($this->db->table_exists(db_prefix() . 'migrations')) {
            $info['app'] = $this->db->query('SELECT version FROM `' . db_prefix() . 'migrations` LIMIT 1')->row_array()['version'] ?? null;
        }
        return $info;
    }

    /** الـ Hooks الموجودة فعلًا في ملفات صفحة الـ Lead على السيرفر ده */
    private function lead_hooks()
    {
        $out = [];
        foreach (glob(APPPATH . 'views/admin/leads/*.php') ?: [] as $file) {
            if (preg_match_all('/hooks\(\)\s*->\s*(do_action|apply_filters)\(\s*[\'"]([a-z0-9_]+)[\'"]/i', (string) @file_get_contents($file), $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $out[] = ['file' => basename($file), 'type' => $x[1], 'hook' => $x[2]];
                }
            }
        }
        return $out;
    }
}
