<?php

defined('BASEPATH') or exit('No direct script access allowed');

// أنواع الخدمات في الباقة
function dse_types()
{
    return [
        'reel'          => ['name' => 'ريلز', 'unit' => 'ريل', 'session' => true],
        'design'        => ['name' => 'تصميمات', 'unit' => 'تصميم', 'session' => false],
        'trainer_hours' => ['name' => 'ساعات مدرب', 'unit' => 'ساعة', 'session' => true],
    ];
}

function dse_type_name($type)
{
    $t = dse_types();
    return isset($t[$type]) ? $t[$type]['name'] : $type;
}

function dse_unit($type)
{
    $t = dse_types();
    return isset($t[$type]) ? $t[$type]['unit'] : $type;
}

function dse_session_statuses()
{
    return ['booked' => 'محجوزة', 'done' => 'اتصورت', 'no_show' => 'مجاش', 'cancelled' => 'اتلغت'];
}

function dse_package_statuses()
{
    return ['pending' => 'مستنية الدفع', 'active' => 'شغالة', 'expired' => 'خلصت', 'cancelled' => 'اتلغت'];
}

function dse_num($n)
{
    $n = (float) $n;
    return rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
}

function dse_money($n)
{
    return number_format((float) $n, 0, '.', ',') . ' ج.م';
}

// التواريخ بالعربي (بتوقيت Perfex — لازم يبقى Africa/Cairo من Setup → Settings → Localization)
function dse_day($v)
{
    if (!$v) {
        return '';
    }
    $ts   = is_numeric($v) ? (int) $v : strtotime($v);
    $days = ['الأحد', 'الاتنين', 'التلات', 'الأربع', 'الخميس', 'الجمعة', 'السبت'];
    return $days[(int) date('w', $ts)] . ' ' . date('d/m', $ts);
}

function dse_time($v)
{
    if (!$v) {
        return '';
    }
    $ts = is_numeric($v) ? (int) $v : strtotime($v);
    return date('g:i', $ts) . (date('H', $ts) < 12 ? ' ص' : ' م');
}

function dse_client_code($clientid)
{
    return 'C' . str_pad((string) $clientid, 3, '0', STR_PAD_LEFT);
}

/** رقم الواتساب بصيغة 20xxxxxxxxxx — بيقبل 01xxxxxxxxx أو +20 أو 0020 */
function dse_normalize_phone($p)
{
    $d = preg_replace('/\D+/', '', (string) $p);
    if (strpos($d, '0020') === 0) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11 && $d[0] === '0') {
        $d = '2' . $d;
    }
    return preg_match('/^20\d{10}$/', $d) ? $d : null;
}

function dse_phone_list($csv)
{
    $out = [];
    foreach (preg_split('/[\s,;]+/', (string) $csv) as $p) {
        $n = dse_normalize_phone($p);
        if ($n) {
            $out[$n] = $n;
        }
    }
    return array_values($out);
}

/** نصوص رسايل الواتساب للعميل */
function dse_wa_text($key, array $p)
{
    $s = function ($k) use ($p) { return isset($p[$k]) ? (string) $p[$k] : ''; };
    switch ($key) {
        // ---- مانوال العميل الجديد (بزرار من الفريق) ----
        case 'welcome':
            return "أهلًا بيكم في Digital Solution 👋\nده الجروب الخاص بـ *" . $s('client_name') . "* — هنا هتلاقوا مواعيد التصوير، والمحتوى قبل النشر، وأي تحديثات.\nأي استفسار اكتبوه هنا وهنرد عليكم في أسرع وقت 🙏";
        case 'rules':
            return $s('rules');
        case 'booking_request':
            return "📅 يلا نحجز أول ميعاد تصوير!\nابعتلنا اليوم والساعة اللي يناسبوك، وعدد الريلز اللي هتصورها.\nأول ما نحجز هيوصلك تأكيد هنا في الجروب.";
        case 'outfits_reminder':
            return '👕 تذكير مهم: لو هتصور أكتر من ريل، هات أكتر من لبس — لبس لكل 3 ريلز تقريبًا — علشان الفيديوهات متبانش شبه بعض.';
        case 'contract_request':
            return '📄 من فضلك ابعتلنا صورة العقد بعد التوقيع هنا في الجروب.';
        case 'access_request':
            return "🔐 علشان نقدر ننشر ونتابع الأداء: من فضلك ضيفنا *Partner* على Meta Business Suite (فيسبوك وإنستجرام) — هنبعتلك الخطوات.\nلو عندك تيك توك أو يوتيوب أو Google Business قولنا نتفق على طريقة الأكسس.\n⚠️ متبعتش باسوردات في الجروب.";

        // ---- أوتوماتيك (3 بس) ----
        case 'script_sent':
            return "سكربت جديد للمراجعة 📝\n*" . $s('title') . "*\n\n" . $s('body') . "\n\nرد بـ \"موافق\" أو ابعتلنا التعديلات";
        case 'session_booked':
            return ($s('rescheduled') ? 'ميعاد التصوير اتغيّر' : 'تم حجز ميعاد التصوير') . " ✅\n📅 " . dse_day($s('start')) . ' الساعة ' . dse_time($s('start'))
                . ($s('studio') !== '' ? "\n📍 " . $s('studio') : '')
                . "\n🎬 " . dse_num($s('qty')) . ' ' . dse_unit($s('type'))
                . ((int) $s('outfits') > 0 ? ' · هات ' . (int) $s('outfits') . ' لبس' : '');
        case 'published':
            return ($s('type') === 'design' ? 'التصميم بتاعك نزل 🎨' : 'الريل بتاعك نزل 🎬') . "\n" . $s('post_url')
                . ($s('feedback_url') !== '' ? "\n\nرأيك في 10 ثواني: " . $s('feedback_url') : '')
                . ($s('balance') !== '' ? "\n\nفاضلك " . $s('balance') . ($s('end_date') !== '' ? ' لحد ' . dse_day($s('end_date')) : '') : '');
    }
    return null;
}

function dse_wa_labels()
{
    return dse_manual_keys() + ['script_sent' => 'السكربت', 'session_booked' => 'تم الحجز', 'published' => 'البوست نزل'];
}

function dse_manual_keys()
{
    return [
        'welcome'          => 'رسالة الترحيب',
        'rules'            => 'قواعد التعامل',
        'booking_request'  => 'طلب حجز أول ميعاد',
        'outfits_reminder' => 'تنبيه اللبسات',
        'contract_request' => 'طلب صورة العقد',
        'access_request'   => 'طلب الأكسس على السوشيال',
    ];
}
