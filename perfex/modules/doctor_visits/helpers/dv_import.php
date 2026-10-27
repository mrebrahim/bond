<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * استيراد شيت الدكاترة (xlsx أو csv) — من غير مكتبات: الـ xlsx ملف zip فيه XML.
 * الأعمدة بتتعرف بالاسم مش بالترتيب، فأي شيت فيه نفس العناوين (بأي ترتيب) يشتغل.
 */

/** عناوين الأعمدة المعروفة ← اسم الخانة */
if (!function_exists('dv_import_columns')) {
    function dv_import_columns()
    {
        return [
            'row_no'          => ['#', 'م', 'مسلسل'],
            'building'        => ['البرج', 'العمارة', 'المبنى', 'البرج / العمارة'],
            'name'            => ['الدكتور / المكان', 'الدكتور', 'اسم الدكتور', 'الاسم', 'المكان'],
            'specialty'       => ['التخصص'],
            'floor'           => ['الدور'],
            'social_summary'  => ['السوشيال / الموقع', 'السوشيال', 'السوشيال ميديا'],
            'facebook_url'    => ['رابط فيسبوك', 'فيسبوك', 'لينك فيسبوك'],
            'followers'       => ['متابعين', 'المتابعين', 'عدد المتابعين'],
            'social_note'     => ['ملاحظة فيسبوك', 'ملاحظة السوشيال'],
            'instagram_url'   => ['انستجرام', 'رابط انستجرام', 'إنستجرام'],
            'gmaps_url'       => ['لوكيشن', 'جوجل ماب', 'الخريطة', 'رابط الخريطة'],
            'clinic_days'     => ['أيام العيادة', 'الأيام', 'ايام العيادة'],
            'clinic_hours'    => ['الساعات', 'المواعيد', 'مواعيد العيادة'],
            'other_branches'  => ['العيادات / الفروع التانية', 'الفروع التانية', 'الفروع', 'العيادات التانية'],
            'phone'           => ['التليفون', 'التليفونات', 'الموبايل', 'الرقم', 'رقم التليفون'],
            'clinic_whatsapp' => ['واتساب العيادة', 'واتساب'],
            'verification'    => ['حالة التحقق', 'التحقق', 'المصدر'],
            'priority_rank'   => ['الأولوية (1=أعلى)', 'الأولوية', 'الاولويه'],
            'notes'           => ['ملاحظات', 'ملاحظة', 'زاوية البيع'],
            'gatekeeper_name' => ['اسم الممرضة', 'الممرضة', 'الريسبشن'],
            'best_time'       => ['أنسب وقت', 'انسب وقت للزيارة', 'أنسب وقت للزيارة'],
            'visited'         => ['زرنا؟', 'زرناه؟', 'اتزار؟'],
            'outcome'         => ['النتيجة', 'نتيجة الزيارة'],
            'next_action'     => ['الخطوة الجاية'],
            'next_action_at'  => ['تاريخ المتابعة', 'ميعاد المتابعة'],
        ];
    }
}

/** توحيد النص العربي للمقارنة: من غير تشكيل ولا علامات، أ/إ/آ = ا، ى = ي، ة = ه */
if (!function_exists('dv_norm')) {
    function dv_norm($s)
    {
        $s = preg_replace('/\([^)]*\)/u', '', (string) $s);
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);
        $s = mb_strtolower($s);
        return preg_replace('/[^\p{L}\p{N}#]+/u', '', $s);
    }
}

/** الاسم من غير "د." / "د/" / "دكتور" — علشان نلاقي الدكتور لو اتكتب بشكل مختلف */
if (!function_exists('dv_norm_name')) {
    function dv_norm_name($s)
    {
        $s = preg_replace('/^\s*(د\s*[\.\/]|دكتور[ه]?|دكتورة|dr\.?)\s*/iu', '', trim((string) $s));
        return dv_norm($s);
    }
}

// ---------------------------------------------------------------------------
// قراية الملف
// ---------------------------------------------------------------------------

