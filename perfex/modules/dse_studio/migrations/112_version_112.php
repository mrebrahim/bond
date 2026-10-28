<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.1.2: مسح العميل من الاستوديو + ربط جروب رقم السيستم مش عضو فيه */
class Migration_Version_112 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
