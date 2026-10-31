<?php

defined('BASEPATH') or exit('No direct script access allowed');

// جداول الـ module + إنشاء الناقص منها.
// بيتحمّل بـ require_once من dse_studio.php و install.php (مش من load->helper)

if (!function_exists('dse_studio_schema')) {
/**
 * جداول الـ module — نفس التعريف بيتطبق على MySQL (Perfex) و SQLite (الاختبارات)
 * {pk} = primary key أوتوماتيك
 */
function dse_studio_schema()
{
    return [
        'dse_clients' => [
            'cols' => [
                '`clientid` INT NOT NULL PRIMARY KEY',
                '`wa_phone` VARCHAR(20) NULL',
                '`wa_group_jid` VARCHAR(80) NULL',
                '`wa_group_link` VARCHAR(255) NULL',
                '`wa_link_error` VARCHAR(255) NULL', // اللينك اتحفظ بس لسه مااتربطش (رقم السيستم مش في الجروب) — بيتجرّب تاني لوحده
                // متابعة التجديد: '' لسه · contacted اتكلمنا · later بعدين · lost مش هيجدد
                '`renewal_status` VARCHAR(20) NULL',
                '`renewal_note` VARCHAR(255) NULL',
                '`renewal_updated_at` DATETIME NULL',
                '`updated_at` DATETIME NULL',
            ],
            'indexes' => [],
        ],
        'dse_studios' => [
            'cols' => [
                '{pk}',
                '`name` VARCHAR(100) NOT NULL',
                '`active` TINYINT NOT NULL DEFAULT 1',
            ],
            'indexes' => [],
        ],
        'dse_packages' => [
            'cols' => [
                '{pk}',
                '`clientid` INT NOT NULL',
                '`name` VARCHAR(150) NOT NULL',
                '`price` DECIMAL(12,2) NOT NULL DEFAULT 0',
                '`start_date` DATE NOT NULL',
                '`end_date` DATE NOT NULL',
                "`status` VARCHAR(20) NOT NULL DEFAULT 'pending'", // pending / active / expired / cancelled
                '`invoice_id` INT NULL',
                '`notes` TEXT NULL',
                '`created_by` INT NULL',
                '`created_at` DATETIME NOT NULL',
                '`activated_at` DATETIME NULL',
                '`historic` TINYINT NOT NULL DEFAULT 0', // باقة قديمة قبل السيستم (اتدفعت واتصرفت برا)
                '`grace_days` INT NULL',                 // فترة سماح خاصة بالباقة دي (NULL = الإعداد العام 15)
            ],
            'indexes' => ['idx_dse_packages_client' => '(`clientid`, `status`)'],
        ],
        'dse_package_items' => [
            'cols' => [
                '{pk}',
                '`package_id` INT NOT NULL',
                '`type` VARCHAR(30) NOT NULL', // reel / design / trainer_hours
                '`qty` DECIMAL(8,2) NOT NULL',
            ],
            'indexes' => ['uq_dse_package_items' => 'UNIQUE (`package_id`, `type`)'],
        ],
        'dse_sessions' => [
            'cols' => [
                '{pk}',
                '`clientid` INT NOT NULL',
                '`package_id` INT NOT NULL',
                '`studio_id` INT NOT NULL',
                '`photographer_id` INT NULL',
                "`type` VARCHAR(30) NOT NULL DEFAULT 'reel'",
                '`qty` DECIMAL(8,2) NOT NULL',
                '`start_at` DATETIME NOT NULL',
                '`end_at` DATETIME NOT NULL',
                '`outfits` INT NULL',
                "`status` VARCHAR(20) NOT NULL DEFAULT 'booked'", // booked / done / no_show / cancelled
                '`actual_qty` DECIMAL(8,2) NULL',
                '`late_cancel` TINYINT NOT NULL DEFAULT 0',
                '`notes` TEXT NULL',
                '`created_by` INT NULL',
                '`created_at` DATETIME NOT NULL',
                '`updated_at` DATETIME NULL',
            ],
            'indexes' => [
                'idx_dse_sessions_studio' => '(`studio_id`, `start_at`)',
                'idx_dse_sessions_client' => '(`clientid`, `status`)',
            ],
        ],
        'dse_usage' => [
            'cols' => [
                '{pk}',
                '`package_id` INT NOT NULL',
                '`type` VARCHAR(30) NOT NULL',
                '`qty` DECIMAL(8,2) NOT NULL',
                '`reason` VARCHAR(30) NOT NULL', // session / no_show / late_cancel / post / manual
                '`session_id` INT NULL',
                '`post_id` INT NULL',
                '`note` VARCHAR(255) NULL',
                '`staff_id` INT NULL',
                '`created_at` DATETIME NOT NULL',
            ],
            'indexes' => ['idx_dse_usage_package' => '(`package_id`, `type`)'],
        ],
        'dse_scripts' => [
            'cols' => [
                '{pk}',
                '`clientid` INT NOT NULL',
                '`title` VARCHAR(200) NOT NULL',
                '`body` TEXT NOT NULL',
                "`status` VARCHAR(20) NOT NULL DEFAULT 'draft'", // draft / sent / approved / changes
                '`revision` INT NOT NULL DEFAULT 0',
                '`created_by` INT NULL',
                '`created_at` DATETIME NOT NULL',
                '`sent_at` DATETIME NULL',
            ],
            'indexes' => ['idx_dse_scripts_client' => '(`clientid`)'],
        ],
        'dse_posts' => [
            'cols' => [
                '{pk}',
                '`clientid` INT NOT NULL',
                '`package_id` INT NULL',
                "`type` VARCHAR(30) NOT NULL DEFAULT 'reel'",
                '`title` VARCHAR(200) NULL',
                '`post_url` VARCHAR(500) NOT NULL',
                '`published_by` INT NULL',
                '`published_at` DATETIME NOT NULL',
            ],
            'indexes' => ['idx_dse_posts_client' => '(`clientid`)'],
        ],
        'dse_wa_outbox' => [
            'cols' => [
                '{pk}',
                '`clientid` INT NOT NULL',
                '`msg_key` VARCHAR(40) NOT NULL',
                '`dedupe_key` VARCHAR(150) NOT NULL',
                '`destination` VARCHAR(80) NOT NULL',
                '`body` TEXT NOT NULL',
                '`manual` TINYINT NOT NULL DEFAULT 0',
                "`status` VARCHAR(20) NOT NULL DEFAULT 'queued'", // queued / sending / sent / failed
                '`error` VARCHAR(500) NULL',
                '`created_by` INT NULL',
                '`created_at` DATETIME NOT NULL',
                '`sent_at` DATETIME NULL',
            ],
            'indexes' => [
                'uq_dse_wa_outbox_dedupe' => 'UNIQUE (`dedupe_key`)',
                'idx_dse_wa_outbox_status' => '(`status`, `id`)',
            ],
        ],
        // الباقة ممكن تتدفع على أكتر من فاتورة (أقساط / فرق الترقية)
        'dse_package_invoices' => [
            'cols' => [
                '{pk}',
                '`package_id` INT NOT NULL',
                '`invoice_id` INT NOT NULL',
                '`note` VARCHAR(150) NULL',
                '`created_by` INT NULL',
                '`created_at` DATETIME NOT NULL',
            ],
            'indexes' => ['uq_dse_package_invoices' => 'UNIQUE (`invoice_id`)', 'idx_dse_package_invoices_pkg' => '(`package_id`)'],
        ],
        // تاريخ الباقة: ترقية / تعديل / تمديد
        'dse_package_events' => [
            'cols' => [
                '{pk}',
                '`package_id` INT NOT NULL',
                '`event` VARCHAR(30) NOT NULL', // upgrade / downgrade / edit
                '`old_price` DECIMAL(12,2) NULL',
                '`new_price` DECIMAL(12,2) NULL',
                '`old_items` TEXT NULL',
                '`new_items` TEXT NULL',
                '`old_end_date` DATE NULL',
                '`new_end_date` DATE NULL',
                '`note` VARCHAR(255) NULL',
                '`staff_id` INT NULL',
                '`created_at` DATETIME NOT NULL',
            ],
            'indexes' => ['idx_dse_package_events_pkg' => '(`package_id`)'],
        ],
        'dse_publish_slots' => [ // مواعيد التنزيل الأسبوعية لكل عميل (الأحد ريل · التلات تصميم …)
            'cols' => [
                '{pk}',
                '`clientid` INT NOT NULL',
                '`weekday` TINYINT NOT NULL', // 0 الأحد … 6 السبت (زي date('w'))
                "`type` VARCHAR(20) NOT NULL DEFAULT 'reel'", // reel / design
            ],
            'indexes' => ['idx_dse_publish_slots_client' => '(`clientid`, `weekday`)'],
        ],
        'dse_content_plans' => [ // كالندر المحتوى: هدف شهري لكل عميل
            'cols' => [
                '{pk}',
                '`clientid` INT NOT NULL',
                '`month` CHAR(7) NOT NULL',            // 2026-11
                "`goal` VARCHAR(20) NOT NULL DEFAULT 'sales'", // sales / followers / engagement / awareness
                '`target` INT NULL',                    // الرقم المستهدف (30 حجز …)
                '`cta` VARCHAR(255) NULL',              // الـ CTA الأساسي للشهر
                '`cta_link` VARCHAR(255) NULL',         // لينك الحجز / الواتساب
                '`notes` TEXT NULL',
                '`result` INT NULL',                    // اللي اتحقق فعلًا آخر الشهر
                '`result_note` VARCHAR(255) NULL',
                '`created_by` INT NULL',
                '`created_at` DATETIME NOT NULL',
                '`updated_at` DATETIME NULL',
            ],
            'indexes' => ['uq_dse_content_plans' => 'UNIQUE (`clientid`, `month`)'],
        ],
        'dse_content_items' => [ // مواضيع الشهر (ريل / تصميم / يوتيوب / بودكاست) بتواريخها
            'cols' => [
                '{pk}',
                '`plan_id` INT NOT NULL',
                '`clientid` INT NOT NULL',
                '`publish_date` DATE NOT NULL',
                "`type` VARCHAR(20) NOT NULL DEFAULT 'reel'",
                "`pillar` VARCHAR(20) NOT NULL DEFAULT 'educational'",
                "`topic` VARCHAR(255) NOT NULL DEFAULT ''",
                '`hook` VARCHAR(255) NULL',
                '`cta` VARCHAR(255) NULL',
                '`script` TEXT NULL',
                "`status` VARCHAR(20) NOT NULL DEFAULT 'idea'",
                '`post_url` VARCHAR(500) NULL',
                '`notes` VARCHAR(255) NULL',
                '`created_by` INT NULL',
                '`created_at` DATETIME NOT NULL',
                '`updated_at` DATETIME NULL',
            ],
            'indexes' => ['idx_dse_content_items_plan' => '(`plan_id`, `publish_date`)'],
        ],
        'dse_alerts' => [
            'cols' => [
                '{pk}',
                '`clientid` INT NULL',
                '`alert_key` VARCHAR(40) NOT NULL',
                '`dedupe_key` VARCHAR(150) NOT NULL',
                '`message` TEXT NOT NULL',
                "`priority` VARCHAR(10) NOT NULL DEFAULT 'normal'",
                '`created_at` DATETIME NOT NULL',
                '`handled_at` DATETIME NULL',
                '`handled_by` INT NULL',
                '`resolution` VARCHAR(500) NULL',
            ],
            'indexes' => ['uq_dse_alerts_dedupe' => 'UNIQUE (`dedupe_key`)'],
        ],
    ];
}
}