/** [['name' => اسم الشيت, 'rows' => [[خانة, …], …]], …] */
if (!function_exists('dv_read_sheets')) {
    function dv_read_sheets($path, $filename = '')
    {
        $ext = strtolower(pathinfo($filename ?: $path, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') {
            return [['name' => 'CSV', 'rows' => dv_read_csv($path)]];
        }
        if ($ext !== 'xlsx') {
            throw new Exception('ارفع ملف Excel بصيغة ‎.xlsx أو CSV (لو الملف ‎.xls قديم: افتحه واعمله Save As ‎.xlsx)');
        }
        return dv_read_xlsx($path);
    }
}

if (!function_exists('dv_read_csv')) {
    function dv_read_csv($path)
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (!mb_check_encoding($raw, 'UTF-8') && function_exists('iconv')) {
            $raw = (string) iconv('Windows-1256', 'UTF-8//IGNORE', $raw); // Excel العربي القديم
        }
        $first = strtok($raw, "\n");
        $sep   = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $fh    = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
            $rows[] = $r;
        }
        fclose($fh);
        return $rows;
    }
}

if (!function_exists('dv_read_xlsx')) {
    function dv_read_xlsx($path)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('السيرفر ناقصه إضافة zip في PHP — ارفع الشيت CSV بدل كده');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception('الملف مش Excel سليم — افتحه في Excel واعمله Save تاني');
        }
        $get = function ($name) use ($zip) {
            $st = $zip->statName($name);
            if (!$st) {
                return null;
            }
            if ($st['size'] > 60 * 1024 * 1024) {
                throw new Exception('الشيت كبير قوي');
            }
            return $zip->getFromName($name);
        };
        $dom = function ($xml) {
            $d = new DOMDocument();
            if (!$xml || !@$d->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new Exception('الملف مش Excel سليم — افتحه في Excel واعمله Save تاني');
            }
            return $d;
        };

        $strings = [];
        if (($xml = $get('xl/sharedStrings.xml')) !== null) {
            foreach ($dom($xml)->getElementsByTagName('si') as $si) {
                $t = '';
                foreach ($si->getElementsByTagName('t') as $node) {
                    if ($node->parentNode->nodeName !== 'rPh') { // النطق الياباني لو موجود
                        $t .= $node->textContent;
                    }
                }
                $strings[] = $t;
            }
        }

        $rels = [];
        if (($xml = $get('xl/_rels/workbook.xml.rels')) !== null) {
            foreach ($dom($xml)->getElementsByTagName('Relationship') as $r) {
                $target = ltrim($r->getAttribute('Target'), '/');
                $rels[$r->getAttribute('Id')] = strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
            }
        }

        $out = [];
        foreach ($dom($get('xl/workbook.xml'))->getElementsByTagName('sheet') as $i => $s) {
            $rid  = $s->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $file = $rels[$rid] ?? 'xl/worksheets/sheet' . ($i + 1) . '.xml';
            $xml  = $get($file);
            if ($xml === null) {
                continue;
            }
            $rows = [];
            foreach ($dom($xml)->getElementsByTagName('row') as $rowNode) {
                $r   = [];
                $pos = 0;
                foreach ($rowNode->getElementsByTagName('c') as $c) {
                    $ref = $c->getAttribute('r');
                    if ($ref !== '' && preg_match('/^([A-Z]+)/', $ref, $m)) {
                        $pos = 0;
                        foreach (str_split($m[1]) as $ch) {
                            $pos = $pos * 26 + (ord($ch) - 64);
                        }
                        $pos--;
                    }
                    $type = $c->getAttribute('t');
                    $v    = $c->getElementsByTagName('v')->item(0);
                    $v    = $v ? $v->textContent : null;
                    if ($type === 's') {
                        $val = $strings[(int) $v] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $val = '';
                        foreach ($c->getElementsByTagName('t') as $t) {
                            $val .= $t->textContent;
                        }
                    } elseif ($type === 'b') {
                        $val = $v === '1' ? 'TRUE' : 'FALSE';
                    } else {
                        $val = $v ?? '';
                        if (is_numeric($val) && strpos($val, 'E') === false && strpos($val, '.') !== false) {
                            $val = rtrim(rtrim(number_format((float) $val, 6, '.', ''), '0'), '.'); // 0.30000000000000004 ← 0.3
                        }
                    }
                    $r[$pos] = $val;
                    $pos++;
                }
                if ($r) {
                    $max = max(array_keys($r));
                    $n = (int) $rowNode->getAttribute('r');
                    $rows[$n > 0 ? $n - 1 : count($rows)] = array_replace(array_fill(0, $max + 1, ''), $r);
                }
            }
            ksort($rows);
            $out[] = ['name' => $s->getAttribute('name'), 'rows' => $rows]; // المفتاح = رقم السطر في Excel - 1
        }
        $zip->close();
        if (!$out) {
            throw new Exception('مفيش شيتات في الملف');
        }
        return $out;
    }
}

