<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.1.1: مدّ السماح بالأسابيع + على الباقات القديمة */
class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
