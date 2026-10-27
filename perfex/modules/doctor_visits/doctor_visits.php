<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Doctor Visits Tracker — زيارات الدكاترة
Description: تسجيل زيارات العيادات من الموبايل في أقل من 30 ثانية، سلم المراحل، قيمة كل زيارة، الحافز والـ XP، الـ QR والواتساب، والتقارير.
Version: 1.1.0
Requires at least: 3.0.*
Author: Digital Solution
*/

define('DOCTOR_VISITS_MODULE_NAME', 'doctor_visits');

require_once __DIR__ . '/helpers/dv_setup.php';
require_once __DIR__ . '/helpers/dv_helper.php';

register_activation_hook(DOCTOR_VISITS_MODULE_NAME, 'doctor_visits_activation_hook');
register_language_files(DOCTOR_VISITS_MODULE_NAME, [DOCTOR_VISITS_MODULE_NAME]);

hooks()->add_action('admin_init', 'doctor_visits_admin_init');
hooks()->add_action('after_lead_lead_tabs', 'doctor_visits_lead_tab');
hooks()->add_action('after_lead_tabs_content', 'doctor_visits_lead_tab_content');
hooks()->add_action('after_cron_run', 'doctor_visits_cron');

function doctor_visits_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__ . '/install.php';
}

function doctor_visits_admin_init()
{
    $CI = &get_instance();

    register_staff_capabilities(DOCTOR_VISITS_MODULE_NAME, [
        'capabilities' => [
            'view_own'        => _l('dv_cap_view_own'),
            'view'            => _l('dv_cap_view'),
            'create'          => _l('dv_cap_create'),
            'edit'            => _l('dv_cap_edit'),
            'delete'          => _l('dv_cap_delete'),
            'manage_settings' => _l('dv_cap_manage_settings'),
        ],
    ], _l('doctor_visits'));

    if (staff_can('view', DOCTOR_VISITS_MODULE_NAME) || staff_can('view_own', DOCTOR_VISITS_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_menu_item('doctor-visits', [
            'name'     => _l('doctor_visits'),
            'href'     => admin_url('doctor_visits'),
            'position' => 7,
            'icon'     => 'fa fa-user-md',
        ]);
        $items = [
            ['doctor-visits-queue', 'dv_menu_queue', 'doctor_visits/queue', null],
            ['doctor-visits-log', 'dv_menu_log', 'doctor_visits/log', 'create'],
            ['doctor-visits-dashboard', 'dv_menu_dashboard', 'doctor_visits/dashboard', null],
            ['doctor-visits-buildings', 'dv_menu_buildings', 'doctor_visits/buildings', null],
            ['doctor-visits-reports', 'dv_menu_reports', 'doctor_visits/reports', null],
            ['doctor-visits-settings', 'dv_menu_settings', 'doctor_visits/settings', 'manage_settings'],
        ];
        foreach ($items as $i => $it) {
            if ($it[3] && !staff_can($it[3], DOCTOR_VISITS_MODULE_NAME)) {
                continue;
            }
            $CI->app_menu->add_sidebar_children_item('doctor-visits', [
                'slug' => $it[0], 'name' => _l($it[1]), 'href' => admin_url($it[2]), 'position' => $i + 1,
            ]);
        }
    }
}

/** تاب "زيارات" في صفحة الـ Lead (لو الدكتور متسجل في الموديول) */
function doctor_visits_lead_tab($lead = null)
{
    if (!$lead || empty($lead->id) || !doctor_visits_lead_is_doctor($lead->id)) {
        return;
    }
    echo '<li role="presentation"><a href="#tab_doctor_visits" aria-controls="tab_doctor_visits" role="tab" data-toggle="tab"><i class="fa fa-user-md"></i> ' . _l('dv_timeline') . '</a></li>';
}

function doctor_visits_lead_tab_content($lead = null)
{
    if (!$lead || empty($lead->id) || !doctor_visits_lead_is_doctor($lead->id)) {
        return;
    }
    $CI = &get_instance();
    $CI->load->model(DOCTOR_VISITS_MODULE_NAME . '/dv_model');
    try {
        $doc      = $CI->dv_model->doctor($lead->id, false);
        $timeline = $CI->dv_model->timeline($lead->id);
    } catch (Exception $e) {
        return;
    }
    echo '<div role="tabpanel" class="tab-pane" id="tab_doctor_visits">';
    $CI->load->view(DOCTOR_VISITS_MODULE_NAME . '/lead_tab', ['doc' => $doc, 'timeline' => $timeline]);
    echo '</div>';
}

function doctor_visits_lead_is_doctor($lead_id)
{
    $CI = &get_instance();
    return (bool) $CI->db->query('SELECT lead_id FROM `' . db_prefix() . 'dv_doctor_meta` WHERE lead_id = ?', [(int) $lead_id])->row_array();
}

/** مرة في اليوم: إشعار لكل مندوب بعدد متابعاته (المهام والقايمة بيسحبهم n8n من الـ API) */
function doctor_visits_cron()
{
    if (get_option('dv_last_daily') === date('Y-m-d')) {
        return;
    }
    update_option('dv_last_daily', date('Y-m-d'));
    $CI = &get_instance();
    $CI->load->model(DOCTOR_VISITS_MODULE_NAME . '/dv_model');
    try {
        $count = [];
        foreach ($CI->dv_model->followups_due() as $d) {
            $count[(int) $d['assigned_staff_id']] = ($count[(int) $d['assigned_staff_id']] ?? 0) + 1;
        }
        foreach ($count as $staff => $n) {
            if ($staff) {
                $CI->dv_model->notify_staff($staff, 'dv_notify_followups', [$n], 'doctor_visits/queue');
            }
        }
    } catch (Exception $e) {
        log_message('error', 'doctor_visits cron: ' . $e->getMessage());
    }
}

