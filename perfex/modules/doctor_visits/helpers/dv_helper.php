<?php

defined('BASEPATH') or exit('No direct script access allowed');

// أسامي بالعربي للمراحل والنتايج والأسباب (النصوص نفسها في ملف اللغة)

if (!function_exists('dv_stage_label')) {
    function dv_stage_label($stage)
    {
        return $stage === null || $stage === '' ? '' : _l('dv_stage_' . (int) $stage);
    }
}

if (!function_exists('dv_stage_color')) {
    function dv_stage_color($stage, $lost = false)
    {
        if ($lost) {
            return '#dc2626';
        }
        $s = (int) $stage;
        return $s <= 1 ? '#94a3b8' : ($s <= 4 ? '#3b82f6' : ($s <= 7 ? '#8b5cf6' : ($s === 8 ? '#f97316' : '#16a34a')));
    }
}

if (!function_exists('dv_progress')) {
    function dv_progress($stage)
    {
        $st = dv_stages();
        return isset($st[(int) $stage]) ? $st[(int) $stage]['progress'] : 0;
    }
}

/** الأزرار اللي في فورم الزيارة بالترتيب (Grid 3×4) */
if (!function_exists('dv_outcome_buttons')) {
    function dv_outcome_buttons()
    {
        return ['CLOSED', 'GK', 'ENV', 'DOC', 'WA', 'MTG_BOOKED', 'MTG_HELD', 'OFFER', 'WON', 'NOT_NOW', 'LOST'];
    }
}

if (!function_exists('dv_outcome_label')) {
    function dv_outcome_label($o)
    {
        return _l('dv_outcome_' . strtolower($o));
    }
}

if (!function_exists('dv_lost_reason_label')) {
    function dv_lost_reason_label($r)
    {
        return $r ? _l('dv_lost_' . $r) : '';
    }
}

/** الخطوة الجاية حسب المرحلة (PRD §5.2) — مع الـ XP بتاعها */
if (!function_exists('dv_next_step')) {
    function dv_next_step($stage)
    {
        $map = [
            0 => ['dv_next_0', 'CLOSED'], 1 => ['dv_next_1', 'GK'], 2 => ['dv_next_2', 'ENV'], 3 => ['dv_next_3', 'DOC'],
            4 => ['dv_next_4', 'WA'], 5 => ['dv_next_5', 'MTG_BOOKED'], 6 => ['dv_next_6', 'MTG_HELD'], 7 => ['dv_next_7', 'OFFER'],
            8 => ['dv_next_8', 'WON'], 9 => ['dv_next_9', null],
        ];
        return $map[(int) $stage] ?? $map[0];
    }
}

/** الخطوات الفاضلة لحد الإغلاق بالأسامي القصيرة: "ميعاد ← عرض ← دفع" */
if (!function_exists('dv_steps_left')) {
    function dv_steps_left($stage)
    {
        $out = [];
        for ($s = (int) $stage + 1; $s <= 9; $s++) {
            $out[] = _l('dv_short_' . $s);
        }
        return $out;
    }
}

/** A/B/C من الـ Digital Score (0–12): الأقل حضور رقمي = فرصة أكبر */
if (!function_exists('dv_grade')) {
    function dv_grade($score)
    {
        $s = (int) $score;
        return $s <= 4 ? 'A' : ($s <= 8 ? 'B' : 'C');
    }
}

/** 01xxxxxxxxx ← 201xxxxxxxxx (بيقبل +20 و 0020 والمسافات والحروف المخفية) */
if (!function_exists('dv_phone')) {
    function dv_phone($p)
    {
        $d = preg_replace('/\D+/', '', (string) $p);
        if (strpos($d, '0020') === 0) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 11 && $d[0] === '0') {
            $d = '2' . $d;
        }
        if (strlen($d) === 10 && $d[0] === '1') {
            $d = '20' . $d;
        }
        return preg_match('/^20\d{10}$/', $d) ? $d : null;
    }
}

if (!function_exists('dv_day_name')) {
    function dv_day_name($ts)
    {
        return _l('dv_day_' . (int) date('w', $ts));
    }
}

if (!function_exists('dv_money')) {
    function dv_money($n)
    {
        return number_format((float) $n, 0, '.', ',') . ' ' . _l('dv_egp');
    }
}

if (!function_exists('dv_date')) {
    function dv_date($v, $with_time = false)
    {
        if (!$v) {
            return '';
        }
        $ts = is_numeric($v) ? (int) $v : strtotime($v);
        return dv_day_name($ts) . ' ' . date('d/m', $ts) . ($with_time ? ' ' . date('g:i', $ts) . (date('H', $ts) < 12 ? ' ' . _l('dv_am') : ' ' . _l('dv_pm')) : '');
    }
}

/** القالب بعد ملء المتغيرات + المتغيرات اللي لسه ناقصة */
if (!function_exists('dv_fill_template')) {
    function dv_fill_template($body, array $vars)
    {
        $missing = [];
        $text    = preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars, &$missing) {
            $v = isset($vars[$m[1]]) ? trim((string) $vars[$m[1]]) : '';
            if ($v === '') {
                $missing[] = $m[1];
                return $m[0];
            }
            return $v;
        }, (string) $body);
        return [$text, array_values(array_unique($missing))];
    }
}
