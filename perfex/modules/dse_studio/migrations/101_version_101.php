<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.0.1: الباقة على أكتر من فاتورة + تاريخ الترقيات */
class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $CI->load->helper('dse_studio/dse_studio_schema');
        dse_studio_ensure_schema();
    }
}
