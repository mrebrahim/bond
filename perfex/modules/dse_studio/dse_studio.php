<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Digital Solution — الاستوديو والباقات
Description: رصيد الباقات، حجز الاستوديو، السكربتات والنشر، جروبات واتساب العملاء (Evolution API)، وتنبيه العميل اللي مابيصوّرش.
Version: 1.0.4
Requires at least: 3.0.*
Author: Digital Solution
*/

define('DSE_STUDIO_MODULE_NAME', 'dse_studio');

$CI = &get_instance();
require_once __DIR__ . '/helpers/dse_studio_setup.php';
$CI->load->helper(DSE_STUDIO_MODULE_NAME . '/dse_studio');

register_activation_hook(DSE_STUDIO_MODULE_NAME, 'dse_studio_activation_hook');
register_language_files(DSE_STUDIO_MODULE_NAME, [DSE_STUDIO_MODULE_NAME]);
register_cron_task('dse_studio_cron');

hooks()->add_action('admin_init', 'dse_studio_admin_init');

function dse_studio_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/install.php';
}

function dse_studio_admin_init()
{
    $CI = &get_instance();

    register_staff_capabilities(DSE_STUDIO_MODULE_NAME, [
        'capabilities' => [
            'view'   => _l('dse_cap_view'),
            'create' => _l('dse_cap_create'),
            'edit'   => _l('dse_cap_edit'),
            'delete' => _l('dse_cap_delete'),
            'publish' => _l('dse_cap_publish'),
        ],
    ], _l('dse_studio'));

    $can_publish = staff_can('publish', DSE_STUDIO_MODULE_NAME) || staff_can('create', DSE_STUDIO_MODULE_NAME);
    if (!staff_can('view', DSE_STUDIO_MODULE_NAME) && $can_publish) {
        // المودريشن: صفحة النشر بس
        $CI->app_menu->add_sidebar_menu_item('dse-studio-publish', [
            'name' => _l('dse_menu_publish'), 'href' => admin_url('dse_studio/publishing'), 'position' => 6, 'icon' => 'fa fa-share-square-o',
        ]);
    }
    if (staff_can('view', DSE_STUDIO_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_menu_item('dse-studio', [
            'name'     => _l('dse_studio'),
            'href'     => admin_url('dse_studio'),
            'position' => 6,
            'icon'     => 'fa fa-video-camera',
        ]);
        $children = [
            ['dse-studio-home', 'dse_menu_home', 'dse_studio', 1],
            ['dse-studio-calendar', 'dse_menu_calendar', 'dse_studio/calendar', 2],
            ['dse-studio-publish', 'dse_menu_publish', 'dse_studio/publishing', 2],
            ['dse-studio-alerts', 'dse_menu_alerts', 'dse_studio/alerts', 3],
            ['dse-studio-outbox', 'dse_menu_outbox', 'dse_studio/outbox', 4],
        ];
        if (is_admin()) {
            $children[] = ['dse-studio-messages', 'dse_menu_messages', 'dse_studio/messages', 5];
            $children[] = ['dse-studio-settings', 'dse_menu_settings', 'dse_studio/settings', 6];
        }
        foreach ($children as $c) {
            $CI->app_menu->add_sidebar_children_item('dse-studio', [
                'slug' => $c[0], 'name' => _l($c[1]), 'href' => admin_url($c[2]), 'position' => $c[3],
            ]);
        }

        // تاب في بروفايل العميل (لو النسخة بتدعمها)
        if (isset($CI->app_tabs) && method_exists($CI->app_tabs, 'add_customer_profile_tab')) {
            $CI->app_tabs->add_customer_profile_tab('dse_studio', [
                'name'     => _l('dse_studio'),
                'icon'     => 'fa fa-video-camera',
                'view'     => 'dse_studio/customer_tab',
                'position' => 6,
            ]);
        }
    }
}

/** بيشتغل مع cron بتاع Perfex: الواتساب كل مرة، والتنبيهات مرة في اليوم */
function dse_studio_cron()
{
    $CI = &get_instance();
    dse_studio_ensure_schema();
    $CI->load->model(DSE_STUDIO_MODULE_NAME . '/dse_studio_model');
    $CI->load->library(DSE_STUDIO_MODULE_NAME . '/dse_evolution');
    $CI->dse_studio_model->evo = $CI->dse_evolution;

    try {
        $CI->dse_studio_model->process_outbox(10);
    } catch (Exception $e) {
        log_message('error', 'dse_studio outbox: ' . $e->getMessage());
    }

    if (get_option('dse_last_daily_run') === date('Y-m-d')) {
        return;
    }
    update_option('dse_last_daily_run', date('Y-m-d'));
    try {
        $CI->dse_studio_model->sync_all_packages();
        $new = array_merge($CI->dse_studio_model->check_inactive_clients(), $CI->dse_studio_model->check_all_payments());
        if ($new) {
            dse_studio_notify_staff($new);
        }
    } catch (Exception $e) {
        log_message('error', 'dse_studio daily: ' . $e->getMessage());
    }
}

/**
 * إشعار جوه Perfex (الجرس) + إيميل للموظفين المسؤولين عن نوع التنبيه:
 * payment_due → dse_payment_notify_staff_ids · الباقي → dse_notify_staff_ids (فاضي = الأدمنز)
 */
function dse_studio_notify_staff(array $alert_ids)
{
    $CI = &get_instance();
    $CI->load->model('emails_model');
    $subjects = [
        'payment_due'     => 'العميل محتاج يدفع الباقي',
        'client_inactive' => 'عميل مابيصوّرش',
        'no_group'        => 'عميل مالوش جروب واتساب',
    ];

    foreach (array_unique($alert_ids) as $aid) {
        $a = $CI->db->query('SELECT * FROM ' . db_prefix() . 'dse_alerts WHERE id = ?', [(int) $aid])->row_array();
        if (!$a) {
            continue;
        }
        $opt   = $a['alert_key'] === 'payment_due' ? 'dse_payment_notify_staff_ids' : 'dse_notify_staff_ids';
        $ids   = array_filter(array_map('intval', preg_split('/[\s,;]+/', (string) get_option($opt))));
        $where = $ids ? 'staffid IN (' . implode(',', $ids) . ')' : 'admin = 1';
        $staff = $CI->db->query('SELECT staffid, email FROM ' . db_prefix() . 'staff WHERE active = 1 AND ' . $where)->result_array();
        $subject = ($a['priority'] === 'urgent' ? '⚠️ ' : '') . ($subjects[$a['alert_key']] ?? 'تنبيه من الاستوديو');
        $link    = $a['clientid'] ? 'dse_studio/client/' . $a['clientid'] : 'dse_studio/alerts';

        $notified = [];
        foreach ($staff as $s) {
            if (function_exists('add_notification') && add_notification([
                'description'     => 'dse_notification_alert',
                'touserid'        => $s['staffid'],
                'link'            => $link,
                'additional_data' => serialize([mb_substr($a['message'], 0, 150)]),
            ])) {
                $notified[] = $s['staffid'];
            }
            if (method_exists($CI->emails_model, 'send_simple_email')) {
                $CI->emails_model->send_simple_email($s['email'], $subject,
                    '<div dir="rtl">' . nl2br(html_escape($a['message'])) . '<br><br><a href="' . admin_url($link) . '">افتح في Perfex</a></div>');
            }
        }
        if ($notified && function_exists('pusher_trigger_notification')) {
            pusher_trigger_notification($notified);
        }
    }
}
