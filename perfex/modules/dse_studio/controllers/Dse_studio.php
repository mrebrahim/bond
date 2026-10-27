<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dse_studio extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        dse_studio_ensure_schema();
        $this->load->model('dse_studio_model');
        $this->load->library(DSE_STUDIO_MODULE_NAME . '/dse_evolution');
        $this->dse_studio_model->evo = $this->dse_evolution;
    }

    private function need($cap)
    {
        if (!staff_can($cap, DSE_STUDIO_MODULE_NAME)) {
            access_denied(DSE_STUDIO_MODULE_NAME);
        }
    }

    /** بينفّذ العملية ويعرض رسالة الموظف (الأخطاء عربي جاهزة) وبيرجع للصفحة */
    private function act($cap, callable $fn, $back, $ok = 'تم', $skip_check = false)
    {
        if (!$skip_check) {
            $this->need($cap);
        }
        $ok = $ok ?? 'تم';
        try {
            $msg = $fn();
            set_alert('success', is_string($msg) ? $msg : $ok);
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
        }
        // تنبيهات اتعملت دلوقتي (دفع / مفيش جروب) → إشعار + إيميل على طول
        if ($this->dse_studio_model->new_alerts) {
            try {
                dse_studio_notify_staff($this->dse_studio_model->new_alerts);
            } catch (Exception $e) {
                log_message('error', 'dse_studio notify: ' . $e->getMessage());
            }
        }
        // ابعت الرسايل اللي اتحطت دلوقتي على طول (والباقي مع الـ cron)
        try {
            $this->dse_studio_model->process_outbox(3);
        } catch (Exception $e) {
            log_message('error', 'dse_studio outbox: ' . $e->getMessage());
        }
        redirect($back);
    }

    private function post($k, $default = null)
    {
        $v = $this->input->post($k);
        return ($v === null || $v === '') ? $default : $v;
    }

    private function clients_list()
    {
        return $this->dse_studio_model->clients_list();
    }

    private function staff_list()
    {
        return $this->db->query('SELECT staffid, firstname, lastname FROM ' . db_prefix() . 'staff WHERE active = 1 ORDER BY firstname')->result_array();
    }

    // =================================================================
    // الصفحات
    // =================================================================
    public function index()
    {
        $this->need('view');
        $data['title']    = _l('dse_studio');
        $data['clients']  = $this->clients_list();
        $data['packages'] = $this->dse_studio_model->clients_with_packages();
        $data['today']    = $this->dse_studio_model->sessions_between(date('Y-m-d 00:00:00'), date('Y-m-d 00:00:00', strtotime('+1 day')));
        $data['alerts']   = count($this->dse_studio_model->alerts(true));
        $this->load->view('index', $data);
    }

    public function client($id)
    {
        $this->need('view');
        try {
            $data = $this->dse_studio_model->client_summary($id);
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
            redirect(admin_url('dse_studio'));
        }
        $data['title']    = $data['client']['company'];
        $data['studios']  = $this->dse_studio_model->studios();
        $data['staff']    = $this->staff_list();
        $data['sessions'] = $this->dse_studio_model->client_sessions($id);
        $data['scripts']  = $this->dse_studio_model->client_scripts($id);
        $data['posts']    = $this->dse_studio_model->client_posts($id);
        $data['outbox']   = $this->dse_studio_model->outbox(20, $id);
        $data['invoices'] = $this->db->query('SELECT id, prefix, number, total, status, date FROM ' . db_prefix() . 'invoices WHERE clientid = ? ORDER BY id DESC LIMIT 30', [(int) $id])->result_array();
        $data['balances'] = $data['pkg_invoices'] = $data['pkg_events'] = $data['pkg_paid'] = $data['pkg_busy'] = [];
        $linked = [];
        foreach ($data['packages'] as $p) {
            $data['balances'][$p['id']]     = $this->dse_studio_model->balance($p['id']);
            $data['pkg_invoices'][$p['id']] = $this->dse_studio_model->package_invoices($p['id']);
            $data['pkg_events'][$p['id']]   = $this->dse_studio_model->package_events($p['id']);
            $data['pkg_paid'][$p['id']]     = $this->dse_studio_model->package_paid_amount($p);
            $data['pkg_busy'][$p['id']]     = $this->dse_studio_model->package_has_activity($p['id']);
            $linked = array_merge($linked, array_column($data['pkg_invoices'][$p['id']], 'invoice_id'));
        }
        // الفواتير اللي لسه مش متربطة بباقة
        $all_linked = array_column($this->db->query('SELECT invoice_id FROM ' . db_prefix() . 'dse_package_invoices')->result_array(), 'invoice_id');
        $data['free_invoices'] = array_values(array_filter($data['invoices'], function ($i) use ($all_linked) { return !in_array($i['id'], $all_linked); }));
        $this->load->view('client', $data);
    }

    public function calendar()
    {
        $this->need('view');
        $ts    = strtotime($this->input->get('week') ?: date('Y-m-d')) ?: time();
        $start = date('Y-m-d', strtotime('-' . ((date('w', $ts) + 1) % 7) . ' days', $ts)); // الأسبوع بيبدأ السبت
        $studio = (int) $this->input->get('studio');
        $data['title']    = _l('dse_menu_calendar');
        $data['start']    = $start;
        $data['studio']   = $studio;
        $data['studios']  = $this->dse_studio_model->studios();
        $data['cancelled'] = (bool) $this->input->get('cancelled');
        $data['sessions'] = $this->dse_studio_model->sessions_between($start . ' 00:00:00', date('Y-m-d 00:00:00', strtotime($start . ' +7 days')), null, $studio ?: null, $data['cancelled']);
        $this->load->view('calendar', $data);
    }

    public function alerts()
    {
        $this->need('view');
        $data['title']  = _l('dse_menu_alerts');
        $data['all']    = (bool) $this->input->get('all');
        $data['alerts'] = $this->dse_studio_model->alerts(!$data['all']);
        $this->load->view('alerts', $data);
    }

    public function outbox()
    {
        $this->need('view');
        $data['title']   = _l('dse_menu_outbox');
        $data['outbox']  = $this->dse_studio_model->outbox(200);
        $data['enabled'] = get_option('dse_wa_enabled') === '1';
        $this->load->view('outbox', $data);
    }

    public function settings()
    {
        if (!is_admin()) {
            access_denied(DSE_STUDIO_MODULE_NAME);
        }
        if ($this->input->post()) {
            foreach (array_keys(dse_studio_default_options()) as $k) {
                if ($k === 'dse_last_daily_run' || $this->input->post($k) === null) {
                    continue;
                }
                if ($k === 'dse_evo_key' && $this->input->post($k) === '') {
                    continue; // الحقل الفاضي = سيب المفتاح القديم
                }
                update_option($k, trim($this->input->post($k, false)));
            }
            update_option('dse_wa_enabled', $this->input->post('dse_wa_enabled') === '1' ? '1' : '0');
            set_alert('success', 'الإعدادات اتحفظت');
            redirect(admin_url('dse_studio/settings'));
        }
        $data['title']   = _l('dse_menu_settings');
        $data['studios'] = $this->dse_studio_model->studios(false);
        $data['staff']   = $this->staff_list();
        $data['state']   = null;
        if ($this->dse_evolution->configured()) {
            try {
                $data['state'] = $this->dse_evolution->state();
            } catch (Exception $e) {
                $data['state'] = $e->getMessage();
            }
        }
        $this->load->view('settings', $data);
    }

    // =================================================================
    // العمليات
    // =================================================================
    public function create_package($clientid)
    {
        $m = $this->dse_studio_model;
        $this->act('edit', function () use ($m, $clientid) {
            $m->create_package($clientid, $this->post('name'), $this->post('price', 0), $this->post('start_date'),
                $this->post('validity_days', 30), (array) $this->input->post('items'), $this->post('invoice_id'), $this->post('notes'));
            return 'الباقة اتعملت — بتتفعل لوحدها أول ما الفاتورة تتدفع';
        }, admin_url('dse_studio/client/' . (int) $clientid));
    }

    public function link_invoice($package_id)
    {
        $m = $this->dse_studio_model;
        $p = $m->package($package_id);
        $this->act('edit', function () use ($m, $package_id) {
            $st = $m->link_invoice($package_id, $this->post('invoice_id'), $this->post('note'));
            return $st === 'active' ? 'الفاتورة اتربطت والباقة اتفعلت' : 'الفاتورة اتربطت — الباقة هتتفعل أول ما تتدفع';
        }, admin_url('dse_studio/client/' . $p['clientid']));
    }

    public function unlink_invoice($package_id, $invoice_id)
    {
        $m = $this->dse_studio_model;
        $p = $m->package($package_id);
        $this->act('edit', function () use ($m, $package_id, $invoice_id) { $m->unlink_invoice($package_id, $invoice_id); return 'الفاتورة اتشالت من الباقة'; },
            admin_url('dse_studio/client/' . $p['clientid']));
    }

    public function upgrade_package($package_id)
    {
        $m = $this->dse_studio_model;
        $p = $m->package($package_id);
        $this->act('edit', function () use ($m, $package_id) {
            $ev = $m->upgrade_package($package_id, (array) $this->input->post('items'), $this->post('price', 0),
                $this->post('extend_days', 0), $this->post('invoice_id'), $this->post('note'));
            return ['upgrade' => 'الباقة اترقّت ✅', 'downgrade' => 'الباقة اتعدّلت (أقل)', 'edit' => 'الباقة اتعدّلت'][$ev];
        }, admin_url('dse_studio/client/' . $p['clientid']));
    }

    public function activate_package($package_id)
    {
        $m = $this->dse_studio_model;
        $p = $m->package($package_id);
        if (!is_admin()) {
            access_denied(DSE_STUDIO_MODULE_NAME);
        }
        $this->act('edit', function () use ($m, $package_id) { $m->activate_package($package_id); return 'الباقة اتفعلت'; },
            admin_url('dse_studio/client/' . $p['clientid']));
    }

    public function cancel_package($package_id)
    {
        $m = $this->dse_studio_model;
        $p = $m->package($package_id);
        $this->act('delete', function () use ($m, $package_id) { $m->cancel_package($package_id); return 'الباقة اتلغت'; },
            $this->input->get('back') === 'home' ? admin_url('dse_studio') : admin_url('dse_studio/client/' . $p['clientid']));
    }

    public function restore_package($package_id)
    {
        $m = $this->dse_studio_model;
        $p = $m->package($package_id);
        $this->act('delete', function () use ($m, $package_id) { $m->restore_package($package_id); return 'الباقة رجعت'; },
            admin_url('dse_studio/client/' . $p['clientid']));
    }

    public function edit_package($package_id)
    {
        $m = $this->dse_studio_model;
        $p = $m->package($package_id);
        $this->act('edit', function () use ($m, $package_id) {
            $m->edit_package($package_id, $this->post('name'), $this->post('start_date'), $this->post('end_date'), $this->post('notes'));
            return 'بيانات الباقة اتعدّلت';
        }, admin_url('dse_studio/client/' . $p['clientid']));
    }

    public function delete_package($package_id)
    {
        $m = $this->dse_studio_model;
        $p = $m->package($package_id);
        $this->act('delete', function () use ($m, $package_id) { $m->delete_package($package_id); return 'الباقة اتمسحت'; },
            $this->input->get('back') === 'home' ? admin_url('dse_studio') : admin_url('dse_studio/client/' . $p['clientid']));
    }

    public function book($clientid)
    {
        $m = $this->dse_studio_model;
        $this->act('create', function () use ($m, $clientid) {
            $m->book_session($clientid, $this->post('studio_id'), $this->post('start_at'), $this->post('type', 'reel'),
                $this->post('qty'), $this->post('photographer_id'), $this->post('notes'));
            return 'الجلسة اتحجزت ✅ ورسالة "تم الحجز" راحت للعميل';
        }, admin_url('dse_studio/client/' . (int) $clientid));
    }

    private function session_action($id, $cap, callable $fn, $ok)
    {
        $s = $this->dse_studio_model->session($id);
        $this->act($cap, $fn, $this->input->get('back') === 'calendar' ? admin_url('dse_studio/calendar') : admin_url('dse_studio/client/' . $s['clientid']), $ok);
    }

    public function reschedule($id)
    {
        $m = $this->dse_studio_model;
        $this->session_action($id, 'create', function () use ($m, $id) {
            $m->reschedule_session($id, $this->post('start_at'), $this->post('studio_id'), $this->post('photographer_id', 0));
        }, 'الميعاد اتغيّر');
    }

    public function complete($id)
    {
        $m = $this->dse_studio_model;
        $this->session_action($id, 'create', function () use ($m, $id) { $m->complete_session($id, $this->post('actual_qty')); }, 'الجلسة اتقفلت والرصيد اتخصم');
    }

    public function correct_qty($id)
    {
        $m = $this->dse_studio_model;
        $this->session_action($id, 'create', function () use ($m, $id) {
            $old = $m->correct_session_qty($id, $this->post('actual_qty'), $this->post('reason'));
            return 'العدد اتصحح من ' . dse_num($old) . ' لـ ' . dse_num($this->post('actual_qty')) . ' والرصيد اتظبط';
        }, '');
    }

    public function no_show($id)
    {
        $m = $this->dse_studio_model;
        $this->session_action($id, 'create', function () use ($m, $id) {
            return 'اتسجل إنه مجاش واتخصم ' . dse_num($m->mark_no_show($id));
        }, '');
    }

    public function cancel_session($id)
    {
        $m = $this->dse_studio_model;
        $this->session_action($id, 'create', function () use ($m, $id) {
            $r = $m->cancel_session($id, $this->post('reason'));
            return $r['late'] ? 'اتلغت — إلغاء متأخر واتخصم ' . dse_num($r['penalty']) : 'اتلغت من غير خصم';
        }, '');
    }

    public function add_script($clientid)
    {
        $m = $this->dse_studio_model;
        $this->act('create', function () use ($m, $clientid) {
            $id = $m->add_script($clientid, $this->post('title'), $this->input->post('body', false));
            if ($this->post('send')) {
                $m->send_script($id);
                return 'السكربت اتبعت للعميل';
            }
            return 'السكربت اتحفظ';
        }, admin_url('dse_studio/client/' . (int) $clientid));
    }

    public function script_action($id, $action)
    {
        $m = $this->dse_studio_model;
        $s = $m->script($id);
        $this->act('create', function () use ($m, $id, $action) {
            if ($action === 'send') {
                $m->send_script($id);
                return 'السكربت اتبعت للعميل';
            }
            if ($action === 'update') {
                $m->update_script($id, $this->post('title'), $this->input->post('body', false));
                return 'السكربت اتعدّل';
            }
            $m->set_script_status($id, $action);
        }, admin_url('dse_studio/client/' . $s['clientid']));
    }

    private function can_publish()
    {
        return staff_can('publish', DSE_STUDIO_MODULE_NAME) || staff_can('create', DSE_STUDIO_MODULE_NAME);
    }

    /** صفحة المودريشن: يختار العميل ويحط لينك البوست ← يتبعت لجروب العميل */
    public function publishing()
    {
        if (!$this->can_publish()) {
            access_denied(DSE_STUDIO_MODULE_NAME);
        }
        $data['title']   = _l('dse_menu_publish');
        $data['clients'] = $this->dse_studio_model->clients_for_publishing();
        $data['recent']  = $this->dse_studio_model->recent_posts(40);
        $data['selected'] = (int) $this->input->get('client');
        $this->load->view('publishing', $data);
    }

    public function publish($clientid = null)
    {
        if (!$this->can_publish()) {
            access_denied(DSE_STUDIO_MODULE_NAME);
        }
        $clientid = $clientid ?: (int) $this->input->post('clientid');
        $back     = $this->input->post('back') === 'publishing' ? admin_url('dse_studio/publishing') : admin_url('dse_studio/client/' . (int) $clientid);
        $m        = $this->dse_studio_model;
        $this->act('view', function () use ($m, $clientid) {
            if (!$clientid) {
                throw new Exception('اختار العميل');
            }
            $m->publish_post($clientid, $this->post('type', 'reel'), $this->post('post_url'), $this->post('title'));
            return 'اتسجل إنه نزل ورسالة اللينك والتقييم راحت لجروب العميل';
        }, $back, null, true);
    }

    public function wa($clientid, $action)
    {
        $m = $this->dse_studio_model;
        $this->act('create', function () use ($m, $clientid, $action) {
            switch ($action) {
                case 'phone':
                    $m->set_wa_phone($clientid, $this->post('wa_phone'));
                    return 'رقم الواتساب اتحفظ';
                case 'create_group':
                    $g = $m->create_group($clientid);
                    return 'الجروب اتعمل ✅ ' . ($g['link'] ?: '');
                case 'link_group':
                    $m->link_group($clientid, $this->post('link'));
                    return 'الجروب اتربط';
                case 'add_team':
                    $m->add_team_to_group($clientid);
                    return 'الفريق اتضاف للجروب';
                case 'manual':
                    $m->send_manual($clientid, $this->post('key'));
                    return 'الرسالة اتحطت في الطابور وهتتبعت';
            }
            throw new Exception('عملية غير معروفة');
        }, admin_url('dse_studio/client/' . (int) $clientid));
    }

    public function retry($id)
    {
        $m = $this->dse_studio_model;
        $this->act('create', function () use ($m, $id) { $m->retry_message($id); return 'هتتبعت تاني'; }, admin_url('dse_studio/outbox'));
    }

    public function resolve_alert($id)
    {
        $m = $this->dse_studio_model;
        $this->act('view', function () use ($m, $id) { $m->resolve_alert($id, $this->post('resolution')); return 'التنبيه اتقفل'; }, admin_url('dse_studio/alerts'));
    }

    public function test_wa()
    {
        if (!is_admin()) {
            access_denied(DSE_STUDIO_MODULE_NAME);
        }
        try {
            $to = dse_normalize_phone($this->input->post('to'));
            if (!$to) {
                throw new Exception('اكتب رقم مصري صح: 01xxxxxxxxx');
            }
            if (!$this->dse_evolution->configured()) {
                throw new Exception('حط الـ URL والـ API key واسم الـ instance الأول واحفظ');
            }
            $this->dse_evolution->send_text($to, 'رسالة تجربة من Perfex ✅');
            set_alert('success', 'الرسالة اتبعتت لـ ' . $to . ' — شوف الواتساب');
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('dse_studio/settings'));
    }

    public function studio($action, $id = null)
    {
        if (!is_admin()) {
            access_denied(DSE_STUDIO_MODULE_NAME);
        }
        $m = $this->dse_studio_model;
        $this->act('view', function () use ($m, $action, $id) {
            if ($action === 'add') {
                $m->add_studio($this->post('name'));
                return 'الاستوديو اتضاف';
            }
            $m->toggle_studio($id);
        }, admin_url('dse_studio/settings'));
    }
}
