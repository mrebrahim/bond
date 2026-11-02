<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.6.0: خدمات الباقة: إعلانات ممولة + برمجة (جدول dse_package_services) */
class Migration_Version_160 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