// ---------------------------------------------------------------------------
// تحويل الصفوف لدكاترة
// ---------------------------------------------------------------------------

/** قيم معناها "مش معروف" — الخانة تفضل فاضية علشان المندوب يملاها في الميدان */
if (!function_exists('dv_import_blank')) {
    function dv_import_blank($v)
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v));
        $n = dv_norm($v);
        if ($n === '' || in_array($n, ['none', 'null', 'na', 'nan'], true)) {
            return '';
        }
        if (strpos($n, 'غيرمعروف') === 0 || strpos($n, 'لميعثر') === 0) {
            return '';
        }
        return $v;
    }
}

/** تاريخ من الشيت: رقم Excel (46330) أو 2026-11-03 أو 3/11/2026 (يوم/شهر) — مع وقت اختياري */
if (!function_exists('dv_import_date')) {
    function dv_import_date($v)
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return gmdate('Y-m-d H:i:s', (int) round(((float) $v - 25569) * 86400));
        }
        $v = strtr($v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})(?:\s+(\d{1,2})(?::(\d{2}))?\s*(م|ص|pm|am)?)?#iu', $v, $m)) {
            $h = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 10;
            if (!empty($m[6]) && preg_match('/^(م|pm)$/iu', $m[6]) && $h < 12) {
                $h += 12;
            }
            if (checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                return sprintf('%04d-%02d-%02d %02d:%02d:00', $m[3], $m[2], $m[1], $h, (int) ($m[5] ?? 0));
            }
            return null;
        }
        $t = strtotime($v);
        if ($t === false) {
            return null;
        }
        return date(preg_match('/\d{1,2}:\d{2}/', $v) ? 'Y-m-d H:i:s' : 'Y-m-d 10:00:00', $t);
    }
}

/** النتيجة المكتوبة في الشيت ← كود النتيجة في الموديول (أو null) */
if (!function_exists('dv_import_outcome')) {
    function dv_import_outcome($v)
    {
        $n = dv_norm($v);
        if ($n === '') {
            return null;
        }
        $aliases = [
            'WON'        => ['won', 'اتقفلwon', 'اتقفل', 'تعاقد', 'اتعاقد', 'مضى', 'دفع'],
            'OFFER'      => ['offer', 'العرضاتبعت', 'عرض', 'عرضسعر', 'اتبعتلهعرض'],
            'MTG_HELD'   => ['mtgheld', 'الميعادحصل', 'الميتنجحصل', 'اتقابلنا', 'اتعملالميتنج'],
            'MTG_BOOKED' => ['mtgbooked', 'ميعاداتحجز', 'ميعاد', 'ميتنج', 'حجزميعاد', 'meeting'],
            'WA'         => ['wa', 'اذنواتساب', 'واتساب', 'اخدناالرقم', 'وافقعالواتساب'],
            'DOC'        => ['doc', 'الدكتور', 'قابلناالدكتور', 'قابلتالدكتور', 'اتكلمنامعالدكتور', 'دكتور'],
            'ENV'        => ['env', 'الظرفوصل', 'ظرف', 'سبناظرف', 'سبناورق', 'ورق'],
            'GK'         => ['gk', 'الممرضه', 'ممرضه', 'قابلناالممرضه', 'السكرتيره', 'الريسبشن'],
            'CLOSED'     => ['closed', 'مقفوله', 'مقفول', 'العيادهمقفوله'],
            'NOT_NOW'    => ['notnow', 'مشدلوقتي', 'بعدين', 'مشفاضي'],
            'LOST'       => ['lost', 'رفض', 'مشمهتم', 'خسرناه'],
        ];
        foreach ($aliases as $code => $list) {
            if (in_array($n, $list, true)) {
                return $code;
            }
        }
        foreach ($aliases as $code => $list) { // "قابلنا الدكتور وسبنا الظرف" ← أعلى حاجة مذكورة
            foreach ($list as $a) {
                if (mb_strlen($a) >= 4 && mb_strpos($n, $a) !== false) {
                    return $code;
                }
            }
        }
        return null;
    }
}

