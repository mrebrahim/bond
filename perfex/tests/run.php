<?php
// اختبارات الـ module بتاع Perfex — من غير Perfex: CodeIgniter db مزيّف على SQLite + Evolution مزيّف
// php perfex/tests/run.php

define('BASEPATH', __DIR__);
$mod = __DIR__ . '/../modules/dse_studio';
require $mod . '/helpers/dse_studio_schema_helper.php';
require $mod . '/helpers/dse_studio_helper.php';
require $mod . '/libraries/Dse_evolution.php';

// ---- بيئة شبه Perfex ----
$OPTIONS = dse_studio_default_options();
$OPTIONS['dse_evo_key'] = 'test';
$STAFF = 1;
function db_prefix() { return 'tbl'; }
function get_option($k) { global $OPTIONS; return $OPTIONS[$k] ?? ''; }
function update_option($k, $v) { global $OPTIONS; $OPTIONS[$k] = $v; }
function get_staff_user_id() { global $STAFF; return $STAFF; }

class FakeResult
{
    private $rows;
    public function __construct($rows) { $this->rows = $rows; }
    public function result_array() { return $this->rows; }
    public function row_array() { return $this->rows[0] ?? null; }
}

class FakeDb
{
    public $pdo;
    private $affected = 0;
    public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    public function query($sql, $binds = [])
    {
        $st = $this->pdo->prepare(str_replace('`', '"', $sql));
        $st->execute(array_values($binds));
        $this->affected = $st->rowCount();
        return new FakeResult($st->columnCount() ? $st->fetchAll(PDO::FETCH_ASSOC) : []);
    }
    public function insert($table, $data)
    {
        $cols = array_keys($data);
        $this->query('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
        return true;
    }
    public function update($table, $data, $where)
    {
        $set = implode(',', array_map(function ($k) { return $k . ' = ?'; }, array_keys($data)));
        $w   = implode(' AND ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($where)));
        $this->query('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $w, array_merge(array_values($data), array_values($where)));
        return true;
    }
    public function insert_id() { return (int) $this->pdo->lastInsertId(); }
    public function affected_rows() { return $this->affected; }
}

class App_Model { public $db; }
require $mod . '/models/Dse_studio_model.php';

$db = new FakeDb();
foreach (dse_studio_schema() as $t => $def) {
    foreach (dse_studio_create_sql($t, $def, 'tbl', 'sqlite') as $sql) {
        $db->pdo->exec(str_replace('`', '"', $sql));
    }
}
// جداول Perfex اللي بنقرا منها
$db->pdo->exec('CREATE TABLE tblclients (userid INTEGER PRIMARY KEY AUTOINCREMENT, company TEXT, phonenumber TEXT)');
$db->pdo->exec('CREATE TABLE tblcontacts (id INTEGER PRIMARY KEY AUTOINCREMENT, userid INT, firstname TEXT, lastname TEXT, is_primary INT)');
$db->pdo->exec('CREATE TABLE tblstaff (staffid INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, active INT, admin INT)');
$db->pdo->exec("INSERT INTO tblstaff VALUES (7, 'أندرو', 'إسحاق', 'a@x.com', 1, 0)");
$db->pdo->exec('CREATE TABLE tblinvoices (id INTEGER PRIMARY KEY AUTOINCREMENT, clientid INT, total REAL, status INT)');

$sent = [];
$evo  = new Dse_evolution();
$evo->transport = function ($method, $url, $body) use (&$sent) {
    if (strpos($url, '/message/sendText/') !== false) {
        if (strpos($body['text'], 'FAIL') !== false) { return [500, ['error' => 'boom']]; }
        $sent[] = $body;
        return [201, ['key' => ['id' => 'x']]];
    }
    if (strpos($url, '/group/create/') !== false) { $GLOBALS['group_members'] = $body['participants']; return [201, ['id' => '120363000000000001@g.us']]; }
    if (strpos($url, '/group/inviteCode/') !== false) { return [200, ['inviteCode' => 'AbCdEfGhIj12']]; }
    if (strpos($url, '/group/updateParticipant/') !== false) { return [200, ['ok' => true, 'n' => count($body['participants'])]]; }
    return [404, []];
};

$m = new Dse_studio_model();
$m->db  = $db;
$m->evo = $evo;
$m->fixed_time = strtotime('2026-11-02 10:00:00'); // الاتنين

$passed = 0; $failed = 0;
function test($name, $fn) { global $passed, $failed; try { $fn(); $passed++; echo "  ✔ $name\n"; } catch (Throwable $e) { $failed++; echo "  ✘ $name\n      " . $e->getMessage() . ' @' . $e->getLine() . "\n"; } }
function ok($c, $msg = 'assertion failed') { if (!$c) { throw new Exception($msg); } }
function throws($fn, $includes) { try { $fn(); } catch (Exception $e) { if (strpos($e->getMessage(), $includes) === false) { throw new Exception('error غلط: ' . $e->getMessage()); } return; } throw new Exception('كان المفروض يرمي error: ' . $includes); }
function q($sql, $b = []) { global $db; return $db->query($sql, $b)->result_array(); }

$db->insert('tblclients', ['company' => 'مطعم الشيف', 'phonenumber' => '01012345678']);
$client = 1;
$db->insert('tblclients', ['company' => 'جيم', 'phonenumber' => '123']);
$client2 = 2;
$db->pdo->exec("ALTER TABLE tblclients ADD COLUMN active INT DEFAULT 1");
$db->insert('tblclients', ['company' => '', 'phonenumber' => '⁦+20 12 10010360⁩']);
$db->insert('tblcontacts', ['userid' => 3, 'firstname' => 'دكتورة', 'lastname' => 'سارة', 'is_primary' => 0]);
$db->insert('tblcontacts', ['userid' => 3, 'firstname' => 'سارة', 'lastname' => 'محمود', 'is_primary' => 1]);
$db->insert('tblclients', ['company' => '', 'phonenumber' => '']);
$studio  = $m->add_studio('استوديو 1');
$studio2 = $m->add_studio('استوديو 2');

echo "العملاء والباقات:\n";
test('اسم العميل: الشركة ولو فاضية جهة الاتصال الأساسية ولو مفيش الرقم', function () use ($m) {
    ok($m->client(3)['company'] === 'سارة محمود', $m->client(3)['company']);
    ok($m->client(3)['wa_phone'] === '201210010360', $m->client(3)['wa_phone']);
    ok($m->client(4)['company'] === 'عميل #4', $m->client(4)['company']);
    $names = array_column($m->clients_list(), 'company');
    ok(in_array('سارة محمود', $names, true) && in_array('مطعم الشيف', $names, true), json_encode($names, JSON_UNESCAPED_UNICODE));
});
test('كود العميل C001 ورقم الواتساب بيتظبط من 01...', function () use ($m, $client, $client2) {
    $c = $m->client($client);
    ok($c['code'] === 'C001' && $c['wa_phone'] === '201012345678', json_encode($c));
    ok($m->client($client2)['wa_phone'] === null);
    throws(function () use ($m, $client2) { $m->set_wa_phone($client2, '555'); }, 'رقم الواتساب');
});
$pkg = null; $inv = null;
test('الباقة بتبدأ مستنية الدفع وبتتفعل لما الفاتورة تتدفع', function () use ($m, $db, $client, &$pkg, &$inv) {
    $db->insert('tblinvoices', ['clientid' => 1, 'total' => 15000, 'status' => 1]);
    $inv = $db->insert_id();
    $pkg = $m->create_package($client, 'Growth', 15000, '2026-11-01', 30, ['reel' => 10, 'design' => 8, 'bogus' => 3], $inv);
    ok($m->package($pkg)['status'] === 'pending');
    ok($m->package($pkg)['end_date'] === '2026-11-30', $m->package($pkg)['end_date']);
    ok(!isset($m->balance($pkg)['bogus']), 'نوع مش معروف');
    ok($m->active_package($client) === null);
    $db->update('tblinvoices', ['status' => 2], ['id' => $inv]);
    ok($m->active_package($client)['id'] == $pkg, 'اتفعلت');
});
test('فاتورة عميل تاني مينفعش تتربط', function () use ($m, $db, $pkg) {
    $db->insert('tblinvoices', ['clientid' => 2, 'total' => 1, 'status' => 2]);
    throws(function () use ($m, $pkg, $db) { $m->link_invoice($pkg, $db->insert_id()); }, 'مش بتاعة العميل');
});
test('باقة من غير خدمات بتترفض', function () use ($m, $client) {
    throws(function () use ($m, $client) { $m->create_package($client, 'x', 1, null, 30, []); }, 'ضيف خدمة');
});

echo "\nالحجز:\n";
$s1 = null;
test('الحجز بيحسب المدة واللبسات ويحجز من الرصيد + رسالة "تم الحجز"', function () use ($m, $client, $studio, $pkg, &$s1) {
    $s1 = $m->book_session($client, $studio, '2026-11-04 12:00', 'reel', 4, 7);
    $s  = $m->session($s1);
    ok($s['end_at'] === '2026-11-04 13:20:00', $s['end_at']);
    ok((int) $s['outfits'] === 2);
    ok($m->balance($pkg)['reel']['remaining'] == 6 && $m->balance($pkg)['reel']['reserved'] == 4);
    $o = q("SELECT * FROM tbldse_wa_outbox WHERE msg_key = 'session_booked'");
    ok(count($o) === 1 && strpos($o[0]['body'], 'تم حجز ميعاد التصوير') === 0 && strpos($o[0]['body'], 'هات 2 لبس') !== false, $o[0]['body'] ?? '');
});
test('تعارض نفس الاستوديو بيترفض', function () use ($m, $client, $studio) {
    throws(function () use ($m, $client, $studio) { $m->book_session($client, $studio, '2026-11-04 13:00', 'reel', 1); }, 'محجوز في الوقت ده');
});
test('تعارض نفس المصور في استوديو تاني بيترفض', function () use ($m, $client, $studio2) {
    throws(function () use ($m, $client, $studio2) { $m->book_session($client, $studio2, '2026-11-04 12:30', 'reel', 1, 7); }, 'المصور عنده جلسة');
});
test('الحجز أكتر من الرصيد (بعد الحجوزات التانية) بيترفض', function () use ($m, $client, $studio2) {
    throws(function () use ($m, $client, $studio2) { $m->book_session($client, $studio2, '2026-11-05 12:00', 'reel', 7); }, 'فاضل 6');
});
test('عميل من غير باقة شغالة مينفعش يحجز / بعد نهاية الباقة مينفعش', function () use ($m, $client, $client2, $studio2) {
    throws(function () use ($m, $client2, $studio2) { $m->book_session($client2, $studio2, '2026-11-05 12:00', 'reel', 1); }, 'مفيش عنده باقة شغالة');
    throws(function () use ($m, $client, $studio2) { $m->book_session($client, $studio2, '2026-12-05 12:00', 'reel', 1); }, 'الباقة بتخلص');
    throws(function () use ($m, $client, $studio2) { $m->book_session($client, $studio2, '2026-11-05 12:00', 'design', 1); }, 'نوع الجلسة غلط');
});
test('تغيير الميعاد: رسالة "اتغيّر" مرة واحدة والتعارض بيتشيّك', function () use ($m, $client, $studio, $studio2, &$s1) {
    $other = $m->book_session($client, $studio2, '2026-11-06 12:00', 'reel', 1);
    throws(function () use ($m, $other) { $m->reschedule_session($other, '2026-11-04 12:30', null, 7); }, 'المصور');
    $m->reschedule_session($s1, '2026-11-05 15:00');
    $m->reschedule_session($s1, '2026-11-05 15:00');
    $o = q("SELECT body FROM tbldse_wa_outbox WHERE msg_key = 'session_booked' AND body LIKE 'ميعاد التصوير اتغيّر%'");
    ok(count($o) === 1, count($o));
    ok($m->session($s1)['end_at'] === '2026-11-05 16:20:00');
    $m->cancel_session($other);
});
test('خلصت الجلسة: بيخصم اللي اتصور فعلًا ويرجّع الباقي للرصيد', function () use ($m, $pkg, $s1) {
    throws(function () use ($m, $s1) { $m->complete_session($s1, 20); }, 'أكتر من رصيد');
    $m->complete_session($s1, 3);
    $b = $m->balance($pkg)['reel'];
    ok($b['used'] == 3 && $b['reserved'] == 0 && $b['remaining'] == 7, json_encode($b));
    throws(function () use ($m, $s1) { $m->complete_session($s1, 3); }, 'مش محجوزة');
});
test('مجاش: بيخصم وحدة', function () use ($m, $client, $studio, $pkg) {
    $s = $m->book_session($client, $studio, '2026-11-07 12:00', 'reel', 2);
    ok($m->mark_no_show($s) == 1);
    ok($m->balance($pkg)['reel']['remaining'] == 6);
});
test('الإلغاء المتأخر بيخصم والبدري لأ', function () use ($m, $client, $studio, $pkg) {
    $late = $m->book_session($client, $studio, '2026-11-02 20:00', 'reel', 1);
    $r    = $m->cancel_session($late, 'ظروف');
    ok($r['late'] && $r['penalty'] == 1, json_encode($r));
    $early = $m->book_session($client, $studio, '2026-11-20 12:00', 'reel', 2);
    $r     = $m->cancel_session($early);
    ok(!$r['late'] && $r['penalty'] == 0);
    ok($m->balance($pkg)['reel']['remaining'] == 5);
});

echo "\nالسكربتات والنشر:\n";
test('السكربت: رسالة واحدة لكل نسخة', function () use ($m, $client) {
    $s = $m->add_script($client, 'افتتاح الفرع', 'أهلا بيكم...');
    $m->send_script($s);
    $m->set_script_status($s, 'changes');
    $m->update_script($s, 'افتتاح الفرع', 'أهلا بيكم 2');
    $m->send_script($s);
    $o = q("SELECT body FROM tbldse_wa_outbox WHERE msg_key = 'script_sent'");
    ok(count($o) === 2 && strpos($o[1]['body'], 'أهلا بيكم 2') !== false, json_encode($o, JSON_UNESCAPED_UNICODE));
    $m->set_script_status($s, 'approved');
    throws(function () use ($m, $s) { $m->send_script($s); }, 'وافق عليه');
});
test('البوست نزل: رسالة فيها اللينك والتقييم والرصيد — والتصميم بيتخصم', function () use ($m, $client, $pkg) {
    throws(function () use ($m, $client) { $m->publish_post($client, 'reel', 'instagram.com/p/x'); }, 'لينك البوست');
    $p = $m->publish_post($client, 'reel', 'https://instagram.com/p/abc');
    $m->publish_post($client, 'design', 'https://instagram.com/p/def');
    ok($m->balance($pkg)['design']['remaining'] == 7);
    $o = q("SELECT body FROM tbldse_wa_outbox WHERE msg_key = 'published' ORDER BY id");
    ok(count($o) === 2, count($o));
    ok(strpos($o[0]['body'], 'https://instagram.com/p/abc') !== false && strpos($o[0]['body'], 'entry.878760930=C001&entry.220082110=' . $p) !== false, $o[0]['body']);
    ok(strpos($o[0]['body'], 'فاضلك 5 ريل · 8 تصميم') !== false, $o[0]['body']);
    ok(strpos($o[1]['body'], 'التصميم بتاعك نزل') === 0);
});

echo "\nالواتساب:\n";
test('مقفول: مفيش ولا رسالة بتتبعت', function () use ($m, &$sent) {
    $r = $m->process_outbox(50);
    ok($r['sent'] === 0 && count($sent) === 0);
});
test('الجروب: العميل + أرقام الفريق، والرسايل بتروح الجروب بعد كده', function () use ($m, $client) {
    $g = $m->create_group($client);
    ok($g['jid'] === '120363000000000001@g.us' && $g['link'] === 'https://chat.whatsapp.com/AbCdEfGhIj12', json_encode($g));
    ok(count($GLOBALS['group_members']) === 5 && $GLOBALS['group_members'][0] === '201012345678', json_encode($GLOBALS['group_members']));
    throws(function () use ($m, $client) { $m->create_group($client); }, 'عنده جروب');
    $m->send_manual($client, 'rules');
    $o = q("SELECT * FROM tbldse_wa_outbox WHERE msg_key = 'rules'");
    ok($o[0]['destination'] === '120363000000000001@g.us' && $o[0]['manual'] == 1 && strpos($o[0]['body'], 'قواعد التعامل') !== false);
    throws(function () use ($m, $client) { $m->send_manual($client, 'spam'); }, 'نوع رسالة غير معروف');
});
test('الإرسال: بيحجز قبل ما يبعت (مفيش تكرار) + 6 أوتوماتيك في اليوم + اليدوي مش بيتحسب', function () use ($m, $client, &$sent) {
    update_option('dse_wa_enabled', '1');
    for ($i = 0; $i < 8; $i++) { $m->queue_wa($client, 'published', 'burst:' . $i, ['post_url' => 'https://x/' . $i, 'type' => 'reel']); }
    $m->queue_wa($client, 'published', 'burst:0', ['post_url' => 'dup', 'type' => 'reel']);
    $r1 = $m->process_outbox(50);
    $r2 = $m->process_outbox(50);
    $auto_to_old_dest = count(q("SELECT id FROM tbldse_wa_outbox WHERE manual = 0 AND status = 'sent'"));
    ok($r1['failed'] === 0 && $r2['sent'] === 0, json_encode([$r1, $r2]));
    $per_dest = q("SELECT destination, COUNT(*) n FROM tbldse_wa_outbox WHERE manual = 0 AND status = 'sent' GROUP BY destination");
    foreach ($per_dest as $d) { ok($d['n'] <= 6, json_encode($per_dest)); }
    ok(count(q("SELECT id FROM tbldse_wa_outbox WHERE manual = 1 AND status = 'sent'")) === 1, 'اليدوي اتبعت');
    ok(count(q("SELECT id FROM tbldse_wa_outbox WHERE status = 'queued'")) > 0, 'الزيادة مستنية');
    ok(count($sent) === $r1['sent'], 'كل رسالة اتبعتت مرة');
    ok(count(q("SELECT id FROM tbldse_wa_outbox WHERE dedupe_key = 'burst:0'")) === 1);
    update_option('dse_wa_enabled', '0');
});
test('الفشل بيتسجل ومبيتعادش لوحده', function () use ($m, $client, $db) {
    update_option('dse_wa_enabled', '1');
    $db->query("UPDATE tbldse_wa_outbox SET status = 'sent' WHERE status = 'queued'");
    $id = $m->queue_wa($client, 'welcome', 'fail-test', ['client_name' => 'FAIL'], true);
    $m->process_outbox(5);
    $r = q('SELECT status, error FROM tbldse_wa_outbox WHERE id = ?', [$id])[0];
    ok($r['status'] === 'failed' && strpos($r['error'], 'Evolution 500') === 0, json_encode($r));
    $m->process_outbox(5);
    ok(q('SELECT status FROM tbldse_wa_outbox WHERE id = ?', [$id])[0]['status'] === 'failed');
    update_option('dse_wa_enabled', '0');
});

echo "\nالتنبيهات:\n";
test('العميل بقاله فترة مابيصوّرش → تنبيه مرة واحدة، urgent لو مفيش نشر', function () use ($m, $client, $db) {
    ok(count($m->check_inactive_clients()) === 0, 'لسه بادئ');
    $m->fixed_time = strtotime('2026-11-27 10:00:00');
    $new = $m->check_inactive_clients();
    ok(count($new) === 1, count($new));
    $a = q('SELECT * FROM tbldse_alerts WHERE id = ?', [$new[0]])[0];
    ok($a['priority'] === 'urgent' && strpos($a['message'], 'بقاله 22 يوم') !== false && strpos($a['message'], 'المحتوى خلص') !== false, $a['message']);
    ok(count($m->check_inactive_clients()) === 0, 'مرة واحدة');
    throws(function () use ($m, $a) { $m->resolve_alert($a['id'], ''); }, 'حصل إيه');
    $m->resolve_alert($a['id'], 'كلمته وحجز');
    ok(count($m->alerts(true)) === 0);
});
test('الباقة بتخلص لوحدها بعد مدتها', function () use ($m, $pkg) {
    $m->fixed_time = strtotime('2026-12-01 10:00:00');
    $m->sync_all_packages();
    ok($m->package($pkg)['status'] === 'expired');
    $m->fixed_time = strtotime('2026-11-02 10:00:00');
});

echo "\nالصفحات (render):\n";
define('DSE_STUDIO_MODULE_NAME', 'dse_studio');
function init_head() {} function init_tail() {}
function _l($k) { return $k; }
function admin_url($p = '') { return '/admin/' . $p; }
function form_open($a, $attrs = []) { return '<form action="' . $a . '">'; }
function form_close() { return '</form>'; }
function staff_can($c, $f) { return true; }
function is_admin() { return true; }
function html_escape($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function &get_instance() { global $CIStub; return $CIStub; }
$CIStub = new class { public $dse_studio_model; public $load; };
$CIStub->dse_studio_model = $m;
$CIStub->load = new class { public function model($x) {} };
function render($view, $vars) {
    extract($vars);
    ob_start();
    include __DIR__ . '/../modules/dse_studio/views/' . $view . '.php';
    $html = ob_get_clean();
    ok(strlen($html) > 200, $view . ' فاضي');
    return $html;
}
set_error_handler(function ($no, $str, $file, $line) { throw new ErrorException($str . ' in ' . basename($file) . ':' . $line); });
test('كل الصفحات بتترسم من غير أخطاء', function () use ($m, $client) {
    $sum = $m->client_summary($client);
    $balances = [];
    foreach ($sum['packages'] as $p) { $balances[$p['id']] = $m->balance($p['id']); }
    render('client', $sum + ['studios' => $m->studios(), 'staff' => [['staffid' => 7, 'firstname' => 'أندرو', 'lastname' => 'إسحاق']],
        'sessions' => $m->client_sessions($client), 'scripts' => $m->client_scripts($client), 'posts' => $m->client_posts($client),
        'outbox' => $m->outbox(20, $client), 'invoices' => [['id' => 1, 'prefix' => 'INV-', 'number' => 5, 'total' => 15000, 'status' => 2, 'date' => '2026-11-01']],
        'balances' => $balances]);
    render('index', ['clients' => [['userid' => 1, 'company' => 'x']], 'packages' => $m->clients_with_packages(),
        'today' => $m->sessions_between('2026-11-01', '2026-12-01'), 'alerts' => 2]);
    $cal = render('calendar', ['start' => '2026-10-31', 'studio' => 0, 'cancelled' => true, 'studios' => $m->studios(),
        'sessions' => $m->sessions_between('2026-10-31', '2026-11-07')]);
    file_put_contents(sys_get_temp_dir() . '/dse_calendar.html', $cal);
    ok(strpos($cal, 'مطعم الشيف') !== false && strpos($cal, '📷 أندرو إسحاق') !== false, 'الاسم والمصور');
    ok(count($m->sessions_between('2026-10-31', '2026-11-30', null, 999)) === 0, 'فلتر الاستوديو');
    ok(count($m->sessions_between('2026-10-31', '2026-11-30', null, null, false)) < count($m->sessions_between('2026-10-31', '2026-11-30')), 'من غير الملغية');
    render('alerts', ['all' => true, 'alerts' => $m->alerts(false)]);
    render('outbox', ['outbox' => $m->outbox(), 'enabled' => false]);
    render('settings', ['studios' => $m->studios(false), 'staff' => [['staffid' => 1, 'firstname' => 'Ibrahim']], 'state' => 'open']);
    $client = (object) ['userid' => 1];
    render('customer_tab', ['client' => $client]);
});
restore_error_handler();

echo "\n$passed passed · $failed failed\n";
exit($failed ? 1 : 0);
