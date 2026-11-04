<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.9.6: مواعيد الأقساط للباقات القديمة + تصنيف عيادات ومستشفيات (مفيش جداول جديدة) */
class Migration_Version_196 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