/** الأرقام: "0122 / 0112" ← [موبايلات مصرية متوحدة] + النص الأصلي */
if (!function_exists('dv_import_phones')) {
    function dv_import_phones($v)
    {
        $raw    = trim((string) $v);
        $mobile = [];
        foreach (preg_split('#\s*[/,؛;|\n]+\s*|\s+و\s+#u', $raw) as $p) {
            if (($m = dv_phone($p)) && !in_array($m, $mobile, true)) {
                $mobile[] = $m;
            }
        }
        return ['raw' => $raw, 'mobile' => $mobile];
    }
}

/**
 * يلاقي صف العناوين في كل شيت ويحوّل الصفوف لدكاترة.
 * بيرجع: rows (كل صف: الخانات + sheet + line + warnings) · columns (العمود ← الخانة) · ignored (عناوين ماتعرفتش)
 */
if (!function_exists('dv_import_parse')) {
    function dv_import_parse(array $sheets)
    {
        $known = [];
        foreach (dv_import_columns() as $field => $names) {
            foreach ($names as $n) {
                $known[dv_norm($n)] = $known[dv_norm($n)] ?? $field;
            }
        }
        $out = ['rows' => [], 'columns' => [], 'ignored' => [], 'sheets' => []];
        foreach ($sheets as $sheet) {
            $head = null;
            foreach ($sheet['rows'] as $i => $r) {
                if ($i > 15) {
                    break;
                }
                $map = [];
                foreach ($r as $col => $title) {
                    $f = $known[dv_norm($title)] ?? null;
                    if ($f && !in_array($f, $map, true)) {
                        $map[$col] = $f;
                    }
                }
                if (in_array('name', $map, true) && count($map) >= 3) {
                    $head = [$i, $map, $r];
                    break;
                }
            }
            if (!$head) {
                continue; // شيت ملخص أو فاضي
            }
            [$hi, $map, $titles] = $head;
            $out['sheets'][] = $sheet['name'];
            foreach ($titles as $col => $t) {
                if (trim((string) $t) === '') {
                    continue;
                }
                if (isset($map[$col])) {
                    $out['columns'][trim($t)] = $map[$col];
                } else {
                    $out['ignored'][] = trim($t);
                }
            }
            foreach ($sheet['rows'] as $i => $r) {
                if ($i <= $hi) {
                    continue;
                }
                $row = [];
                foreach ($map as $col => $field) {
                    $row[$field] = trim(preg_replace('/[ \t]+/u', ' ', (string) ($r[$col] ?? '')));
                }
                if (dv_import_blank($row['name'] ?? '') === '') {
                    continue; // سطر فاضي أو ملاحظة في آخر الشيت
                }
                $out['rows'][] = dv_import_clean($row) + ['sheet' => $sheet['name'], 'line' => $i + 1];
            }
        }
        if (!$out['sheets']) {
            throw new Exception('ملقتش صف عناوين فيه عمود "الدكتور" أو "الاسم" — أول صف في الشيت لازم يبقى العناوين');
        }
        if (count($out['rows']) > 3000) {
            throw new Exception('الشيت فيه أكتر من 3000 صف — قسّمه على كذا ملف');
        }
        return $out;
    }
}

