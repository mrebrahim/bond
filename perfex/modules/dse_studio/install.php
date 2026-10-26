<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->helper(DSE_STUDIO_MODULE_NAME . '/dse_studio_schema');

foreach (dse_studio_schema() as $table => $def) {
    if (!$CI->db->table_exists(db_prefix() . $table)) {
        foreach (dse_studio_create_sql($table, $def, db_prefix(), 'mysql', 'utf8mb4') as $sql) {
            $CI->db->query($sql);
        }
    }
}

foreach (dse_studio_default_options() as $name => $value) {
    add_option($name, $value, 0);
}
dse_studio_ensure_schema();

// استوديو افتراضي علشان الحجز يشتغل من أول يوم
if ((int) $CI->db->count_all(db_prefix() . 'dse_studios') === 0) {
    $CI->db->insert(db_prefix() . 'dse_studios', ['name' => 'الاستوديو', 'active' => 1]);
}
