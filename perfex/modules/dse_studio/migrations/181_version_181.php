<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.8.1: تحويل الإيرادات بالدولار واليورو لجنيه (options بس) */
class Migration_Version_181 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