/** تنضيف صف واحد: القيم المجهولة تتشال، الأرقام تتوحد، التواريخ تتفهم، والتحذيرات تتجمع */
if (!function_exists('dv_import_clean')) {
    function dv_import_clean(array $r)
    {
        $w = [];
        $c = [];
        foreach ($r as $k => $v) {
            $c[$k] = in_array($k, ['social_summary'], true) ? trim($v) : dv_import_blank($v);
        }
        $c['name'] = mb_substr($c['name'], 0, 191);
        if (($c['specialty'] ?? '') === '') {
            $c['specialty'] = 'غير معروف';
            $w[] = 'التخصص مش مكتوب';
        }
        $c['specialty'] = mb_substr($c['specialty'], 0, 100);
        $floor = $c['floor'] ?? '';
        if (in_array($floor, ['؟', '?', '-', '—', '–'], true)) {
            $floor = '';
        }
        $c['floor'] = mb_substr($floor, 0, 20);
        $c['followers'] = in_array($c['followers'] ?? '', ['-', '—', '–', '؟'], true) ? '' : mb_substr($c['followers'] ?? '', 0, 30);

        // اللينكات: الأول في الخانة، والباقي يفضل في ملاحظة السوشيال
        foreach (['facebook_url', 'instagram_url', 'gmaps_url'] as $k) {
            $urls = [];
            if (!empty($c[$k]) && preg_match_all('#https?://[^\s|,]+#i', $c[$k], $m)) {
                $urls = $m[0];
            }
            $c[$k] = $urls[0] ?? '';
            if (count($urls) > 1) {
                $c['social_note'] = trim(($c['social_note'] ?? '') . ' · لينكات تانية: ' . implode(' ', array_slice($urls, 1)), ' ·');
            }
        }

        $ph = dv_import_phones($c['phone'] ?? '');
        $c['phones'] = mb_substr($ph['raw'], 0, 191);
        $c['phone']  = $ph['mobile'][0] ?? '';
        if ($ph['raw'] !== '' && !$ph['mobile']) {
            $w[] = 'مفيش موبايل مصري في التليفون (أرضي؟) — اتحفظ في "كل الأرقام"';
        }
        if (!empty($c['clinic_whatsapp'])) {
            $c['clinic_whatsapp'] = dv_phone($c['clinic_whatsapp']) ?: '';
        }

        $p = $c['priority_rank'] ?? '';
        $c['priority_rank'] = preg_match('/([1-9])/', strtr($p, ['١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5']), $m) ? (int) $m[1] : null;

        $c['gatekeeper_name'] = ($c['gatekeeper_name'] ?? '') === '' ? '' : mb_substr(preg_split('/\s+/u', $c['gatekeeper_name'])[0], 0, 50);

        $c['outcome_code'] = dv_import_outcome($c['outcome'] ?? '');
        if (($c['outcome'] ?? '') !== '' && !$c['outcome_code']) {
            $w[] = 'النتيجة "' . $c['outcome'] . '" مش مفهومة — مااتسجلتش زيارة';
        }
        if ($c['outcome_code'] === 'LOST') {
            $c['outcome_code'] = null;
            $w[] = 'Lost مبيتسجلش من الإكسل (محتاج سبب وقاعدة الـ 3 زيارات) — سجّله من صفحة الدكتور';
        }
        $c['next_action_at'] = dv_import_date($c['next_action_at'] ?? '');
        if (($r['next_action_at'] ?? '') !== '' && !$c['next_action_at']) {
            $w[] = 'تاريخ المتابعة "' . $r['next_action_at'] . '" مش مفهوم — اكتبه 2026-11-03 أو 3/11/2026';
        }
        $visited = dv_norm($c['visited'] ?? '');
        $c['visited'] = in_array($visited, ['نعم', 'ايوه', 'اه', 'yes', 'y', 'true', '1', '✓', '✅'], true) ? 1 : 0;
        if ($c['visited'] && !$c['outcome_code']) {
            $w[] = 'مكتوب "زرناه" من غير نتيجة — مااتسجلتش زيارة';
        }
        foreach (['building', 'social_summary', 'social_note', 'clinic_days', 'clinic_hours', 'other_branches', 'verification', 'notes', 'best_time', 'next_action'] as $k) {
            $c[$k] = $c[$k] ?? '';
        }
        $c['clinic_days']  = mb_substr($c['clinic_days'], 0, 191);
        $c['clinic_hours'] = mb_substr($c['clinic_hours'], 0, 191);
        $c['best_time']    = mb_substr($c['best_time'], 0, 100);
        $c['next_action']  = mb_substr($c['next_action'], 0, 255);
        $c['social_summary'] = mb_substr($c['social_summary'], 0, 255);
        $c['verification'] = mb_substr($c['verification'], 0, 255);
        unset($c['row_no'], $c['outcome']);
        $c['warnings'] = $w;
        return $c;
    }
}
