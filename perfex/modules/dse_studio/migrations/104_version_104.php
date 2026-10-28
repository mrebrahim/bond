<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.0.4: فترة سماح 15 يوم للباقة اللي فيها رصيد + دفعات على الباقة اللي خلصت */
class Migration_Version_104 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
