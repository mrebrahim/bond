<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * جداول موديول زيارات الدكاترة + البيانات الافتراضية (PRD §6 و §5.5 و F8).
 * بيتحمّل بـ require_once (مش load->helper) علشان يشتغل حتى لو السيرفر شايل نسخة قديمة في الكاش.
 * نفس التعريف بيتطبق على MySQL (Perfex) و SQLite (الاختبارات). {pk} = primary key أوتوماتيك.
 */

if (!defined('DV_SCHEMA_VERSION')) {
    define('DV_SCHEMA_VERSION', '1'); // زوّدها مع أي جدول أو عمود جديد
}

if (!function_exists('dv_schema')) {
    function dv_schema()
    {
        return [
            'dv_buildings' => [
                'cols' => [
                    '{pk}',
                    '`name` VARCHAR(191) NOT NULL',
                    '`cluster` VARCHAR(100) NULL',
                    '`address` TEXT NULL',
                    '`maps_url` VARCHAR(500) NULL',
                    '`clinics_estimate` INT NOT NULL DEFAULT 0',
                    '`notes` TEXT NULL',
                    '`created_by` INT NULL',
                    '`created_at` DATETIME NULL',
                ],
                'indexes' => ['idx_dv_buildings_cluster' => '(`cluster`)'],
            ],
            'dv_doctor_meta' => [
                'cols' => [
                    '`lead_id` INT NOT NULL PRIMARY KEY', // tblleads.id
                    '`building_id` INT NULL',
                    '`floor` VARCHAR(20) NULL',
                    '`specialty` VARCHAR(100) NULL',
                    '`clinic_hours` VARCHAR(191) NULL',
                    '`gatekeeper_name` VARCHAR(100) NULL',
                    '`best_time` VARCHAR(100) NULL',
                    '`digital_score` TINYINT NOT NULL DEFAULT 0', // 0..12
                    '`grade` CHAR(1) NULL',                       // A/B/C من الـ score
                    '`facebook_url` VARCHAR(500) NULL',
                    '`instagram_url` VARCHAR(500) NULL',
                    '`gmaps_url` VARCHAR(500) NULL',
                    '`clinic_whatsapp` VARCHAR(30) NULL',
                    '`wa_consent` TINYINT NOT NULL DEFAULT 0',
                    '`wa_consent_at` DATETIME NULL',
                    '`wa_consent_source` VARCHAR(50) NULL',      // in_person / qr_scan
                    '`qr_code` VARCHAR(12) NULL',                 // DV-7K2P
                    '`stage` TINYINT NOT NULL DEFAULT 0',         // 0..9
                    '`lost_reason` VARCHAR(50) NULL',
                    '`first_visit_at` DATETIME NULL',
                    '`won_at` DATETIME NULL',
                    '`next_action` VARCHAR(255) NULL',
                    '`next_action_at` DATETIME NULL',
                    '`assigned_staff_id` INT NULL',
                    '`created_at` DATETIME NULL',
                    '`updated_at` DATETIME NULL',
                ],
                'indexes' => [
                    'uq_dv_doctor_meta_qr'       => 'UNIQUE (`qr_code`)',
                    'idx_dv_doctor_meta_building' => '(`building_id`)',
                    'idx_dv_doctor_meta_staff'    => '(`assigned_staff_id`, `stage`)',
                    'idx_dv_doctor_meta_next'     => '(`next_action_at`)',
                ],
            ],
            'dv_visits' => [
                'cols' => [
                    '{pk}',
                    '`lead_id` INT NOT NULL',
                    '`staff_id` INT NOT NULL',
                    '`outcome` VARCHAR(20) NOT NULL', // CLOSED, GK, ENV, DOC, WA, MTG_BOOKED, MTG_HELD, OFFER, WON, LOST, NOT_NOW, WA_SENT, QR_SCAN
                    "`channel` VARCHAR(20) NOT NULL DEFAULT 'field'", // field | whatsapp | phone | system
                    '`note` TEXT NULL',
                    '`lost_reason` VARCHAR(50) NULL',
                    '`stage_before` TINYINT NULL',
                    '`stage_after` TINYINT NULL',
                    '`next_action` VARCHAR(255) NULL',
                    '`next_action_at` DATETIME NULL',
                    '`visited_at` DATETIME NOT NULL',
                    '`logged_at` DATETIME NOT NULL', // متأخرة لو بعد visited_at بأكتر من 24 ساعة
                ],
                'indexes' => [
                    'idx_dv_visits_lead'  => '(`lead_id`)',
                    'idx_dv_visits_staff' => '(`staff_id`, `visited_at`)',
                ],
            ],
            'dv_events' => [
                'cols' => [
                    '{pk}',
                    '`lead_id` INT NULL',
                    '`type` VARCHAR(30) NOT NULL', // qr_scan, milestone, opt_out
                    '`payload` LONGTEXT NULL',     // JSON (LONGTEXT علشان MariaDB القديمة)
                    '`created_at` DATETIME NOT NULL',
                ],
                'indexes' => ['idx_dv_events_lead' => '(`lead_id`, `type`)'],
            ],
            'dv_settings' => [
                // PRD: staff_id NULL = global — بس MySQL مش بيقبل NULL في الـ PRIMARY KEY، فالعام = 0
                'cols' => [
                    '`staff_id` INT NOT NULL DEFAULT 0',
                    '`key` VARCHAR(50) NOT NULL',
                    '`value` TEXT NULL',
                    'PRIMARY KEY (`staff_id`, `key`)',
                ],
                'indexes' => [],
            ],
            'dv_wa_templates' => [
                'cols' => [
                    '{pk}',
                    '`key` VARCHAR(50) NOT NULL',
                    '`name` VARCHAR(100) NOT NULL',
                    '`body` TEXT NOT NULL',
                    '`stage_min` TINYINT NULL',
                    '`stage_max` TINYINT NULL',
                    '`days_since_touch_min` INT NOT NULL DEFAULT 0',
                    '`sort` INT NOT NULL DEFAULT 0',
                    '`active` TINYINT NOT NULL DEFAULT 1',
                ],
                'indexes' => ['uq_dv_wa_templates_key' => 'UNIQUE (`key`)'],
            ],
            'dv_xp_ledger' => [
                // PRD: UNIQUE(staff_id, lead_id, action) — بس ده هيمنع متابعة واتساب تانية لنفس الدكتور.
                // بدله uniq_key بيتملى بس لأكشنات stage_* ("stage_DOC:{lead}") فالدكتور ياخد XP كل Stage مرة واحدة.
                'cols' => [
                    '{pk}',
                    '`staff_id` INT NOT NULL',
                    '`lead_id` INT NULL',
                    '`action` VARCHAR(30) NOT NULL',
                    '`xp` INT NOT NULL',
                    '`ref_visit_id` INT NULL',
                    '`uniq_key` VARCHAR(80) NULL',
                    '`created_at` DATETIME NOT NULL',
                ],
                'indexes' => [
                    'uq_dv_xp_ledger_once'  => 'UNIQUE (`uniq_key`)',
                    'idx_dv_xp_ledger_staff' => '(`staff_id`, `created_at`)',
                ],
            ],
        ];
    }
}

