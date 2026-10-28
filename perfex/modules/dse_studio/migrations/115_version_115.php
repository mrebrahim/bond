<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.1.5: لينك الجروب بيتحفظ على طول ويتربط لوحده (عمود wa_link_error) */
class Migration_Version_115 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once __DIR__ . '/../helpers/dse_studio_setup.php';
        dse_studio_ensure_schema();
    }
}
