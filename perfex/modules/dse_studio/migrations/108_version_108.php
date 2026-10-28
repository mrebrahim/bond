<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.0.8: الباقة القديمة رصيدها صفر (وعدد الريلز اختياري) */
class Migration_Version_108 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
