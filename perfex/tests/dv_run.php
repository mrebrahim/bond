<?php
// اختبارات المرحلة 1 لموديول doctor_visits — تفعيل على داتابيز شبه Perfex (SQLite) من غير Perfex
// php perfex/tests/dv_run.php

define('BASEPATH', __DIR__);
$mod = __DIR__ . '/../modules/doctor_visits';
set_error_handler(function ($n, $s, $f, $l) { throw new ErrorException("$s in " . basename($f) . ":$l"); });

$OPT = [];
function db_prefix() { return 'tbl'; }
function get_option($k) { global $OPT; return $OPT[$k] ?? ''; }
function add_option($k, $v, $a = 1) { global $OPT; if (!isset($OPT[$k])) { $OPT[$k] = $v; } }
function update_option($k, $v) { global $OPT; $OPT[$k] = $v; }
$HOOKS = [];
function register_activation_hook($m, $f) { global $HOOKS; $HOOKS['activate'] = $f; }
function register_language_files() {}
function hooks() { return new class { function add_action($h, $f) { $GLOBALS['HOOKS'][$h] = $f; } }; }

class R { private $r; function __construct($r) { $this->r = $r; } function result_array() { return $this->r; } function row_array() { return $this->r[0] ?? null; } }
class DB
{
    public $pdo; public $dbdriver = 'sqlite';
    function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    function query($sql, $b = []) { $st = $this->pdo->prepare(str_replace('`', '"', $sql)); $st->execute(array_values($b)); return new R($st->columnCount() ? $st->fetchAll(PDO::FETCH_ASSOC) : []); }
    function table_exists($t) { return (bool) $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name=" . $this->pdo->quote($t))->fetch(); }
    function count_all($t) { return (int) $this->pdo->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn(); }
    function insert($t, $d) { $this->query("INSERT INTO $t (" . implode(',', array_map(function ($k) { return '"' . $k . '"'; }, array_keys($d))) . ') VALUES (' . implode(',', array_fill(0, count($d), '?')) . ')', array_values($d)); }
    function insert_id() { return (int) $this->pdo->lastInsertId(); }
    function update($t, $d, $w) { $this->query("UPDATE $t SET " . implode(',', array_map(function ($k) { return '"' . $k . '" = ?'; }, array_keys($d))) . ' WHERE ' . implode(' AND ', array_map(function ($k) { return '"' . $k . '" = ?'; }, array_keys($w))), array_merge(array_values($d), array_values($w))); }
    function field_exists($f, $t) { foreach ($this->pdo->query("PRAGMA table_info(\"$t\")")->fetchAll() as $c) { if ($c['name'] === $f) return true; } return false; }
    function platform() { return 'sqlite'; } function version() { return '3'; }
}
$CI = new class { public $db; public $load; public $dv_model; };
$CI->db = new DB();
function &get_instance() { global $CI; return $CI; }

// جداول Perfex اللي بنلمسها: leads_status فيها حالات موجودة قبل كده
$CI->db->pdo->exec('CREATE TABLE tblleads_status (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, statusorder INT, color TEXT, isdefault INT)');
$CI->db->pdo->exec("INSERT INTO tblleads_status (name, statusorder, color, isdefault) VALUES ('Customer', 1000, '#7cb342', 1), ('New', 1, '#28B8DA', 0)");
$CI->db->pdo->exec('CREATE TABLE tblleads_sources (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
$CI->db->pdo->exec('CREATE TABLE tblleads (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, title TEXT, description TEXT, phonenumber TEXT, status INT, source INT, assigned INT, dateadded TEXT, addedfrom INT, lastcontact TEXT, hash TEXT, last_status_change TEXT, lost INT DEFAULT 0)');
$CI->db->pdo->exec('CREATE TABLE tblreminders (id INTEGER PRIMARY KEY AUTOINCREMENT, description TEXT, date TEXT, isnotified INT, rel_id INT, staff INT, rel_type TEXT, notify_by_email INT, creator INT)');
$CI->db->pdo->exec('CREATE TABLE tblstaff (staffid INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, active INT, admin INT)');
$CI->db->pdo->exec("INSERT INTO tblstaff VALUES (1, 'Ibrahim', 'Khalil', 'i@x.com', 1, 1), (7, 'Andrew', 'Isaac', 'a@x.com', 1, 0), (8, 'Mina', 'Magdy', 'm@x.com', 1, 0)");
$NOTIF = [];
function add_notification($d) { global $NOTIF; $NOTIF[] = $d; return true; }
$ME = 7;
function get_staff_user_id() { global $ME; return $ME; }
$CAN_ALL = false;
function staff_can($c, $f) { global $CAN_ALL; return $c === 'view' ? $CAN_ALL : true; }
function is_admin() { global $CAN_ALL; return $CAN_ALL; }
function _l($k, $a = null) { static $lang; if (!$lang) { $lang = []; include __DIR__ . '/../modules/doctor_visits/language/arabic/doctor_visits_lang.php'; } return $lang[$k] ?? $k; }
class App_Model { public $db; function __construct() { global $CI; $this->db = $CI->db; } }

$passed = 0; $failed = 0;
function test($n, $f) { global $passed, $failed; try { $f(); $passed++; echo "  ✔ $n\n"; } catch (Throwable $e) { $failed++; echo "  ✘ $n\n      " . $e->getMessage() . "\n"; } }
function ok($c, $m = 'assertion failed') { if (!$c) { throw new Exception($m); } }
function q($s, $b = []) { global $CI; return $CI->db->query($s, $b)->result_array(); }

echo "التفعيل:\n";
require $mod . '/doctor_visits.php';
test('ملف الموديول اتحمّل: activation hook + admin_init', function () {
    global $HOOKS;
    ok(isset($HOOKS['activate']) && $HOOKS['activate'] === 'doctor_visits_activation_hook' && isset($HOOKS['admin_init']));
});
test('التفعيل بيعمل الـ 7 جداول', function () {
    global $HOOKS, $CI;
    call_user_func($HOOKS['activate']);
    foreach (['dv_buildings', 'dv_doctor_meta', 'dv_visits', 'dv_events', 'dv_settings', 'dv_wa_templates', 'dv_xp_ledger'] as $t) {
        ok($CI->db->table_exists('tbl' . $t), $t);
    }
    ok(get_option('dv_schema_version') === DV_SCHEMA_VERSION);
});
test('Lead Statuses: 10 مراحل + Lost/Nurture، ومتربطة في الإعدادات، والقديمة زي ما هي', function () {
    $rows = q("SELECT * FROM tblleads_status WHERE name LIKE 'DV%' ORDER BY statusorder");
    ok(count($rows) === 11, count($rows));
    ok($rows[0]['name'] === 'DV 0 · Mapped' && $rows[9]['name'] === 'DV 9 · Won' && $rows[10]['name'] === 'DV · Lost / Nurture', json_encode(array_column($rows, 'name')));
    ok((int) $rows[0]['statusorder'] === 1001, 'بعد آخر حالة موجودة');
    ok(dv_setting('stage_status_0') == $rows[0]['id'] && dv_setting('stage_status_9') == $rows[9]['id'] && dv_setting('lost_status') == $rows[10]['id']);
    ok(count(q("SELECT id FROM tblleads_status WHERE name IN ('Customer','New')")) === 2);
});
test('الإعدادات الافتراضية + api_token عشوائي + XP والـ Levels', function () {
    ok(dv_setting('monthly_target') === '60000' && dv_setting('avg_deal_value') === '7500' && dv_setting('default_visits_per_close') === '25');
    ok(dv_setting('default_touches_to_win') === '7' && dv_setting('off_days') === '5' && dv_setting('min_daily_for_streak') === '5');
    ok(preg_match('/^[a-f0-9]{32}$/', dv_setting('api_token')) === 1, dv_setting('api_token'));
    $xp = json_decode(dv_setting('xp_rules'), true);
    ok($xp['stage_GK'] === 10 && $xp['stage_MTG_HELD'] === 100 && $xp['WON'] === 500 && $xp['combo_multiplier'] == 1.5, json_encode($xp));
    $lv = json_decode(dv_setting('level_thresholds'), true);
    ok($lv['2'] === 500 && $lv['6'] === 12000);
    ok(json_decode(dv_setting('level_names'), true)['6'] === 'أسطورة شبرا');
});
test('إعداد خاص بمندوب بيغلب العام', function () {
    global $CI;
    $CI->db->insert('tbldv_settings', ['staff_id' => 7, 'key' => 'monthly_target', 'value' => '30000']);
    ok(dv_setting('monthly_target', 7) === '30000' && dv_setting('monthly_target', 8) === '60000' && dv_setting('monthly_target') === '60000');
});
test('قوالب الواتساب الـ 6 بالمراحل', function () {
    $t = q('SELECT * FROM tbldv_wa_templates ORDER BY sort');
    ok(count($t) === 6, count($t));
    ok(implode(',', array_column($t, 'key')) === 'after_visit,bot_demo,followup_48h,meeting_confirm,offer_followup,breakup');
    ok($t[2]['stage_min'] == 3 && $t[2]['stage_max'] == 5 && $t[2]['days_since_touch_min'] == 2);
    ok(strpos($t[0]['body'], '{doctor_name}') !== false && strpos($t[0]['body'], '{audit_link}') !== false);
});
test('التفعيل تاني (Deactivate ثم Activate) مبيكررش ولا بيغيّر حاجة', function () {
    global $HOOKS, $CI;
    $token = dv_setting('api_token');
    $CI->db->query("UPDATE tbldv_wa_templates SET body = 'متعدّل' WHERE key = 'breakup'");
    call_user_func($HOOKS['activate']);
    ok(count(q("SELECT id FROM tblleads_status WHERE name LIKE 'DV%'")) === 11, 'مفيش مراحل مكررة');
    ok(count(q('SELECT id FROM tbldv_wa_templates')) === 6, 'مفيش قوالب مكررة');
    ok(dv_setting('api_token') === $token, 'الـ token متغيّرش');
    ok(q("SELECT body FROM tbldv_wa_templates WHERE key = 'breakup'")[0]['body'] === 'متعدّل', 'تعديلك متمسحش');
});
test('لو حد مسح Lead Status بتاع مرحلة: بيرجع يتعمل ويتربط', function () {
    global $CI, $OPT;
    $old = dv_setting('stage_status_4');
    $CI->db->query('DELETE FROM tblleads_status WHERE id = ?', [$old]);
    unset($OPT['dv_schema_version']);
    dv_ensure_schema();
    $new = dv_setting('stage_status_4');
    ok($new && $new != $old && q('SELECT name FROM tblleads_status WHERE id = ?', [$new])[0]['name'] === 'DV 4 · Doctor Talked');
});
test('جدول ناقص (Perfex ماشغّلش التفعيل بعد التحديث) بيتعمل لوحده', function () {
    global $CI, $OPT;
    $CI->db->pdo->exec('DROP TABLE tbldv_events');
    unset($OPT['dv_schema_version']);
    ok(dv_ensure_schema() === true && $CI->db->table_exists('tbldv_events'));
    ok(dv_ensure_schema() === false, 'المرة التانية مبيعملش حاجة');
});
test('XP: الـ Stage مرة واحدة لكل دكتور، بس المتابعات تتكرر عادي', function () {
    global $CI;
    $now = date('Y-m-d H:i:s');
    $CI->db->insert('tbldv_xp_ledger', ['staff_id' => 1, 'lead_id' => 5, 'action' => 'stage_DOC', 'xp' => 30, 'uniq_key' => 'stage_DOC:5', 'created_at' => $now]);
    $dup = false;
    try { $CI->db->insert('tbldv_xp_ledger', ['staff_id' => 2, 'lead_id' => 5, 'action' => 'stage_DOC', 'xp' => 30, 'uniq_key' => 'stage_DOC:5', 'created_at' => $now]); } catch (PDOException $e) { $dup = true; }
    ok($dup, 'نفس الـ Stage لنفس الدكتور اترفض');
    $CI->db->insert('tbldv_xp_ledger', ['staff_id' => 1, 'lead_id' => 5, 'action' => 'WA_SENT', 'xp' => 15, 'created_at' => $now]);
    $CI->db->insert('tbldv_xp_ledger', ['staff_id' => 1, 'lead_id' => 5, 'action' => 'WA_SENT', 'xp' => 15, 'created_at' => $now]);
    ok(count(q("SELECT id FROM tbldv_xp_ledger WHERE action = 'WA_SENT'")) === 2);
});
test('qr_code فريد', function () {
    global $CI;
    $CI->db->insert('tbldv_doctor_meta', ['lead_id' => 1, 'qr_code' => 'DV-7K2P']);
    $dup = false;
    try { $CI->db->insert('tbldv_doctor_meta', ['lead_id' => 2, 'qr_code' => 'DV-7K2P']); } catch (PDOException $e) { $dup = true; }
    ok($dup);
    $CI->db->insert('tbldv_doctor_meta', ['lead_id' => 3]); // من غير كود عادي
    $CI->db->insert('tbldv_doctor_meta', ['lead_id' => 4]);
});

echo "\nالمحرّك (المراحل 2–5):\n";
require $mod . '/helpers/dv_helper.php';
require $mod . '/models/Dv_model.php';
$CI->db->pdo->exec('DELETE FROM tbldv_doctor_meta');
$CI->db->pdo->exec('DELETE FROM tbldv_xp_ledger');
$CI->db->pdo->exec("DELETE FROM tbldv_settings WHERE staff_id = 7");
$M = new Dv_model();
$M->fixed_time = strtotime('2026-11-02 11:00:00'); // الاتنين
$B = null; $D = null;
test('عمارة + Quick Add دكتور: Lead في Perfex بحالة "DV 0" ومصدر "زيارة ميدانية" + كود QR', function () use ($M, &$B, &$D) {
    $B = $M->save_building(['name' => 'برج الأطباء', 'cluster' => 'شبرا', 'clinics_estimate' => 4, 'notes' => 'البواب عم سيد']);
    try { $M->quick_add(['name' => 'أحمد']); throw new Exception('x'); } catch (Exception $e) { ok(strpos($e->getMessage(), 'إجباري') !== false, $e->getMessage()); }
    $D = $M->quick_add(['name' => 'أحمد سمير', 'specialty' => 'أسنان', 'building_id' => $B, 'floor' => '3', 'phone' => '01012345678']);
    $l = q('SELECT * FROM tblleads WHERE id = ?', [$D])[0];
    ok($l['name'] === 'د/ أحمد سمير' && $l['phonenumber'] === '201012345678' && $l['assigned'] == 7, json_encode($l, JSON_UNESCAPED_UNICODE));
    ok($l['status'] == dv_setting('stage_status_0') && $l['source'] == dv_setting('lead_source'));
    $d = $M->doctor($D);
    ok(preg_match('/^DV-[A-Z2-9]{4}$/', $d['qr_code']) && $d['stage'] == 0 && $d['progress'] === 0 && $d['touches_left'] === 7, json_encode($d, JSON_UNESCAPED_UNICODE));
    ok(strpos($M->qr_link($d), 'https://wa.me/201027555789?text=') === 0 && strpos(urldecode($M->qr_link($d)), $d['qr_code']) !== false);
});
test('Quick Log: الخطوة الجاية إجباري · الـ Stage لقدام بس · Lead Status بيتحدث · Reminder بيتعمل', function () use ($M, &$D) {
    try { $M->log_visit($D, ['outcome' => 'GK']); throw new Exception('x'); } catch (Exception $e) { ok(strpos($e->getMessage(), 'الخطوة الجاية') !== false, $e->getMessage()); }
    $M->log_visit($D, ['outcome' => 'GK', 'note' => 'الممرضة منى', 'next_action' => 'أسلّم الظرف', 'next_action_at' => '2026-11-03 18:00']);
    ok($M->doctor($D)['stage'] == 2 && q('SELECT status FROM tblleads WHERE id = ?', [$D])[0]['status'] == dv_setting('stage_status_2'));
    ok(count(q("SELECT id FROM tblreminders WHERE rel_type = 'lead' AND rel_id = ? AND date = '2026-11-03 18:00:00'", [$D])) === 1, 'reminder');
    ok($M->feedback['toast']['number'] === 1 && $M->feedback['toast']['of'] === 25 && $M->feedback['toast']['value'] === 300, json_encode($M->feedback));
    $M->log_visit($D, ['outcome' => 'CLOSED', 'next_action' => 'أرجع', 'next_action_at' => '2026-11-04 18:00']); // أقل ← مبينزلش
    ok($M->doctor($D)['stage'] == 2, 'لقدام بس');
    ok(q('SELECT stage_before, stage_after FROM tbldv_visits ORDER BY id DESC LIMIT 1')[0]['stage_after'] == 2);
});
test('XP: زيارة 5 مرة واحدة في اليوم لكل دكتور · Stage XP مرة واحدة بس لو اتحرّك', function () use ($M, &$D) {
    ok($M->xp(7) === 15, 'GK 10 + زيارة 5 = ' . $M->xp(7)); // CLOSED نفس اليوم: مفيش زيارة تانية ولا stage
    $M->fixed_time = strtotime('2026-11-03 18:30:00');
    $M->log_visit($D, ['outcome' => 'ENV', 'next_action' => 'أكلم الدكتور', 'next_action_at' => '2026-11-05 19:00']);
    ok($M->xp(7) === 15 + 5 + 20, (string) $M->xp(7));
});
test('قاعدة الـ 3 زيارات: مينفعش Lost قبل 3 زيارات و14 يوم — إلا "Wrong lead" · والسبب إجباري', function () use ($M, $B, &$D) {
    try { $M->log_visit($D, ['outcome' => 'LOST']); throw new Exception('x'); } catch (Exception $e) { ok(strpos($e->getMessage(), 'سبب') !== false, $e->getMessage()); }
    try { $M->log_visit($D, ['outcome' => 'LOST', 'lost_reason' => 'no_budget']); throw new Exception('x'); } catch (Exception $e) { ok(strpos($e->getMessage(), 'قاعدة الـ 3 زيارات') !== false, $e->getMessage()); }
    $W = $M->quick_add(['name' => 'غلط', 'specialty' => 'جلدية', 'building_id' => $B]);
    $M->log_visit($W, ['outcome' => 'LOST', 'lost_reason' => 'wrong_lead']);
    $w = $M->doctor($W);
    ok($w['lost'] && $w['lost_reason'] === 'wrong_lead' && q('SELECT status FROM tblleads WHERE id = ?', [$W])[0]['status'] == dv_setting('lost_status'));
});
test('WA إذن واتساب: Consent بيتسجل in_person · QR_SCAN من الـ API: Stage 5 + Consent qr_scan + إشعار للمندوب', function () use ($M, $B) {
    global $NOTIF;
    $Q = $M->quick_add(['name' => 'هالة', 'specialty' => 'نسا', 'building_id' => $B]);
    $code = $M->doctor($Q)['qr_code'];
    $NOTIF = [];
    $r = $M->qr_event($code, '+20 111 222 3333', 'أنا د. هالة — شفت التحليل (' . $code . ')', '2026-11-03 20:00:00');
    $d = $M->doctor($Q);
    ok($r['stage'] === 5 && $d['wa_consent'] == 1 && $d['wa_consent_source'] === 'qr_scan' && $d['phonenumber'] === '201112223333', json_encode($d, JSON_UNESCAPED_UNICODE));
    ok(count(array_filter($NOTIF, function ($n) { return $n['description'] === 'dv_notify_qr' && $n['touserid'] == 7; })) === 1, json_encode($NOTIF));
    try { $M->qr_event('DV-ZZZZ'); throw new Exception('x'); } catch (Exception $e) { ok($e->getMessage() === 'unknown code'); }
    // Opt-out
    ok($M->opt_out('01112223333') === 1 && $M->doctor($Q)['wa_consent'] == 0);
});
test('القالب المقترح حسب المرحلة والأيام + المتغيرات الناقصة بتتعلم', function () use ($M, &$D) {
    $M->fixed_time = strtotime('2026-11-05 19:30:00'); // يومين من آخر Touch، Stage 3
    $ctx = $M->wa_context($D);
    ok($ctx['suggested']['key'] === 'followup_48h', $ctx['suggested']['key']);
    ok($ctx['vars']['doctor_name'] === 'أحمد سمير' && $ctx['vars']['rep_name'] === 'Andrew Isaac' && $ctx['vars']['day_a'] === _l('dv_day_6') && $ctx['vars']['day_b'] === _l('dv_day_0'), json_encode($ctx['vars'], JSON_UNESCAPED_UNICODE));
    ok($ctx['phone'] === '201012345678' && !$ctx['consent']);
    [$text, $missing] = dv_fill_template(q("SELECT body FROM tbldv_wa_templates WHERE key = 'after_visit'")[0]['body'], $ctx['vars']);
    ok($missing === ['audit_link', 'key_insight'] && strpos($text, 'أحمد سمير') !== false, json_encode($missing));
});
test('"اتبعتت؟ أيوه": WA_SENT في ميعاده +15 XP والخطوة الجاية بعد يومين · متأخرة +5', function () use ($M, &$D) {
    $before = $M->xp(7);
    $M->wa_sent($D, 'followup_48h'); // الخطوة الجاية كانت النهارده 19:00 ← في ميعادها
    ok($M->xp(7) - $before === 15 + 5, (string) ($M->xp(7) - $before));
    ok(substr($M->doctor($D)['next_action_at'], 0, 10) === '2026-11-07');
    $M->fixed_time = strtotime('2026-11-09 12:00:00'); // فات ميعادها
    $before = $M->xp(7);
    $M->wa_sent($D);
    ok($M->xp(7) - $before === 5 + 5, (string) ($M->xp(7) - $before));
});
test('قيمة الزيارة / فاضل كام / هدف النهارده (مثال الـ PRD) / Pipeline', function () use ($M) {
    ok(round($M->value_per_visit()) == 300); // 7500 ÷ 25
    // مثال §5.3: الهدف 60,000 · اتقفل 15,000 · 7,500 · 25 · فاضل 12 يوم شغل ← 13
    $M->fixed_time = strtotime('2026-11-17 10:00:00');
    ok($M->field_days_left(7) === 12, (string) $M->field_days_left(7));
    $X = $M->quick_add(['name' => 'رابح', 'specialty' => 'أطفال', 'building_id' => 1]);
    $GLOBALS['CI']->db->query("UPDATE tbldv_doctor_meta SET stage = 9, won_at = '2026-11-10 12:00:00', deal_value = 15000 WHERE lead_id = ?", [$X]);
    ok($M->daily_target(7) === 13, (string) $M->daily_target(7));
    ok($M->pipeline_value() > 0);
});
test('الـ Streak: أيام ورا بعض فيها 5 زيارات (الجمعة مبتتحسبش) + مكافأة 3 أيام', function () use ($M, $B) {
    $docs = [];
    for ($i = 0; $i < 5; $i++) { $docs[] = $M->quick_add(['name' => 'س' . $i, 'specialty' => 'باطنة', 'building_id' => $B]); }
    foreach (['2026-11-19 12:00:00', '2026-11-21 12:00:00', '2026-11-22 12:00:00'] as $day) { // خميس، (جمعة إجازة)، سبت، حد
        $M->fixed_time = strtotime($day);
        foreach ($docs as $d) { $M->log_visit($d, ['outcome' => 'CLOSED', 'next_action' => 'أرجع', 'next_action_at' => date('Y-m-d', strtotime($day . ' +1 day'))]); }
    }
    ok($M->streak(7) === 3, (string) $M->streak(7));
    ok(count(q("SELECT id FROM tbldv_xp_ledger WHERE action = 'streak' AND staff_id = 7")) === 1, 'مكافأة 3 أيام');
});
test('Combo: 3 دكاترة اتحركوا Stage في نفس اليوم ← ×1.5', function () use ($M, $B) {
    $M->fixed_time = strtotime('2026-11-23 12:00:00');
    $ids = [];
    for ($i = 0; $i < 4; $i++) { $ids[] = $M->quick_add(['name' => 'ك' . $i, 'specialty' => 'عيون', 'building_id' => $B]); }
    foreach (array_slice($ids, 0, 3) as $d) { $M->log_visit($d, ['outcome' => 'GK', 'next_action' => 'ظرف', 'next_action_at' => '2026-11-24']); }
    ok(count(q("SELECT id FROM tbldv_xp_ledger WHERE action = 'combo'")) === 1 && q("SELECT xp FROM tbldv_xp_ledger WHERE action = 'combo'")[0]['xp'] == 15, json_encode(q("SELECT * FROM tbldv_xp_ledger WHERE action = 'combo'")));
    $M->log_visit($ids[3], ['outcome' => 'GK', 'next_action' => 'ظرف', 'next_action_at' => '2026-11-24']);
    ok((int) q("SELECT xp FROM tbldv_xp_ledger WHERE action = 'stage_GK' AND lead_id = ?", [$ids[3]])[0]['xp'] === 15, 'بعد الـ Combo: 10 × 1.5');
});
test('الـ Level والـ Level Up بيعمل إشعار للمدير · الـ XP الشهري بيتصفّر', function () use ($M, $B) {
    global $NOTIF;
    $NOTIF = [];
    $L = $M->quick_add(['name' => 'كبير', 'specialty' => 'قلب', 'building_id' => $B]);
    $M->log_visit($L, ['outcome' => 'WON', 'deal_value' => 7500]); // 500 + 150 + 5
    $lv = $M->level(7);
    ok($lv['level'] >= 2 && $lv['name'] === 'فاتح أبواب', json_encode($lv, JSON_UNESCAPED_UNICODE));
    ok(count(array_filter($NOTIF, function ($n) { return $n['description'] === 'dv_notify_level_up' && $n['touserid'] == 1; })) === 1, json_encode($NOTIF));
    $M->fixed_time = strtotime('2026-12-02 10:00:00');
    ok($M->level(7, true)['xp'] === 0 && $M->level(7)['xp'] > 0);
});
test('Queue: المتابعات المتأخرة الأول · "أنا في عمارة X" · منطقة التقفيل والتبريد', function () use ($M, $B, &$D) {
    $M->fixed_time = strtotime('2026-11-24 12:00:00');
    $q = $M->queue(7);
    ok(in_array('overdue', $q[0]['why'], true) || in_array('due_today', $q[0]['why'], true), json_encode(array_slice($q, 0, 2), JSON_UNESCAPED_UNICODE));
    $B2 = $M->save_building(['name' => 'عمارة 2']);
    $Z = $M->quick_add(['name' => 'زين', 'specialty' => 'عظام', 'building_id' => $B2]);
    ok(count($M->queue(7, $B2)) === 1);
    $GLOBALS['CI']->db->query("UPDATE tbldv_doctor_meta SET stage = 6 WHERE lead_id = ?", [$Z]);
    $GLOBALS['CI']->db->query("INSERT INTO tbldv_visits (lead_id, staff_id, outcome, channel, visited_at, logged_at) VALUES (?, 7, 'MTG_BOOKED', 'field', '2026-11-20 10:00:00', '2026-11-20 10:00:00')", [$Z]);
    $zone = $M->closing_zone(7);
    ok(count($zone['hot']) === 1 && $zone['hot'][0]['cooling'] === 'orange', json_encode($zone['hot'][0]['cooling'] ?? null));
});
test('التقارير: Funnel بالعمارة والمعدلات · أسباب الخسارة · الـ Gatekeepers', function () use ($M, &$D) {
    $GLOBALS['CAN_ALL'] = true;
    $f = $M->funnel('building', '2026-11-01', '2026-11-30');
    $b = array_values(array_filter($f, function ($x) { return $x['group'] === 'برج الأطباء'; }))[0];
    ok($b['visited'] > 0 && $b['access_rate'] >= 0 && $b['won'] === 1, json_encode($b));
    ok($M->lost_reasons_report()[0]['lost_reason'] === 'wrong_lead');
    $M->update_doctor($D, ['gatekeeper_name' => 'منى أحمد 0100000', 'digital_score' => 3]);
    $d = $M->doctor($D);
    ok($d['gatekeeper_name'] === 'منى' && $d['grade'] === 'A', 'الاسم الأول بس + Grade');
    ok($M->gatekeepers_report()[0]['name'] === 'منى');
    ok(count($M->visits_export('2026-11-01', '2026-11-30')) > 10);
    $GLOBALS['CAN_ALL'] = false;
});
test('صلاحية view_own: المندوب ميشوفش دكاترة غيره', function () use ($M, $B) {
    global $ME;
    $ME = 8;
    $mine = $M->quick_add(['name' => 'بتاعي', 'specialty' => 'أنف', 'building_id' => $B]);
    $all  = array_column($M->doctors(), 'lead_id');
    ok($all == [$mine], json_encode($all));
    try { $M->doctor(1); throw new Exception('x'); } catch (Exception $e) { ok(strpos($e->getMessage(), 'مش من دكاترتك') !== false || strpos($e->getMessage(), 'مش موجود') !== false, $e->getMessage()); }
    $ME = 7;
});

echo "\nMySQL:\n";
test('أوامر MySQL: utf8mb4 + الـ PRIMARY KEY المركّب في الإعدادات + الـ indexes', function () {
    $s   = dv_schema();
    $set = dv_create_sql('dv_settings', $s['dv_settings'], 'tbl', 'mysql');
    ok(strpos($set[0], 'PRIMARY KEY (`staff_id`, `key`)') !== false && strpos($set[0], '`staff_id` INT NOT NULL DEFAULT 0') !== false && strpos($set[0], 'utf8mb4') !== false, $set[0]);
    $v = dv_create_sql('dv_visits', $s['dv_visits'], 'tbl', 'mysql');
    ok(strpos($v[0], '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY') !== false && count($v) === 3, json_encode($v));
    $x = dv_create_sql('dv_xp_ledger', $s['dv_xp_ledger'], 'tbl', 'mysql');
    ok($x[1] === 'CREATE UNIQUE INDEX `tbluq_dv_xp_ledger_once` ON `tbldv_xp_ledger` (`uniq_key`)', $x[1]);
});

echo "\nالشاشات (من الكنترولر زي Perfex):\n";
// ---- stubs لـ Perfex / CodeIgniter ----
$app = sys_get_temp_dir() . '/dv_app/';
if (!is_dir($app . 'views/admin/leads')) { mkdir($app . 'views/admin/leads', 0777, true); }
file_put_contents($app . 'views/admin/leads/lead.php', "<?php hooks()->do_action('after_lead_lead_tabs', \$lead); ?>\n<?php hooks()->do_action('after_lead_tabs_content', \$lead); ?>\n<?php echo hooks()->apply_filters('lead_modal_profile_bottom', ''); ?>");
define('APPPATH', $app);
define('DV_VIEWS', $mod . '/views/');
function init_head() {} function init_tail() {}
function html_escape($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function access_denied($x) { throw new Exception('denied'); }
function admin_url($p = '') { return '/admin/' . $p; } function site_url($p = '') { return '/' . $p; } function current_url() { return '/admin/current'; }
class Redirected extends Exception {}
function redirect($u) { throw new Redirected($u); }
$ALERTS = [];
function set_alert($t, $m) { global $ALERTS; $ALERTS[] = [$t, $m]; }
function form_open($a, $attrs = []) { return '<form method="post" action="' . $a . '">'; } function form_close() { return '</form>'; }
class FakeInput { public $post = []; public $get = []; public $raw_input_stream = ''; public $headers = [];
    function post($k = null, $x = true) { return $k === null ? ($this->post ?: null) : ($this->post[$k] ?? null); }
    function get($k = null) { return $k === null ? $this->get : ($this->get[$k] ?? null); }
    function get_request_header($h) { return $this->headers[$h] ?? null; } }
class FakeSession { public $flash = []; function set_flashdata($k, $v) { $this->flash[$k] = $v; } function flashdata($k) { $v = $this->flash[$k] ?? null; unset($this->flash[$k]); return $v; } }
class FakeUri { public $seg3 = 'queue'; function segment($n) { return $n === 3 ? $this->seg3 : null; } }
class FakeLoader {
    public $out = ''; public $ctrl;
    function model($m) { $this->ctrl->dv_model = $GLOBALS['M']; }
    function view($v, $data = []) {
        $v = str_replace('doctor_visits/', '', $v);
        $ctl = $this->ctrl; $data += $ctl->_vars ?? [];
        $ctl->_vars = $data;
        $render = function () use ($v, $data) { extract($data); include DV_VIEWS . $v . '.php'; };
        $render = Closure::bind($render, $ctl, get_class($ctl));
        static $depth = 0;
        $depth++; ob_start(); $render(); $html = ob_get_clean(); $depth--;
        if ($depth === 0) { $this->out .= $html; } else { echo $html; }
    }
}
class AdminController {
    public $db; public $load; public $input; public $session; public $uri; public $app = null; public $dv_model; public $_vars = [];
    function __construct() { global $CI; $this->db = $CI->db; $this->load = new FakeLoader(); $this->load->ctrl = $this; $this->input = new FakeInput(); $this->session = new FakeSession(); $this->uri = new FakeUri(); }
}
require $mod . '/controllers/Doctor_visits.php';
$GLOBALS['CAN_ALL'] = true;
$M->fixed_time = null;
function page($method, $args = [], $seg = null) {
    $c = new Doctor_visits(); $c->uri->seg3 = $seg ?: $method;
    ob_start();
    try { call_user_func_array([$c, $method], $args); } catch (Redirected $r) { ob_end_clean(); return 'REDIRECT ' . $r->getMessage(); }
    ob_end_clean();
    return $c->load->out;
}
set_error_handler(function ($n, $s, $f, $l) { throw new ErrorException("$s in " . basename($f) . ":$l"); });
test('الإعدادات والفحص: نسخة Perfex، الـ Hooks، المراحل، القوالب، الـ API', function () {
    global $CI;
    $CI->db->pdo->exec('CREATE TABLE IF NOT EXISTS tblmigrations (version INT)');
    $CI->db->pdo->exec('INSERT INTO tblmigrations VALUES (341)');
    $h = page('settings');
    file_put_contents(sys_get_temp_dir() . '/dv_settings.html', $h);
    ok(strpos($h, 'after_lead_lead_tabs') !== false && strpos($h, 'after_lead_tabs_content') !== false && strpos($h, 'lead_modal_profile_bottom') !== false, 'الـ hooks');
    ok(strpos($h, 'DV 9 · Won') !== false && strpos($h, 'followup_48h') !== false && strpos($h, '341') !== false && strpos($h, '/doctor_visits/dv_api') !== false);
    ok(substr_count($h, 'label-danger') === 0, 'مفيش حاجة ناقصة');
});
test('أزور مين النهارده + سجّل زيارة (اختيار + فورم) + الداشبورد + العماير + العمارة + الدكتور + الواتساب + التقارير', function () use (&$D, $B) {
    $pages = [
        'queue' => page('queue'), 'log' => page('log'), 'log_doc' => page('log', [$D], 'log'), 'dashboard' => page('dashboard'),
        'buildings' => page('buildings'), 'building' => page('building', [$B]), 'doctor' => page('doctor', [$D]), 'wa' => page('wa', [$D]),
        'reports' => page('reports'),
    ];
    foreach ($pages as $k => $h) {
        ok(strlen($h) > 500 && strpos($h, 'REDIRECT') !== 0, $k . ': ' . substr($h, 0, 200));
        file_put_contents(sys_get_temp_dir() . '/dv_' . $k . '.html', $h);
    }
    ok(substr_count($pages['log_doc'], 'class="dv-out') === 11, 'الـ 11 زرار');
    ok(strpos($pages['wa'], 'wa.me') !== false && strpos($pages['wa'], 'DV_T') !== false);
    ok(strpos($pages['doctor'], 'QRCode') !== false && strpos($pages['doctor'], 'DV-') !== false);
    ok(strpos($pages['dashboard'], _l('dv_closing_zone')) !== false && strpos($pages['dashboard'], _l('dv_quests')) !== false);
});
test('Quick Log من الفورم: بيحفظ ويعرض الـ Toast في الصفحة الجاية', function () use (&$D) {
    $c = new Doctor_visits();
    $c->input->post = ['outcome' => 'DOC', 'lead_id' => $D, 'next_action' => 'أطلب الواتساب', 'next_action_at' => date('Y-m-d H:i', time() + 86400), 'back' => '/admin/doctor_visits/queue'];
    try { $c->log(); } catch (Redirected $r) { ok($r->getMessage() === '/admin/doctor_visits/queue'); }
    $fb = json_decode($c->session->flash['dv_feedback'] ?? '', true);
    ok($fb && isset($fb['toast']['value']), json_encode($fb));
    // الصفحة الجاية بتعرض الـ Toast
    $c2 = new Doctor_visits(); $c2->session->flash = $c->session->flash; $c2->uri->seg3 = 'queue';
    ob_start(); $c2->queue(); ob_end_clean();
    ok(strpos($c2->load->out, 'dv-toast') !== false);
});
test('تاب "زيارات" في صفحة الـ Lead', function () use (&$D) {
    global $CI;
    $CI->load = new class { function model($m) { $GLOBALS['CI']->dv_model = $GLOBALS['M']; } function view($v, $data = []) { extract($data); include DV_VIEWS . str_replace('doctor_visits/', '', $v) . '.php'; } };
    $lead = (object) ['id' => $D];
    ob_start(); doctor_visits_lead_tab($lead); doctor_visits_lead_tab_content($lead); $h = ob_get_clean();
    ok(strpos($h, 'tab_doctor_visits') !== false && strpos($h, 'tab-pane') !== false, substr($h, 0, 300));
    ob_start(); doctor_visits_lead_tab((object) ['id' => 99999]); ok(ob_get_clean() === '', 'Lead مش دكتور ← مفيش تاب');
});
restore_error_handler();

echo "\nالـ API (n8n):\n";
class App_Controller { public $db; public $load; public $input; public $output; public $dv_model;
    function __construct() { global $CI; $this->db = $CI->db; $this->input = $GLOBALS['API_IN']; $this->load = new class { function model($m) {} }; $this->dv_model = $GLOBALS['M'];
        $this->output = new class { public $status; public $body; function set_status_header($s) { $this->status = $s; return $this; } function set_content_type($t, $c = null) { return $this; } function set_output($o) { $this->body = $o; return $this; } function _display() { throw new ApiDone($this->status . ' ' . $this->body); } }; } }
class ApiDone extends Error {}
require $mod . '/controllers/Dv_api.php';
function api($method, $headers, $body = [], $get = []) {
    $in = new FakeInput(); $in->headers = $headers; $in->raw_input_stream = json_encode($body); $in->get = $get; $GLOBALS['API_IN'] = $in;
    try { $c = new Dv_api(); $c->$method(); } catch (ApiDone $e) { [$st, $js] = explode(' ', $e->getMessage(), 2); return [(int) $st, json_decode($js, true)]; }
    return [0, null];
}
test('من غير Token صح ← 401', function () {
    [$st] = api('queue', ['X-DV-Token' => 'غلط'], [], ['staff_id' => 7]);
    ok($st === 401, (string) $st);
});
test('qr_event / queue / followups_due / opt_out بالـ Token', function () use ($M, $B) {
    $tok = dv_setting('api_token');
    $GLOBALS['ME'] = 7;
    $L = $M->quick_add(['name' => 'API', 'specialty' => 'جلدية', 'building_id' => $B]);
    $code = $M->doctor($L)['qr_code'];
    [$st, $r] = api('qr_event', ['X-DV-Token' => $tok], ['code' => $code, 'phone' => '01055555555', 'message' => 'أنا د. API (' . $code . ')']);
    ok($st === 200 && $r['ok'] && $r['stage'] === 5, json_encode($r, JSON_UNESCAPED_UNICODE));
    [$st, $r] = api('qr_event', ['X-DV-Token' => $tok], ['code' => 'DV-0000']);
    ok($st === 422, (string) $st);
    [$st, $r] = api('queue', ['X-DV-Token' => $tok], [], ['staff_id' => 7]);
    ok($st === 200 && isset($r['today_target']) && is_array($r['queue']) && isset($r['quests']), json_encode(array_keys($r ?? [])));
    [$st, $r] = api('followups_due', ['X-DV-Token' => $tok], [], ['staff_id' => 7]);
    ok($st === 200 && isset($r['followups']));
    [$st, $r] = api('opt_out', ['X-DV-Token' => $tok], ['phone' => '01055555555']);
    ok($st === 200 && $r['updated'] === 1);
});
test('Rate limit: أكتر من 60 طلب في الدقيقة ← 429', function () {
    $tok = dv_setting('api_token');
    update_option('dv_api_rl', date('YmdHi') . '|60');
    [$st] = api('followups_due', ['X-DV-Token' => $tok]);
    ok($st === 429, (string) $st);
    update_option('dv_api_rl', '');
});

echo "\n$passed passed · $failed failed\n";
exit($failed ? 1 : 0);
