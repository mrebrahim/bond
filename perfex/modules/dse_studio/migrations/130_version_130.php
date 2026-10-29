<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.3.0: مواعيد التنزيل الأسبوعية لكل عميل (جدول dse_publish_slots) */
class Migration_Version_130 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