if (!function_exists('dse_studio_create_sql')) {
/** بيرجّع أوامر الـ SQL لجدول واحد حسب الداتابيز */
function dse_studio_create_sql($table, $def, $prefix, $driver = 'mysql', $charset = 'utf8mb4')
{
    $pk   = $driver === 'sqlite' ? '`id` INTEGER PRIMARY KEY AUTOINCREMENT' : '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $cols = array_map(function ($c) use ($pk) { return $c === '{pk}' ? $pk : $c; }, $def['cols']);
    $sql  = ['CREATE TABLE `' . $prefix . $table . '` (' . implode(', ', $cols) . ')'
        . ($driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=' . $charset)];
    foreach ($def['indexes'] as $name => $spec) {
        $unique = strpos($spec, 'UNIQUE') === 0;
        $cols   = trim(str_replace('UNIQUE', '', $spec));
        $sql[]  = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX `' . $prefix . $name . '` ON `' . $prefix . $table . '` ' . $cols;
    }
    return $sql;
}
}

if (!function_exists('dse_studio_default_options')) {
/** القيم الافتراضية للإعدادات */
function dse_studio_default_options()
{
    return [
        'dse_wa_enabled'           => '0',
        'dse_evo_url'              => 'https://evolutoin-api-evolution-api.4tqpgc.easypanel.host',
        'dse_evo_key'              => '',
        'dse_evo_instance'         => '010',
        'dse_wa_daily_cap'         => '6',
        'dse_wa_team_numbers'      => '201050858834,201284816199,201224186293,201276530062',
        'dse_minutes_per_reel'     => '20',
        'dse_minutes_per_podcast'  => '120',   // حلقة البودكاست أقصاها ساعتين في الاستوديو
        'dse_reels_per_outfit'     => '3',
        'dse_late_cancel_hours'    => '24',
        'dse_penalty_units'        => '1',
        'dse_inactive_days'        => '21',
        'dse_notify_staff_ids'     => '5,6',   // إنجي عماد ومريم ناجي (أرقام الموظفين في Perfex)
        'dse_payment_notify_staff_ids' => '5,6',
        'dse_activation_paid_pct'  => '50',
        'dse_grace_days'           => '15',
        'dse_renewal_days_before'  => '7',
        'dse_content_notify_staff_ids' => '1,5,6', // تنبيهات التصوير والمحتوى والباقات: إبراهيم وإنجي عماد ومريم ناجي
        'dse_film_gap_days'        => '7',     // بقاله الأيام دي مصوّرش ← تنبيه
        'dse_default_posts_per_week' => '2',   // معدل النشر لو العميل لسه مانزلش حاجة     // العميل بيظهر في "مستنيين تجديد" قبل نهاية باقته بالأيام دي    // الباقة اللي فيها رصيد بتفضل شغالة 15 يوم بعد تاريخ نهايتها علشان نخلص الريلز
        'dse_feedback_url'         => 'https://docs.google.com/forms/d/e/1FAIpQLSdqw--gHY0XPOeyGOqTq4QvXeJBBO8arwWN669wtC3KnZmZtA/viewform?usp=pp_url&entry.878760930={client_code}&entry.220082110={post_id}',
        'dse_rules_text'           => "📌 *قواعد التعامل* — من فضلك اقراها كويس\n\n1) مواعيد التصوير بتتحجز مسبقًا.\n2) الإلغاء أو التأجيل قبل الميعاد بأقل من 24 ساعة بيتخصم من رصيد الباقة.\n3) عدم الحضور من غير إبلاغ بيتخصم من الرصيد.\n4) لو هتصور أكتر من ريل هات أكتر من لبس (لبس لكل 3 ريلز تقريبًا).\n5) السكربتات بتتبعت هنا للموافقة قبل التصوير.\n6) كل ريل ليه تعديلين بعد التسليم.\n7) الرصيد اللي ماتستخدمش في مدة الباقة مش بيترحّل للباقة الجاية.\n8) الباقة بتتفعل بعد الدفع.\n9) متبعتش باسوردات في الجروب — الأكسس بيكون بإضافتنا Partner.",
        'dse_last_daily_run'       => '',
    ];
}
}

if (!defined('DSE_STUDIO_SCHEMA_VERSION')) {
/** نسخة الجداول — زوّدها مع أي جدول جديد */
    define('DSE_STUDIO_SCHEMA_VERSION', '11');
}

if (!function_exists('dse_studio_ensure_schema')) {

/**
 * بيعمل أي جدول ناقص (لو Perfex ماشغّلش الـ migration بعد رفع نسخة جديدة).
 * بيتنادى مع كل صفحة في الـ module ومع الـ cron — بيقرا option واحد بس لو كله تمام.
 */
function dse_studio_ensure_schema()
{
    if (get_option('dse_schema_version') === DSE_STUDIO_SCHEMA_VERSION) {
        return;
    }
    $CI     = &get_instance();
    $driver = ($CI->db->dbdriver ?? 'mysqli') === 'sqlite' ? 'sqlite' : 'mysql';
    foreach (dse_studio_schema() as $table => $def) {
        if (!$CI->db->table_exists(db_prefix() . $table)) {
            foreach (dse_studio_create_sql($table, $def, db_prefix(), $driver, 'utf8mb4') as $sql) {
                $CI->db->query($sql);
            }
        }
    }
    dse_studio_add_missing_columns();
    // 1.0.1: الفاتورة اللي كانت على الباقة نفسها ← جدول فواتير الباقة
    $CI->db->query('INSERT INTO ' . db_prefix() . "dse_package_invoices (package_id, invoice_id, note, created_at)
        SELECT p.id, p.invoice_id, 'الفاتورة الأولى', p.created_at FROM " . db_prefix() . 'dse_packages p
         WHERE p.invoice_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM ' . db_prefix() . 'dse_package_invoices x WHERE x.invoice_id = p.invoice_id)');
    foreach (dse_studio_default_options() as $name => $value) {
        add_option($name, $value, 0); // مبيغيّرش اللي متسجل
    }
    update_option('dse_schema_version', DSE_STUDIO_SCHEMA_VERSION);
}
}

/**
 * أعمدة اتضافت بعد ما الجدول اتعمل ← ALTER. بيتنادى من ensure_schema، ومن الموديل نفسه قبل أي كود بيستخدم عمود جديد
 * (لو السيرفر شال نسخة قديمة من الملف ده في الكاش أو Perfex ماشغّلش التحديث، العمود يتضاف برضه بدل ما الصفحة تقع).
 * $only: [table => [cols]] علشان يشيك على أعمدة معينة بس.
 */
if (!function_exists('dse_studio_add_missing_columns')) {
    function dse_studio_add_missing_columns(array $only = [])
    {
        $CI    = &get_instance();
        $added = [];
        if (!is_object($CI) || !isset($CI->db) || !is_object($CI->db)) {
            return $added;
        }
        foreach (dse_studio_schema() as $table => $def) {
            if ($only && !isset($only[$table])) {
                continue;
            }
            if (!$CI->db->table_exists(db_prefix() . $table)) {
                continue;
            }
            foreach ($def['cols'] as $col) {
                if ($col === '{pk}' || stripos($col, 'PRIMARY KEY') !== false || !preg_match('/^`([a-z_]+)`\s+/i', $col, $m)) {
                    continue;
                }
                if ($only && !in_array($m[1], $only[$table], true)) {
                    continue;
                }
                if (!$CI->db->field_exists($m[1], db_prefix() . $table)) {
                    $CI->db->query('ALTER TABLE `' . db_prefix() . $table . '` ADD COLUMN ' . $col);
                    $added[] = $table . '.' . $m[1];
                }
            }
        }
        return $added;
    }
}
