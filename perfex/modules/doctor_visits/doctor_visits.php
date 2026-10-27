<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Doctor Visits Tracker — زيارات الدكاترة
Description: تسجيل زيارات العيادات من الموبايل، سلم المراحل، قيمة كل زيارة، والـ QR والواتساب. (المرحلة 1: الجداول والإعدادات)
Version: 1.0.0
Requires at least: 3.0.*
Author: Digital Solution
*/

define('DOCTOR_VISITS_MODULE_NAME', 'doctor_visits');

require_once __DIR__ . '/helpers/dv_setup.php';

register_activation_hook(DOCTOR_VISITS_MODULE_NAME, 'doctor_visits_activation_hook');
register_language_files(DOCTOR_VISITS_MODULE_NAME, [DOCTOR_VISITS_MODULE_NAME]);

hooks()->add_action('admin_init', 'doctor_visits_admin_init');

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
        // المرحلة 1: صفحة الإعدادات/الفحص بس — باقي الشاشات في المراحل الجاية
        $CI->app_menu->add_sidebar_children_item('doctor-visits', [
            'slug' => 'doctor-visits-settings', 'name' => _l('dv_menu_settings'),
            'href' => admin_url('doctor_visits/settings'), 'position' => 1,
        ]);
    }
}
