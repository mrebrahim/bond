<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.0.6: الأعمدة الناقصة (renewal_status · historic) بتتضاف لو التحديث ماشتغلش */
class Migration_Version_106 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
