<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** 1.0.1: الباقة على أكتر من فاتورة + تاريخ الترقيات */
class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $CI->load->helper('dse_studio/dse_studio_schema');
        foreach (dse_studio_schema() as $table => $def) {
            if (!$CI->db->table_exists(db_prefix() . $table)) {
                foreach (dse_studio_create_sql($table, $def, db_prefix(), 'mysql', 'utf8mb4') as $sql) {
                    $CI->db->query($sql);
                }
            }
        }
        // الفاتورة القديمة اللي كانت على الباقة نفسها ← جدول الفواتير
        $CI->db->query('INSERT INTO ' . db_prefix() . 'dse_package_invoices (package_id, invoice_id, note, created_at)
            SELECT p.id, p.invoice_id, \'الفاتورة الأولى\', p.created_at FROM ' . db_prefix() . 'dse_packages p
             WHERE p.invoice_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM ' . db_prefix() . 'dse_package_invoices x WHERE x.invoice_id = p.invoice_id)');
        add_option('dse_activation_paid_pct', '50', 0);
        add_option('dse_payment_notify_staff_ids', '5,6', 0);
    }
}
