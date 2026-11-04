<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.9.0: ميعاد نزول البوست (عمود scheduled) + مواعيد الأقساط (جدول dse_package_installments) */
class Migration_Version_190 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
