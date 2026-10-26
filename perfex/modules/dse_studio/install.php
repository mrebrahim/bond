<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
require_once __DIR__ . '/helpers/dse_studio_setup.php';

// الجداول والإعدادات (نفس اللي بيتشغّل لو نسخة جديدة اترفعت من غير migration)
update_option('dse_schema_version', '');
dse_studio_ensure_schema();

// استوديو افتراضي علشان الحجز يشتغل من أول يوم
if ((int) $CI->db->count_all(db_prefix() . 'dse_studios') === 0) {
    $CI->db->insert(db_prefix() . 'dse_studios', ['name' => 'الاستوديو', 'active' => 1]);
}
