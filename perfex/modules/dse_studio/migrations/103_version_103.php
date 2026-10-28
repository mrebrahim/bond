<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.0.3: دفعات من غير حد على الباقة + تجديد الاشتراك (مفيش جداول جديدة) */
class Migration_Version_103 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
