<?php

defined('BASEPATH') or exit('No direct script access allowed');

// الجداول + Lead Statuses + الإعدادات + القوالب + XP والـ Levels (PRD §6)
// آمن يتشغّل أكتر من مرة: مبيغيّرش أي حاجة متسجلة
$CI = &get_instance();
require_once __DIR__ . '/helpers/dv_setup.php';
dv_ensure_schema(true);
