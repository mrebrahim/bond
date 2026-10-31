<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.5.0: كالندر المحتوى (جداول dse_content_plans و dse_content_items) */
class Migration_Version_150 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
