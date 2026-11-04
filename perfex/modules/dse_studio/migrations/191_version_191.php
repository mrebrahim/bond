<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.9.1: أسباب خسارة العملاء (جدول dse_churn) */
class Migration_Version_191 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