if (!function_exists('dv_create_sql')) {
    function dv_create_sql($table, $def, $prefix, $driver = 'mysql')
    {
        $pk   = $driver === 'sqlite' ? '`id` INTEGER PRIMARY KEY AUTOINCREMENT' : '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $cols = array_map(function ($c) use ($pk) { return $c === '{pk}' ? $pk : $c; }, $def['cols']);
        $sql  = ['CREATE TABLE `' . $prefix . $table . '` (' . implode(', ', $cols) . ')'
            . ($driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')];
        foreach ($def['indexes'] as $name => $spec) {
            $unique = strpos($spec, 'UNIQUE') === 0;
            $sql[]  = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX `' . $prefix . $name . '` ON `' . $prefix . $table . '` ' . trim(str_replace('UNIQUE', '', $spec));
        }
        return $sql;
    }
}

// ---------------------------------------------------------------------------
// سلم المراحل (PRD §3) — الاسم بيتعمل بيه Lead Status في Perfex
// ---------------------------------------------------------------------------
if (!function_exists('dv_stages')) {
    function dv_stages()
    {
        return [
            0 => ['key' => 'mapped',      'name' => 'DV 0 · Mapped',            'progress' => 0,   'color' => '#94a3b8'],
            1 => ['key' => 'visited',     'name' => 'DV 1 · Visited',           'progress' => 10,  'color' => '#94a3b8'],
            2 => ['key' => 'gatekeeper',  'name' => 'DV 2 · Gatekeeper Engaged', 'progress' => 20, 'color' => '#3b82f6'],
            3 => ['key' => 'envelope',    'name' => 'DV 3 · Envelope Delivered', 'progress' => 30, 'color' => '#3b82f6'],
            4 => ['key' => 'doctor',      'name' => 'DV 4 · Doctor Talked',     'progress' => 45,  'color' => '#3b82f6'],
            5 => ['key' => 'wa_perm',     'name' => 'DV 5 · WhatsApp Permission', 'progress' => 55, 'color' => '#8b5cf6'],
            6 => ['key' => 'mtg_booked',  'name' => 'DV 6 · Meeting Booked',    'progress' => 65,  'color' => '#8b5cf6'],
            7 => ['key' => 'mtg_held',    'name' => 'DV 7 · Meeting Held',      'progress' => 75,  'color' => '#8b5cf6'],
            8 => ['key' => 'offer',       'name' => 'DV 8 · Proposal / Sprint Offered', 'progress' => 85, 'color' => '#f97316'],
            9 => ['key' => 'won',         'name' => 'DV 9 · Won',               'progress' => 100, 'color' => '#16a34a'],
        ];
    }
}

if (!function_exists('dv_lost_status_name')) {
    function dv_lost_status_name()
    {
        return 'DV · Lost / Nurture';
    }
}

// Outcome → الـ Stage اللي بيوصلها (null = مبيحركش الـ Stage)
if (!function_exists('dv_outcomes')) {
    function dv_outcomes()
    {
        return [
            'CLOSED'     => 1, 'GK' => 2, 'ENV' => 3, 'DOC' => 4, 'WA' => 5,
            'MTG_BOOKED' => 6, 'MTG_HELD' => 7, 'OFFER' => 8, 'WON' => 9,
            'LOST'       => null, 'NOT_NOW' => null,
            'WA_SENT'    => null, 'QR_SCAN' => 5, // أوتوماتيك
        ];
    }
}

if (!function_exists('dv_lost_reasons')) {
    function dv_lost_reasons()
    {
        return ['existing_agency', 'no_budget', 'not_interested', 'timing', 'no_digital_priority', 'couldnt_access', 'wrong_lead', 'doesnt_want_to_appear'];
    }
}

// ---------------------------------------------------------------------------
// الإعدادات الافتراضية (PRD §6 و §5)
// ---------------------------------------------------------------------------
if (!function_exists('dv_default_settings')) {
    function dv_default_settings()
    {
        return [
            'monthly_target'           => '60000',
            'avg_deal_value'           => '7500',
            'default_visits_per_close' => '25',
            'default_touches_to_win'   => '7',
            'off_days'                 => '5',     // أرقام أيام الأسبوع (0 = الأحد … 5 = الجمعة)
            'min_daily_for_streak'     => '5',
            'cold_start_min_wins'      => '5',
            'api_token'                => '',      // بيتولد عشوائي وقت التفعيل
            'quest_hour'               => '14',
            'xp_rules'                 => json_encode([
                'visit_logged'  => 5,  'stage_GK' => 10, 'stage_ENV' => 20, 'stage_DOC' => 30,
                'WA_SENT'       => 15, 'WA_SENT_late' => 5, 'stage_WA' => 50, 'stage_QR_SCAN' => 60,
                'stage_MTG_BOOKED' => 80, 'stage_MTG_HELD' => 100, 'stage_OFFER' => 150,
                'WON'           => 500, 'WON_per_egp' => 50, // +1 XP لكل 50 ج
                'quest'         => 100, 'combo_multiplier' => 1.5, 'combo_min_leads' => 3,
                'streak_3'      => 50,  'streak_7' => 150,
            ]),
            'level_thresholds'         => json_encode([1 => 0, 2 => 500, 3 => 1500, 4 => 3500, 5 => 7000, 6 => 12000]),
            'level_names'              => json_encode([1 => 'مستكشف', 2 => 'فاتح أبواب', 3 => 'صياد مواعيد', 4 => 'مقفّل', 5 => 'ملك العمارة', 6 => 'أسطورة شبرا'], JSON_UNESCAPED_UNICODE),
            'level_rewards'            => json_encode([1 => '', 2 => 'عشا', 3 => 'يوم إجازة', 4 => 'بونص 1,000 ج', 5 => 'بونص 2,500 ج', 6 => '% عمولة أعلى'], JSON_UNESCAPED_UNICODE),
        ];
    }
}

// القوالب الافتراضية (PRD F8)
if (!function_exists('dv_default_templates')) {
    function dv_default_templates()
    {
        return [
            ['key' => 'after_visit', 'name' => 'بعد الزيارة', 'stage_min' => 3, 'stage_max' => 5, 'days_since_touch_min' => 0, 'sort' => 1,
             'body' => 'مساء الخير د/ {doctor_name}، أنا {rep_name} من Digital Solution، اتشرفت بزيارة العيادة النهارده. ده التحليل اللي سبته لحضرتك: {audit_link} — أهم فرصة: {key_insight}. يناسب حضرتك 10 دقايق يوم {day_a} ولا {day_b}؟'],
            ['key' => 'bot_demo', 'name' => 'ديمو البوت (يوم 2)', 'stage_min' => 3, 'stage_max' => 5, 'days_since_touch_min' => 1, 'sort' => 2,
             'body' => 'د/ {doctor_name}، ده فيديو 30 ثانية للرد الآلي وهو بيرد على سؤال "الكشف بكام؟" باسم عيادة حضرتك: {demo_link}'],
            ['key' => 'followup_48h', 'name' => 'متابعة بعد يومين', 'stage_min' => 3, 'stage_max' => 5, 'days_since_touch_min' => 2, 'sort' => 3,
             'body' => 'مساء الخير د/ {doctor_name}، بفكّر حضرتك بالتحليل. عندي اقتراح واحد نجربه من غير أي التزام. أعدّي على حضرتك {day_a} ولا {day_b}؟'],
            ['key' => 'meeting_confirm', 'name' => 'تأكيد الميعاد', 'stage_min' => 6, 'stage_max' => 6, 'days_since_touch_min' => 0, 'sort' => 4,
             'body' => 'تأكيد ميعادنا {meeting_time} في العيادة يا دكتور. هاخد من حضرتك 15 دقيقة بالظبط.'],
            ['key' => 'offer_followup', 'name' => 'متابعة العرض', 'stage_min' => 8, 'stage_max' => 8, 'days_since_touch_min' => 0, 'sort' => 5,
             'body' => 'د/ {doctor_name}، ده ملخص عرض الـ Sprint اللي اتكلمنا عليه: {offer_link}. أقدر أحجز جلسة التصوير يوم {day_a}.'],
            ['key' => 'breakup', 'name' => 'رسالة الوداع (14 يوم)', 'stage_min' => 1, 'stage_max' => 8, 'days_since_touch_min' => 14, 'sort' => 6,
             'body' => 'د/ {doctor_name}، مش عايز أضايق حضرتك، فهقفل المتابعة من عندي. لو حبيت تجرب البوت أسبوع ببلاش في أي وقت، ابعتلي كلمة "تمام".'],
        ];
    }
}

if (!function_exists('dv_random_token')) {
    function dv_random_token($len = 32)
    {
        return bin2hex(random_bytes((int) ($len / 2)));
    }
}

/**
 * بيعمل الجداول الناقصة + الـ Seeds (من غير ما يلمس أي حاجة موجودة).
 * بيتنادى من التفعيل ومن أول كل صفحة في الموديول — بيقرا option واحد بس لو كله تمام.
 */
if (!function_exists('dv_ensure_schema')) {
    function dv_ensure_schema($force = false)
    {
        if (!$force && get_option('dv_schema_version') === DV_SCHEMA_VERSION) {
            return false;
        }
        $CI     = &get_instance();
        $p      = db_prefix();
        $driver = ($CI->db->dbdriver ?? 'mysqli') === 'sqlite' ? 'sqlite' : 'mysql';
        $now    = date('Y-m-d H:i:s');

        foreach (dv_schema() as $table => $def) {
            if (!$CI->db->table_exists($p . $table)) {
                foreach (dv_create_sql($table, $def, $p, $driver) as $sql) {
                    $CI->db->query($sql);
                }
            }
        }

        // الإعدادات العامة (staff_id = 0) — مبيغيّرش اللي متسجل
        foreach (dv_default_settings() as $k => $v) {
            if (!$CI->db->query('SELECT 1 FROM `' . $p . 'dv_settings` WHERE staff_id = 0 AND `key` = ?', [$k])->row_array()) {
                if ($k === 'api_token') {
                    $v = dv_random_token();
                }
                $CI->db->insert($p . 'dv_settings', ['staff_id' => 0, 'key' => $k, 'value' => $v]);
            }
        }

        // القوالب
        foreach (dv_default_templates() as $t) {
            if (!$CI->db->query('SELECT 1 FROM `' . $p . 'dv_wa_templates` WHERE `key` = ?', [$t['key']])->row_array()) {
                $CI->db->insert($p . 'dv_wa_templates', $t + ['active' => 1]);
            }
        }

        // Lead Statuses: مرحلة لكل Stage + Lost/Nurture — والـ id بيتحفظ في الإعدادات (stage_status_0 … stage_status_9، lost_status)
        $order = (int) ($CI->db->query('SELECT MAX(statusorder) AS m FROM `' . $p . 'leads_status`')->row_array()['m'] ?? 0);
        $want  = [];
        foreach (dv_stages() as $i => $s) {
            $want['stage_status_' . $i] = [$s['name'], $s['color']];
        }
        $want['lost_status'] = [dv_lost_status_name(), '#dc2626'];
        foreach ($want as $setting => $info) {
            $row = $CI->db->query('SELECT id FROM `' . $p . 'leads_status` WHERE name = ?', [$info[0]])->row_array();
            if (!$row) {
                $CI->db->insert($p . 'leads_status', ['name' => $info[0], 'statusorder' => ++$order, 'color' => $info[1], 'isdefault' => 0]);
                $id = (int) $CI->db->insert_id();
            } else {
                $id = (int) $row['id'];
            }
            $exists = $CI->db->query('SELECT 1 FROM `' . $p . 'dv_settings` WHERE staff_id = 0 AND `key` = ?', [$setting])->row_array();
            if ($exists) {
                $CI->db->query('UPDATE `' . $p . 'dv_settings` SET value = ? WHERE staff_id = 0 AND `key` = ?', [(string) $id, $setting]);
            } else {
                $CI->db->insert($p . 'dv_settings', ['staff_id' => 0, 'key' => $setting, 'value' => (string) $id]);
            }
        }

        update_option('dv_schema_version', DV_SCHEMA_VERSION);
        update_option('dv_installed_at', get_option('dv_installed_at') ?: $now);
        return true;
    }
}

if (!function_exists('dv_setting')) {
    /** قيمة إعداد: الخاص بالموظف لو موجود، وإلا العام */
    function dv_setting($key, $staff_id = 0, $default = null)
    {
        $CI  = &get_instance();
        $row = $CI->db->query('SELECT value FROM `' . db_prefix() . 'dv_settings` WHERE `key` = ? AND staff_id IN (0, ?) ORDER BY staff_id DESC LIMIT 1', [$key, (int) $staff_id])->row_array();
        return $row ? $row['value'] : $default;
    }
}
