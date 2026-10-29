<?php

defined('BASEPATH') or exit('No direct script access allowed');

// أنواع الخدمات في الباقة
function dse_types()
{
    return [
        'reel'          => ['name' => 'ريلز', 'unit' => 'ريل', 'session' => true],
        'design'        => ['name' => 'تصميمات', 'unit' => 'تصميم', 'session' => false],
        // فيديو بالعرض لليوتيوب (مدربين ومدرسين) — بيتحسب بالساعات. المفتاح القديم trainer_hours علشان الباقات المتسجلة
        'trainer_hours' => ['name' => 'فيديو يوتيوب (بالعرض)', 'unit' => 'ساعة', 'session' => true],
        'podcast'       => ['name' => 'بودكاست', 'unit' => 'حلقة', 'session' => true], // بالعدد — الحلقة أقصاها ساعتين
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

/** أنواع التنزيل (مواعيد التنزيل + "نزل على السوشيال") ← نوع الباقة اللي بيتحسب عليه */
function dse_post_types()
{
    return [
        'reel'    => ['label' => '🎬 ريل', 'package' => 'reel'],
        'design'  => ['label' => '🎨 تصميم', 'package' => 'design'],
        'youtube' => ['label' => '📺 فيديو يوتيوب', 'package' => 'trainer_hours'],
        'podcast' => ['label' => '🎙 بودكاست', 'package' => 'podcast'],
    ];
}

/** أيام الأسبوع بالترتيب المصري (السبت الأول) — المفتاح زي date('w') */
function dse_weekdays()
{
    return [6 => 'السبت', 0 => 'الأحد', 1 => 'الاتنين', 2 => 'التلات', 3 => 'الأربع', 4 => 'الخميس', 5 => 'الجمعة'];
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

/**
 * رسايل الواتساب للعميل — النص بيتعدّل من "رسايل الواتساب" (option dse_wa_tpl_<key>)، ولو فاضي بيرجع للأصلي.
 * المتغيرات {client_name} … بتتملا هنا، والسطر اللي كل متغيراته فاضية بيتشال.
 */
function dse_wa_defaults()
{
    return [
        // ---- مانوال العميل الجديد (بزرار من الفريق) ----
        'welcome'          => "أهلًا بيكم في Digital Solution 👋\nده الجروب الخاص بـ *{client_name}* — هنا هتلاقوا مواعيد التصوير، والمحتوى قبل النشر، وأي تحديثات.\nأي استفسار اكتبوه هنا وهنرد عليكم في أسرع وقت 🙏",
        'rules'            => '{rules}',
        'booking_request'  => "📅 يلا نحجز أول ميعاد تصوير!\nابعتلنا اليوم والساعة اللي يناسبوك، وعدد الريلز اللي هتصورها.\nأول ما نحجز هيوصلك تأكيد هنا في الجروب.",
        'outfits_reminder' => '👕 تذكير مهم: لو هتصور أكتر من ريل، هات أكتر من لبس — لبس لكل 3 ريلز تقريبًا — علشان الفيديوهات متبانش شبه بعض.',
        'contract_request' => '📄 من فضلك ابعتلنا صورة العقد بعد التوقيع هنا في الجروب.',
        'access_request'   => "🔐 علشان نقدر ننشر ونتابع الأداء: من فضلك ضيفنا *Partner* على Meta Business Suite (فيسبوك وإنستجرام) — هنبعتلك الخطوات.\nلو عندك تيك توك أو يوتيوب أو Google Business قولنا نتفق على طريقة الأكسس.\n⚠️ متبعتش باسوردات في الجروب.",
        'renewal_request'  => "أهلًا {client_name} 👋\nباقة *{package_name}* {ended} {end_date}.\nحابين نكمل معاكم الشهر الجاي؟ نجهّز التجديد ونحجز أول ميعاد تصوير 🎬\nرد علينا هنا وإحنا نظبط كل حاجة 🙏",
        // ---- أوتوماتيك (3 بس) ----
        'script_sent'      => "سكربت جديد للمراجعة 📝\n*{title}*\n\n{body}\n\nرد بـ \"موافق\" أو ابعتلنا التعديلات",
        'session_booked'   => "{headline} ✅\n📅 {date} الساعة {time}\n📍 {studio}\n🎬 {count} · {outfits}",
        'published'        => "{headline}\n{post_url}\n\nرأيك في 10 ثواني: {feedback_url}\n\nفاضلك {balance} لحد {end_date}",
    ];
}

/** المتغيرات المتاحة لكل رسالة ← شرحها */
function dse_wa_vars($key)
{
    $map = [
        'welcome'        => ['client_name' => 'اسم العميل'],
        'renewal_request' => ['client_name' => 'اسم العميل', 'package_name' => 'اسم آخر باقة', 'ended' => '"خلصت يوم" أو "بتخلص يوم"', 'end_date' => 'تاريخ نهاية الباقة'],
        'rules'          => ['rules' => 'نص قواعد التعامل (من الإعدادات)', 'client_name' => 'اسم العميل'],
        'script_sent'    => ['title' => 'عنوان السكربت', 'body' => 'نص السكربت', 'client_name' => 'اسم العميل'],
        'session_booked' => ['headline' => '"تم حجز ميعاد التصوير" أو "ميعاد التصوير اتغيّر"', 'date' => 'اليوم والتاريخ', 'time' => 'الساعة',
            'studio' => 'الاستوديو', 'count' => 'العدد (3 ريل)', 'outfits' => '"هات 2 لبس" (فاضي لو مفيش)', 'client_name' => 'اسم العميل'],
        'published'      => ['headline' => '"الريل بتاعك نزل 🎬" / "التصميم بتاعك نزل 🎨" / "الفيديو بتاعك نزل على اليوتيوب 📺" / "حلقة البودكاست بتاعتك نزلت 🎙"', 'post_url' => 'لينك البوست', 'feedback_url' => 'لينك التقييم',
            'balance' => 'الرصيد الباقي', 'end_date' => 'آخر يوم في الباقة', 'client_name' => 'اسم العميل'],
    ];
    return $map[$key] ?? ['client_name' => 'اسم العميل'];
}

/** النص المحفوظ (لو اتعدّل) أو الأصلي */
function dse_wa_template($key)
{
    $d = dse_wa_defaults();
    $t = function_exists('get_option') ? (string) get_option('dse_wa_tpl_' . $key) : '';
    return trim($t) !== '' ? $t : ($d[$key] ?? null);
}

/** يملا المتغيرات · السطر اللي كل متغيراته فاضية بيتشال · " ·" في آخر السطر بتتشال · مسافات السطور بتتظبط */
function dse_wa_render($tpl, array $vars)
{
    $lines = [];
    foreach (preg_split('/\r?\n/', (string) $tpl) as $line) {
        preg_match_all('/\{([a-z_]+)\}/', $line, $m);
        if ($m[1] && !array_filter($m[1], function ($k) use ($vars) { return trim((string) ($vars[$k] ?? '')) !== ''; })) {
            continue;
        }
        $line    = preg_replace_callback('/\{([a-z_]+)\}/', function ($x) use ($vars) { return (string) ($vars[$x[1]] ?? ''); }, $line);
        $lines[] = preg_replace('/\s*·\s*$/u', '', $line);
    }
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
}

function dse_wa_text($key, array $p)
{
    $tpl = dse_wa_template($key);
    return $tpl === null ? null : dse_wa_render($tpl, dse_wa_values($key, $p));
}

/** قيم المتغيرات من بيانات الرسالة */
function dse_wa_values($key, array $p)
{
    $s = function ($k) use ($p) { return isset($p[$k]) ? (string) $p[$k] : ''; };
    $v = ['client_name' => $s('client_name'), 'rules' => $s('rules'), 'package_name' => $s('package_name'),
        'ended' => $s('end_date') === '' ? '' : ($s('end_date') < date('Y-m-d') ? 'خلصت يوم' : 'بتخلص يوم'), 'end_date' => $s('end_date') === '' ? '' : dse_day($s('end_date')), 'title' => $s('title'), 'body' => $s('body'), 'post_url' => $s('post_url'),
        'feedback_url' => $s('feedback_url'), 'balance' => $s('balance'), 'studio' => $s('studio')];
    if ($key === 'session_booked') {
        $v['headline'] = $s('rescheduled') ? 'ميعاد التصوير اتغيّر' : 'تم حجز ميعاد التصوير';
        $v['date']     = dse_day($s('start'));
        $v['time']     = dse_time($s('start'));
        $v['count']    = dse_num($s('qty')) . ' ' . dse_unit($s('type'));
        $v['outfits']  = (int) $s('outfits') > 0 ? 'هات ' . (int) $s('outfits') . ' لبس' : '';
    }
    if ($key === 'published') {
        $v['headline'] = ['design' => 'التصميم بتاعك نزل 🎨', 'youtube' => 'الفيديو بتاعك نزل على اليوتيوب 📺', 'podcast' => 'حلقة البودكاست بتاعتك نزلت 🎙'][$s('type')] ?? 'الريل بتاعك نزل 🎬';
        $v['end_date'] = $s('end_date') !== '' && $s('balance') !== '' ? dse_day($s('end_date')) : '';
    }
    return $v;
}

function dse_wa_sample_vars($key)
{
    return dse_wa_values($key, dse_wa_sample($key));
}

/** يشيك على النص قبل الحفظ — بيرمي رسالة عربي لو فيه متغير مش معروف */
function dse_wa_validate($key, $body)
{
    if (!isset(dse_wa_defaults()[$key])) {
        throw new Exception('رسالة مش معروفة');
    }
    if (trim((string) $body) === '') {
        throw new Exception('الرسالة فاضية — لو عايز الأصلي دوس "رجّع الأصلي"');
    }
    preg_match_all('/\{([^}]*)\}/u', $body, $m);
    $bad = array_diff(array_unique($m[1]), array_keys(dse_wa_vars($key)));
    if ($bad) {
        throw new Exception('متغير مش معروف: {' . implode('} {', $bad) . '} — المتاح: {' . implode('} {', array_keys(dse_wa_vars($key))) . '}');
    }
    return true;
}

/** بيانات تجربة للمعاينة في صفحة الرسايل */
function dse_wa_sample($key)
{
    $base = ['client_name' => 'عيادة د. أحمد', 'rules' => '1) المواعيد بتتأكد قبلها بيوم…', 'package_name' => 'Growth', 'end_date' => date('Y-m-d', strtotime('-3 days'))];
    $more = [
        'script_sent'    => ['title' => 'ريل: 3 أخطاء في تنضيف الأسنان', 'body' => 'أول خطأ: …'],
        'session_booked' => ['start' => date('Y-m-d 17:00:00', strtotime('+2 days')), 'qty' => 3, 'type' => 'reel', 'studio' => 'استوديو 1', 'outfits' => 1],
        'published'      => ['type' => 'reel', 'post_url' => 'https://instagram.com/p/xxxx', 'feedback_url' => 'https://ds.link/f/123', 'balance' => '5 ريل · 2 تصميم', 'end_date' => date('Y-m-d', strtotime('+20 days'))],
    ];
    return array_merge($base, $more[$key] ?? []);
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
        'renewal_request'  => 'طلب تجديد الاشتراك',
    ];
}
