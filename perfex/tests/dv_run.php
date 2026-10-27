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
    function platform() { return 'sqlite'; } function version() { return '3'; }
}
$CI = new class { public $db; };
$CI->db = new DB();
function &get_instance() { global $CI; return $CI; }

// جداول Perfex اللي بنلمسها: leads_status فيها حالات موجودة قبل كده
$CI->db->pdo->exec('CREATE TABLE tblleads_status (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, statusorder INT, color TEXT, isdefault INT)');
$CI->db->pdo->exec("INSERT INTO tblleads_status (name, statusorder, color, isdefault) VALUES ('Customer', 1000, '#7cb342', 1), ('New', 1, '#28B8DA', 0)");

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

echo "\nصفحة الفحص:\n";
test('صفحة الإعدادات/الفحص بتترسم وبتلاقي الـ Hooks من ملفات الـ Leads', function () use ($mod) {
    global $CI;
    // ملف lead view مزيّف بالـ hooks الحقيقية اللي في Perfex
    $app = sys_get_temp_dir() . '/dv_app/';
    if (!is_dir($app . 'views/admin/leads')) { mkdir($app . 'views/admin/leads', 0777, true); }
    file_put_contents($app . 'views/admin/leads/lead.php', "<?php hooks()->do_action('after_lead_lead_tabs', \$lead); ?>\n<?php hooks()->do_action('after_lead_tabs_content', \$lead); ?>\n<?php echo hooks()->apply_filters('lead_modal_profile_bottom', ''); ?>");
    define('APPPATH', $app);
    // stubs لـ Perfex
    if (!function_exists('_l')) { function _l($k) { static $lang; if (!$lang) { $lang = []; include __DIR__ . '/../modules/doctor_visits/language/arabic/doctor_visits_lang.php'; } return $lang[$k] ?? $k; } }
    function init_head() {} function init_tail() {}
    function html_escape($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
    function staff_can($c, $f) { return true; } function is_admin() { return true; }
    function access_denied($x) { throw new Exception('denied'); }
    function admin_url($p = '') { return '/admin/' . $p; } function redirect($u) {}
    class AdminController { public $db; public $load; public $app = null; function __construct() { global $CI; $this->db = $CI->db; $this->load = new class { public $out = ''; function view($v, $data) { extract($data); ob_start(); include __DIR__ . '/../modules/doctor_visits/views/' . $v . '.php'; $this->out = ob_get_clean(); } }; } }
    $CI->db->pdo->exec('CREATE TABLE IF NOT EXISTS tblmigrations (version INT)');
    $CI->db->pdo->exec('INSERT INTO tblmigrations VALUES (341)');
    require $mod . '/controllers/Doctor_visits.php';
    $c = new Doctor_visits();
    $c->settings();
    $html = $c->load->out;
    file_put_contents(sys_get_temp_dir() . '/dv_settings.html', $html);
    ok(strpos($html, 'after_lead_lead_tabs') !== false && strpos($html, 'after_lead_tabs_content') !== false && strpos($html, 'lead_modal_profile_bottom') !== false, 'الـ hooks');
    ok(strpos($html, 'DV 9 · Won') !== false && strpos($html, 'followup_48h') !== false && strpos($html, '341') !== false);
    ok(strpos($html, dv_setting('api_token')) === false, 'الـ token متخبي');
    ok(substr_count($html, 'label-danger') === 0, 'مفيش حاجة ناقصة');
});

echo "\n$passed passed · $failed failed\n";
exit($failed ? 1 : 0);
