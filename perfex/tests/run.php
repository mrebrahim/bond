<?php
// اختبارات الـ module بتاع Perfex — من غير Perfex: CodeIgniter db مزيّف على SQLite + Evolution مزيّف
// php perfex/tests/run.php

define('BASEPATH', __DIR__);
$mod = __DIR__ . '/../modules/dse_studio';
require $mod . '/helpers/dse_studio_setup.php';
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
function add_option($k, $v, $a = 1) { global $OPTIONS; if (!array_key_exists($k, $OPTIONS)) { $OPTIONS[$k] = $v; } }

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
    public $dbdriver = 'sqlite';
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
    public function table_exists($t) { return (bool) $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $this->pdo->quote($t))->fetch(); }
    public function affected_rows() { return $this->affected; }
    public function field_exists($f, $t) { foreach ($this->pdo->query('PRAGMA table_info("' . $t . '")')->fetchAll() as $c) { if ($c['name'] === $f) { return true; } } return false; }
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
$db->pdo->exec('CREATE TABLE tblclients (userid INTEGER PRIMARY KEY AUTOINCREMENT, company TEXT, phonenumber TEXT, default_currency INT DEFAULT 0)');
$db->pdo->exec('CREATE TABLE tblcontacts (id INTEGER PRIMARY KEY AUTOINCREMENT, userid INT, firstname TEXT, lastname TEXT, is_primary INT)');
$db->pdo->exec('CREATE TABLE tblstaff (staffid INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, active INT, admin INT)');
$db->pdo->exec("INSERT INTO tblstaff VALUES (7, 'أندرو', 'إسحاق', 'a@x.com', 1, 0)");
$db->pdo->exec('CREATE TABLE tblinvoicepaymentrecords (id INTEGER PRIMARY KEY AUTOINCREMENT, invoiceid INT, amount REAL, paymentmode TEXT, date TEXT, daterecorded TEXT, note TEXT)');
$db->pdo->exec('CREATE TABLE tblpayment_modes (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INT)');
$db->pdo->exec("INSERT INTO tblpayment_modes (name, active) VALUES ('كاش', 1), ('فودافون كاش', 1), ('قديمة', 0)");
$db->pdo->exec('CREATE TABLE tblinvoices (id INTEGER PRIMARY KEY AUTOINCREMENT, clientid INT, total REAL, status INT, prefix TEXT DEFAULT "INV-", number INT DEFAULT 1, date TEXT)');

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
    // الاسم متكتب كامل في الخانتين ← ميتكررش
    $db = $GLOBALS['db'];
    $db->insert('tblclients', ['company' => '', 'phonenumber' => '']);
    $dup = $db->insert_id();
    $db->insert('tblcontacts', ['userid' => $dup, 'firstname' => 'د/ميشيل كرم', 'lastname' => 'د/ميشيل كرم', 'is_primary' => 1]);
    ok($m->client($dup)['company'] === 'د/ميشيل كرم', $m->client($dup)['company']);
    $db->insert('tblclients', ['company' => '', 'phonenumber' => '']);
    $half = $db->insert_id();
    $db->insert('tblcontacts', ['userid' => $half, 'firstname' => 'محمد', 'lastname' => 'محمد احمد', 'is_primary' => 1]);
    ok($m->client($half)['company'] === 'محمد احمد', $m->client($half)['company']);
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

echo "\nالجروب:\n";
test('من غير جروب: مفيش رسايل على الخاص خالص — تنبيه للفريق بدالها', function () use ($m, $client, $studio) {
    $m->new_alerts = [];
    $sid = $m->book_session($client, $studio, '2026-11-25 12:00', 'reel', 1);
    ok(count(q('SELECT id FROM tbldse_wa_outbox')) === 0, 'مفيش رسالة');
    $a = q("SELECT * FROM tbldse_alerts WHERE alert_key = 'no_group'");
    ok(count($a) === 1 && strpos($a[0]['message'], 'تم الحجز') !== false && count($m->new_alerts) === 1, json_encode($a, JSON_UNESCAPED_UNICODE));
    throws(function () use ($m, $client) { $m->send_manual($client, 'welcome'); }, 'مالوش جروب');
    $m->cancel_session($sid);
    $m->resolve_alert($a[0]['id'], 'اتعمل');
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
test('عميل من غير باقة شغالة مينفعش يحجز / بعد نهاية الباقة + فترة السماح (15 يوم) مينفعش', function () use ($m, $client, $client2, $studio2) {
    throws(function () use ($m, $client2, $studio2) { $m->book_session($client2, $studio2, '2026-11-05 12:00', 'reel', 1); }, 'مفيش عنده باقة شغالة');
    throws(function () use ($m, $client, $studio2) { $m->book_session($client, $studio2, '2026-12-16 12:00', 'reel', 1); }, '15/12 (بفترة السماح)');
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

echo "\nالدفع الجزئي:\n";
test('دافع 50%: الباقة بتتفعل، ولما الشغل يوصل 50% تنبيه + إيميل لإنجي ومريم (مرة واحدة)', function () use ($m, $db, $client2) {
    $m->save_client_wa($client2, ['wa_group_jid' => '120363000000000002@g.us']);
    $db->insert('tblinvoices', ['clientid' => $client2, 'total' => 10000, 'status' => 1]);
    $inv = $db->insert_id();
    $pkg = $m->create_package($client2, 'Starter', 10000, '2026-11-01', 30, ['reel' => 4, 'design' => 4], $inv);
    $db->insert('tblinvoicepaymentrecords', ['invoiceid' => $inv, 'amount' => 3000]);
    $db->update('tblinvoices', ['status' => 3], ['id' => $inv]);
    ok($m->sync_package_status($pkg) === 'pending', '30% مش كفاية');
    $db->insert('tblinvoicepaymentrecords', ['invoiceid' => $inv, 'amount' => 2000]);
    ok($m->sync_package_status($pkg) === 'active', '50% بتفعّل');
    ok(abs($m->package_paid_ratio($m->package($pkg)) - 0.5) < 0.001);
    $m->new_alerts = [];
    $m->publish_post($client2, 'design', 'https://instagram.com/p/d1');
    ok(count(q("SELECT id FROM tbldse_alerts WHERE alert_key = 'payment_due'")) === 0, '25% لسه');
    $m->publish_post($client2, 'design', 'https://instagram.com/p/d2');
    $a = q("SELECT * FROM tbldse_alerts WHERE alert_key = 'payment_due'");
    ok(count($a) === 1 && $a[0]['priority'] === 'urgent' && count($m->new_alerts) === 1, json_encode($a, JSON_UNESCAPED_UNICODE));
    ok(strpos($a[0]['message'], 'دافع 50%') !== false && strpos($a[0]['message'], '2 من 4 تصميم') !== false && strpos($a[0]['message'], '5,000 ج.م من 10,000 ج.م') !== false, $a[0]['message']);
    $m->publish_post($client2, 'reel', 'https://instagram.com/p/r1');
    $m->publish_post($client2, 'reel', 'https://instagram.com/p/r2');
    ok(count(q("SELECT id FROM tbldse_alerts WHERE alert_key = 'payment_due'")) === 1, 'مرة واحدة');
    ok(count($m->check_all_payments()) === 0);
    $o = q("SELECT destination FROM tbldse_wa_outbox WHERE clientid = ?", [$client2]);
    ok(count($o) === 4 && $o[0]['destination'] === '120363000000000002@g.us', 'كله على الجروب');
    $db->insert('tblinvoicepaymentrecords', ['invoiceid' => $inv, 'amount' => 5000]);
    $db->update('tblinvoices', ['status' => 2], ['id' => $inv]);
    $m->publish_post($client2, 'design', 'https://instagram.com/p/d3');
    ok(count(q("SELECT id FROM tbldse_alerts WHERE alert_key = 'payment_due'")) === 1, 'دفع الباقي');
    $m->cancel_package($pkg);
});

echo "\nالأقساط والترقية:\n";
test('مثال: 5000 (8 ريل + 4 تصميم) اتدفعوا ← ترقية لـ 20 ريل والسعر 13000 ← فاتورة الفرق على قسطين', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'كافيه الترقية', 'phonenumber' => '01099999999']);
    $cl = $db->insert_id();
    $m->save_client_wa($cl, ['wa_group_jid' => '120363000000000009@g.us']);
    $db->insert('tblinvoices', ['clientid' => $cl, 'total' => 5000, 'status' => 2]);
    $inv1 = $db->insert_id();
    $pkg = $m->create_package($cl, 'Basic', 5000, '2026-11-01', 30, ['reel' => 8, 'design' => 4], $inv1);
    ok($m->package($pkg)['status'] === 'active' && $m->package_paid_ratio($m->package($pkg)) == 1.0);
    // صوّر 6 ريل
    $st = $m->add_studio('استوديو الترقية');
    $sid = $m->book_session($cl, $st, '2026-11-03 12:00', 'reel', 6);
    $m->complete_session($sid, 6);
    // الترقية: مينفعش ينزل أقل من اللي اتستخدم
    throws(function () use ($m, $pkg) { $m->upgrade_package($pkg, ['reel' => 5, 'design' => 4], 5000); }, 'مينفعش أقل من 6');
    $db->insert('tblinvoices', ['clientid' => $cl, 'total' => 8000, 'status' => 1]);
    $inv2 = $db->insert_id();
    $m->new_alerts = [];
    ok($m->upgrade_package($pkg, ['reel' => 20, 'design' => 4], 13000, 0, $inv2, 'ترقية لـ 20 ريل') === 'upgrade');
    $b = $m->balance($pkg);
    ok($b['reel']['total'] == 20 && $b['reel']['used'] == 6 && $b['reel']['remaining'] == 14, json_encode($b));
    ok(abs($m->package_paid_amount($m->package($pkg)) - 5000) < 0.01);
    ok(count($m->package_invoices($pkg)) === 2 && $m->package_invoices($pkg)[1]['note'] === 'فرق الترقية');
    ok(count($m->package_events($pkg)) === 1);
    // دافع 5000/13000 = 38% والريلز 6/20 = 30% ← لسه مفيش تنبيه
    ok(count(q("SELECT id FROM tbldse_alerts WHERE alert_key = 'payment_due' AND clientid = ?", [$cl])) === 0, 'لسه');
    // القسط الأول من الفرق 3000 ← 8000/13000 = 62%
    $db->insert('tblinvoicepaymentrecords', ['invoiceid' => $inv2, 'amount' => 3000]);
    $db->update('tblinvoices', ['status' => 3], ['id' => $inv2]);
    ok(abs($m->package_paid_amount($m->package($pkg)) - 8000) < 0.01);
    // صوّر لحد 13 ريل (65%) ← تنبيه "الباقي 5000"
    $sid = $m->book_session($cl, $st, '2026-11-05 12:00', 'reel', 7);
    $m->complete_session($sid, 7);
    $a = q("SELECT message FROM tbldse_alerts WHERE alert_key = 'payment_due' AND clientid = ?", [$cl]);
    ok(count($a) === 1 && strpos($a[0]['message'], 'دافع 62%') !== false && strpos($a[0]['message'], 'الباقي 5,000 ج.م') !== false && strpos($a[0]['message'], '13 من 20 ريل') !== false, json_encode($a, JSON_UNESCAPED_UNICODE));
    // دفع الباقي ← مفيش تنبيهات تانية
    $db->insert('tblinvoicepaymentrecords', ['invoiceid' => $inv2, 'amount' => 5000]);
    $db->update('tblinvoices', ['status' => 2], ['id' => $inv2]);
    ok($m->package_paid_ratio($m->package($pkg)) == 1.0);
    ok(count($m->check_all_payments()) === 0);
    // نفس الفاتورة مينفعش تتربط مرتين
    throws(function () use ($m, $pkg, $inv2) { $m->link_invoice($pkg, $inv2); }, 'متربطة');
});
test('باقة جديدة على قسطين: بتتفعل بعد 50% من السعر', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'أقساط', 'phonenumber' => '']);
    $cl = $db->insert_id();
    $db->insert('tblinvoices', ['clientid' => $cl, 'total' => 3000, 'status' => 2]);
    $i1 = $db->insert_id();
    $pkg = $m->create_package($cl, 'X', 10000, '2026-11-01', 30, ['reel' => 10], $i1);
    ok($m->package($pkg)['status'] === 'pending', '30%');
    $db->insert('tblinvoices', ['clientid' => $cl, 'total' => 2000, 'status' => 2]);
    ok($m->link_invoice($pkg, $db->insert_id(), 'القسط التاني') === 'active', '50%');
});

echo "\nتصحيح الغلط:\n";
test('تعديل بيانات الباقة / مسح باقة اتعملت غلط / رجوع باقة اتلغت', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'غلطة', 'phonenumber' => '']);
    $cl = $db->insert_id();
    $db->insert('tblinvoices', ['clientid' => $cl, 'total' => 4000, 'status' => 2]);
    $inv = $db->insert_id();
    $pkg = $m->create_package($cl, 'غلط', 4000, '2026-11-01', 30, ['reel' => 5], $inv);
    // تعديل الاسم والتواريخ
    $m->edit_package($pkg, 'Starter', '2026-11-02', '2026-12-15', 'اتصلحت');
    $p = $m->package($pkg);
    ok($p['name'] === 'Starter' && $p['start_date'] === '2026-11-02' && $p['end_date'] === '2026-12-15', json_encode($p, JSON_UNESCAPED_UNICODE));
    throws(function () use ($m, $pkg) { $m->edit_package($pkg, 'x', '2026-12-01', '2026-11-01'); }, 'بعد البداية');
    // مسح: مفيش عليها شغل ← تتمسح والفاتورة تتفك
    ok(!$m->package_has_activity($pkg));
    $m->delete_package($pkg);
    throws(function () use ($m, $pkg) { $m->package($pkg); }, 'مش موجودة');
    ok(count(q('SELECT id FROM tbldse_package_invoices WHERE invoice_id = ?', [$inv])) === 0, 'الفاتورة اتفكت');
    // الفاتورة ترجع تتربط بباقة صح
    $pkg2 = $m->create_package($cl, 'صح', 4000, '2026-11-01', 30, ['reel' => 5], $inv);
    ok($m->package($pkg2)['status'] === 'active');
    // اتصوّر عليها ← مينفعش تتمسح، تتلغي وترجع
    $st  = $m->studios()[0]['id'];
    $sid = $m->book_session($cl, $st, '2026-11-12 09:00', 'reel', 1);
    throws(function () use ($m, $pkg2) { $m->edit_package($pkg2, 'صح', '2026-11-01', '2026-11-10'); }, 'خارج التواريخ');
    $m->complete_session($sid, 1);
    throws(function () use ($m, $pkg2) { $m->delete_package($pkg2); }, 'مينفعش تتمسح');
    $m->cancel_package($pkg2);
    ok($m->package($pkg2)['status'] === 'cancelled');
    ok($m->restore_package($pkg2) === 'active', 'رجعت');
});

test('صحّح العدد: الجلسة اتقفلت بعدد غلط ← الرصيد يتظبط والسبب يتسجل', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'تصحيح', 'phonenumber' => '']);
    $cl = $db->insert_id();
    $db->insert('tblinvoices', ['clientid' => $cl, 'total' => 1000, 'status' => 2]);
    $pkg = $m->create_package($cl, 'P', 1000, '2026-11-01', 30, ['reel' => 10], $db->insert_id());
    $st  = $m->studios()[0]['id'];
    $sid = $m->book_session($cl, $st, '2026-11-13 09:00', 'reel', 4);
    throws(function () use ($m, $sid) { $m->correct_session_qty($sid, 3, 'x'); }, 'اللي اتقفلت بس');
    $m->complete_session($sid, 8); // اتكتب 8 بدل 3
    ok($m->balance($pkg)['reel']['remaining'] == 2);
    throws(function () use ($m, $sid) { $m->correct_session_qty($sid, 3, ''); }, 'سبب التصحيح');
    throws(function () use ($m, $sid) { $m->correct_session_qty($sid, 11, 'x'); }, 'أكبر من رصيد');
    ok($m->correct_session_qty($sid, 3, 'اتكتب غلط') == 8);
    $b = $m->balance($pkg)['reel'];
    ok($b['used'] == 3 && $b['remaining'] == 7, json_encode($b));
    $s = $m->session($sid);
    ok($s['actual_qty'] == 3 && strpos($s['notes'], 'تصحيح العدد 8 ← 3: اتكتب غلط') !== false, $s['notes']);
    ok(count(q("SELECT id FROM tbldse_usage WHERE session_id = ? AND reason = 'session'", [$sid])) === 1, 'مفيش تكرار في الخصم');
    // تصحيح لفوق برضه
    $m->correct_session_qty($sid, 5, 'نسيت ريلين');
    ok($m->balance($pkg)['reel']['remaining'] == 5);
});

echo "\nالواتساب:\n";
test('مقفول: مفيش ولا رسالة بتتبعت', function () use ($m, &$sent) {
    $r = $m->process_outbox(50);
    ok($r['sent'] === 0 && count($sent) === 0);
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


echo "\nالدفعات (أقساط من غير حد) + التجديد:\n";
test('باقة من غير فاتورة: 4 أقساط · أول دفعة بتعمل فاتورة الباقة لوحدها · بتتفعل عند 50% · الباقة تبقى خالصة · أكتر من الباقي بيترفض', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'عيادة الأقساط']); $c = $db->insert_id();
    $pkg = $m->create_package($c, 'Growth', 8000, '2026-11-01', 30, ['reel' => 8, 'design' => 4]);
    ok($m->package($pkg)['status'] === 'pending');
    $r = $m->record_payment($pkg, 2000, '2026-11-01', 2, 'القسط الأول');
    ok($r['status'] === 'pending' && $r['left'] == 6000, json_encode($r));
    $inv = q('SELECT * FROM tblinvoices WHERE clientid = ?', [$c]);
    ok(count($inv) === 1 && (float) $inv[0]['total'] === 8000.0 && (int) $inv[0]['status'] === 3, json_encode($inv));
    $r = $m->record_payment($pkg, 2000, '2026-11-02');
    ok($r['status'] === 'active', 'اتفعلت عند 50%');
    $m->record_payment($pkg, 1500, '2026-11-02');
    $r = $m->record_payment($pkg, 2500, null, null, 'آخر قسط');
    ok($r['left'] == 0 && (int) q('SELECT status FROM tblinvoices WHERE clientid = ?', [$c])[0]['status'] === 2, json_encode($r));
    $pays = $m->package_payments($pkg);
    ok(count($pays) === 4 && $pays[0]['mode_name'] === 'فودافون كاش' && $pays[0]['note'] === 'القسط الأول' && $pays[1]['mode_name'] === 'كاش', json_encode($pays, JSON_UNESCAPED_UNICODE));
    ok(count(q('SELECT id FROM tblinvoices WHERE clientid = ?', [$c])) === 1, 'فاتورة واحدة بس');
    throws(function () use ($m, $pkg) { $m->record_payment($pkg, 100); }, 'أكبر من الباقي');
    throws(function () use ($m, $pkg) { $m->record_payment($pkg, 0); }, 'اكتب المبلغ');
    throws(function () use ($m, $pkg) { $m->record_payment($pkg, 1, '2026-12-30'); }, 'المستقبل');
});
test('باقة عليها فاتورة أقل من سعرها (بعد ترقية): الدفعة بتملا الفاتورة وبعدها فاتورة "باقي الباقة"', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'ترقية']); $c = $db->insert_id();
    $db->insert('tblinvoices', ['clientid' => $c, 'total' => 5000, 'status' => 1]); $i1 = $db->insert_id();
    $pkg = $m->create_package($c, 'Basic', 5000, '2026-11-01', 30, ['reel' => 8, 'design' => 4], $i1);
    $m->record_payment($pkg, 5000);
    $m->upgrade_package($pkg, ['reel' => 20, 'design' => 4], 13000, 0, null, 'ترقية');
    $r = $m->record_payment($pkg, 6000, null, null, 'فرق الترقية');
    $inv = q('SELECT total, status FROM tblinvoices WHERE clientid = ? ORDER BY id', [$c]);
    ok(count($inv) === 2 && (float) $inv[1]['total'] === 8000.0 && (int) $inv[1]['status'] === 3 && $r['left'] == 2000, json_encode([$inv, $r]));
    $m->record_payment($pkg, 2000);
    ok((int) q('SELECT status FROM tblinvoices WHERE clientid = ? ORDER BY id DESC', [$c])[0]['status'] === 2 && count($m->package_payments($pkg)) === 3);
});
test('حالة الصورة: باقة خلصت (Customize 15,000) ومدفوع منها دفعتين ← الدفعة التالتة بتتسجل · فترة سماح 15 يوم لو فيها رصيد', function () use ($m, $db) {
    $save = $m->fixed_time;
    $db->insert('tblclients', ['company' => 'Customize']); $c = $db->insert_id();
    $m->fixed_time = strtotime('2026-09-16 10:00:00');
    $pkg = $m->create_package($c, 'Customize', 15000, '2026-09-16', 30, ['reel' => 3, 'design' => 2]);
    $m->record_payment($pkg, 2000, '2026-09-16');
    $m->fixed_time = strtotime('2026-09-29 10:00:00');
    $m->record_payment($pkg, 5500, '2026-09-29');
    ok($m->package($pkg)['status'] === 'active');
    // بعد نهايتها (15/10) بأسبوع: لسه فيها 3 ريلز ← شغالة (فترة سماح)
    $m->fixed_time = strtotime('2026-10-22 10:00:00');
    ok($m->sync_package_status($pkg) === 'active' && (int) $m->active_package($c)['id'] === $pkg, 'فترة السماح');
    ok($m->effective_end($m->package($pkg)) === '2026-10-30');
    $st = q('SELECT id FROM tbldse_studios LIMIT 1')[0]['id'];
    $m->book_session($c, $st, '2026-10-25 12:00:00', 'reel', 1);
    throws(function () use ($m, $c, $st) { $m->book_session($c, $st, '2026-11-02 12:00:00', 'reel', 1); }, 'بفترة السماح');
    // بعد فترة السماح ← خلصت، والدفعة التالتة لسه بتتسجل
    $m->fixed_time = strtotime('2026-11-01 10:00:00');
    $db->query("UPDATE tbldse_sessions SET status = 'cancelled' WHERE package_id = ?", [$pkg]);
    ok($m->sync_package_status($pkg) === 'expired', 'بعد السماح');
    $r = $m->record_payment($pkg, 3000, '2026-11-01', null, 'القسط التالت');
    ok($r['left'] == 4500 && count($m->package_payments($pkg)) === 3, json_encode($r));
    $m->record_payment($pkg, 4500);
    ok(count($m->package_payments($pkg)) === 4 && $m->package_paid_amount($m->package($pkg)) == 15000);
    // باقة خلصت ومفيهاش رصيد ← بتقفل في ميعادها من غير سماح
    $m->fixed_time = strtotime('2026-09-01 10:00:00');
    $p2 = $m->create_package($c, 'Done', 1000, '2026-09-01', 10, ['design' => 1]);
    $m->record_payment($p2, 1000);
    $db->insert('tbldse_usage', ['package_id' => $p2, 'type' => 'design', 'qty' => 1, 'reason' => 'post', 'created_at' => '2026-09-05 10:00:00']);
    $m->fixed_time = strtotime('2026-09-12 10:00:00');
    ok($m->sync_package_status($p2) === 'expired', 'من غير رصيد');
    // باقة قديمة اتقفلت قبل التحديث ولسه في السماح وفيها رصيد ← بترجع شغالة
    $m->fixed_time = strtotime('2026-09-01 10:00:00');
    $p3 = $m->create_package($c, 'Old', 1000, '2026-09-01', 10, ['reel' => 2]);
    $m->record_payment($p3, 1000);
    $db->query("UPDATE tbldse_packages SET status = 'expired' WHERE id = ?", [$p3]);
    $m->fixed_time = strtotime('2026-09-15 10:00:00');
    $m->sync_client_packages($c);
    ok($m->package($p3)['status'] === 'active', 'رجعت شغالة');
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid = ?", [$c]); // ميأثرش على اختبارات التنبيهات
    $m->fixed_time = $save;
});
test('جدّد الاشتراك: نفس الخدمات والسعر والمدة · بيبدأ بعد القديمة · مستني الدفع · القديمة تكمل لآخر يوم · تقدر تغيّر السعر والعدد', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'تجديد']); $c = $db->insert_id();
    $old = $m->create_package($c, 'Growth', 8000, '2026-11-01', 30, ['reel' => 8, 'design' => 4]);
    $m->record_payment($old, 8000);
    $new = $m->renew_package($old);
    $n = $m->package($new);
    ok($n['start_date'] === '2026-12-01' && $n['end_date'] === '2026-12-30' && (float) $n['price'] === 8000.0 && $n['status'] === 'pending' && $n['name'] === 'Growth', json_encode($n, JSON_UNESCAPED_UNICODE));
    ok(array_map(function ($b) { return $b['total']; }, $m->balance($new)) == ['reel' => 8.0, 'design' => 4.0], json_encode($m->balance($new)));
    ok(strpos($n['notes'], 'تجديد') === 0 && q("SELECT event FROM tbldse_package_events WHERE package_id = ?", [$new])[0]['event'] === 'renew', 'event');
    $m->record_payment($new, 4000);
    ok($m->package($new)['status'] === 'active', 'الجديدة اتفعلت'); ok((int) $m->active_package($c)['id'] === $old, 'القديمة لسه هي الشغالة');
    $save = $m->fixed_time; $m->fixed_time = strtotime('2026-12-02 10:00:00');
    ok((int) $m->active_package($c)['id'] === $old, 'القديمة فيها 8 ريلز ← فترة سماح، ورصيدها بيتسحب الأول');
    $m->fixed_time = strtotime('2026-12-16 10:00:00');
    ok((int) $m->active_package($c)['id'] === $new && $m->package($old)['status'] === 'expired', 'بعد السماح الجديدة لوحدها');
    $m->fixed_time = $save;
    $x = $m->package($m->renew_package($new, ['price' => 9000, 'items' => ['reel' => 12, 'design' => ''], 'start_date' => '2026-12-31', 'validity_days' => 31]));
    ok((float) $x['price'] === 9000.0 && $x['end_date'] === '2027-01-30' && $m->balance($x['id'])['reel']['total'] === 12.0 && $m->balance($x['id'])['design']['total'] === 4.0, json_encode($x, JSON_UNESCAPED_UNICODE));
    $m->cancel_package($x['id']);
    throws(function () use ($m, $x) { $m->renew_package($x['id']); }, 'ملغية');
    throws(function () use ($m, $x) { $m->record_payment($x['id'], 10); }, 'اتلغت');
});

test('العملاء القدام: باقة قديمة (خلصت) ← "مستنيين تجديد" · طلب التجديد على الجروب · المتابعة · لما يجدد بيختفي · تنبيه للسيلز', function () use ($m, $db) {
    $save = $m->fixed_time;
    $db->insert('tblclients', ['company' => 'عميل قديم']); $c = $db->insert_id();
    throws(function () use ($m, $c) { $m->create_package($c, 'Old', 6000, '2026-10-25', 30, ['reel' => 6], null, null, true); }, 'لازم تكون خلصت');
    $old = $m->create_package($c, 'Growth', 6000, '2026-08-01', 30, ['reel' => 6, 'design' => 4], null, null, true);
    $p = $m->package($old);
    ok($p['status'] === 'expired' && (int) $p['historic'] === 1 && $m->balance($old)['reel']['remaining'] == 0, json_encode($p));
    $m->sync_client_packages($c);
    ok($m->package($old)['status'] === 'expired' && !$m->active_package($c), 'مبترجعش بفترة السماح');
    $r = array_values(array_filter($m->renewals(), function ($x) use ($c) { return $x['clientid'] === $c; }));
    ok(count($r) === 1 && $r[0]['ended'] && $r[0]['days'] === 64 && $r[0]['historic'] && $r[0]['renewal_status'] === '', json_encode($r, JSON_UNESCAPED_UNICODE));
    // طلب التجديد: من غير جروب ← error · بالجروب ← في الطابور بالنص + المتابعة بقت "اتكلمنا"
    throws(function () use ($m, $c) { $m->send_manual($c, 'renewal_request'); }, 'مالوش جروب');
    $m->save_client_wa($c, ['wa_group_jid' => '120363000000000099@g.us']);
    $m->send_manual($c, 'renewal_request');
    $o = q("SELECT body FROM tbldse_wa_outbox WHERE clientid = ? AND msg_key = 'renewal_request'", [$c]);
    ok(count($o) === 1 && strpos($o[0]['body'], 'باقة *Growth* خلصت يوم') !== false && strpos($o[0]['body'], 'عميل قديم') !== false, $o[0]['body'] ?? '');
    $r = array_values(array_filter($m->renewals(), function ($x) use ($c) { return $x['clientid'] === $c; }))[0];
    ok($r['renewal_status'] === 'contacted' && $r['last_msg'], json_encode($r, JSON_UNESCAPED_UNICODE));
    // "مش هيجدد" ← بيختفي من المفتوحين وبيظهر في lost
    $m->set_renewal_status($c, 'lost', 'راح لوكالة تانية');
    ok(!array_filter($m->renewals('open'), function ($x) use ($c) { return $x['clientid'] === $c; }) && count(array_filter($m->renewals('lost'), function ($x) use ($c) { return $x['clientid'] === $c; })) === 1);
    $m->set_renewal_status($c, 'later');
    // باقة قربت تخلص (خلال 7 أيام) بتظهر · باقة خلصت من 5 أيام ← تنبيه مرة واحدة (القديمة اللي اتضافت بإيدك لأ)
    $db->insert('tblclients', ['company' => 'قربت تخلص']); $c2 = $db->insert_id();
    $m->fixed_time = strtotime('2026-10-01 10:00:00');
    $p2 = $m->create_package($c2, 'Starter', 4000, '2026-10-06', 30, ['reel' => 4]);
    $m->record_payment($p2, 4000);
    $m->fixed_time = strtotime('2026-11-01 10:00:00');
    $r2 = array_values(array_filter($m->renewals(), function ($x) use ($c2) { return $x['clientid'] === $c2; }));
    ok(count($r2) === 1 && !$r2[0]['ended'] && $r2[0]['days'] === -3, json_encode($r2));
    $m->fixed_time = strtotime('2026-11-09 10:00:00');
    $db->insert('tbldse_usage', ['package_id' => $p2, 'type' => 'reel', 'qty' => 4, 'reason' => 'manual', 'created_at' => '2026-11-01 10:00:00']);
    $a = $m->check_renewals();
    ok(count(array_filter($a, function ($id) use ($c2) { return q('SELECT clientid FROM tbldse_alerts WHERE id = ?', [$id])[0]['clientid'] == $c2; })) === 1, 'تنبيه');
    ok(!array_filter($a, function ($id) use ($c) { return q('SELECT clientid FROM tbldse_alerts WHERE id = ?', [$id])[0]['clientid'] == $c; }), 'القديمة من غير تنبيه');
    ok(count($m->check_renewals()) === 0, 'مرة واحدة');
    // جدّد ← بيختفي
    $m->renew_package($p2);
    ok(!array_filter($m->renewals(''), function ($x) use ($c2) { return $x['clientid'] === $c2; }), 'جدد ← اختفى');
    $m->renew_package($old);
    ok(!array_filter($m->renewals(''), function ($x) use ($c) { return $x['clientid'] === $c; }), 'القديم جدد ← اختفى');
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid IN (?, ?)", [$c, $c2]);
    $m->fixed_time = $save;
});
echo "\nالتنبيهات:\n";
test('العميل بقاله فترة مابيصوّرش → تنبيه مرة واحدة، urgent لو مفيش نشر', function () use ($m, $client, $db) {
    ok(count($m->check_inactive_clients()) === 0, 'لسه بادئ');
    $m->fixed_time = strtotime('2026-11-27 10:00:00');
    $new = $m->check_inactive_clients();
    $mine = array_values(array_filter($new, function ($id) use ($client) { return q('SELECT clientid FROM tbldse_alerts WHERE id = ?', [$id])[0]['clientid'] == $client; }));
    ok(count($mine) === 1, count($mine));
    $a = q('SELECT * FROM tbldse_alerts WHERE id = ?', [$mine[0]])[0];
    ok($a['priority'] === 'urgent' && strpos($a['message'], 'بقاله 22 يوم') !== false && strpos($a['message'], 'المحتوى خلص') !== false, $a['message']);
    ok(count($m->check_inactive_clients()) === 0, 'مرة واحدة');
    throws(function () use ($m, $a) { $m->resolve_alert($a['id'], ''); }, 'حصل إيه');
    $m->resolve_alert($a['id'], 'كلمته وحجز');
    ok(count(array_filter($m->alerts(true), function ($x) { return $x['alert_key'] === 'client_inactive' && $x['clientid'] == $GLOBALS['client']; })) === 0, 'اتقفل');
});
test('الباقة بتخلص لوحدها بعد مدتها — ولو فيها رصيد بعد 15 يوم سماح', function () use ($m, $pkg) {
    $m->fixed_time = strtotime('2026-12-01 10:00:00');
    $m->sync_all_packages();
    ok($m->package($pkg)['status'] === 'active', 'فيها رصيد ← سماح');
    $m->fixed_time = strtotime('2026-12-16 10:00:00');
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
$CIStub = new class { public $dse_studio_model; public $load; public $db; };
$CIStub->db = $db;
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
test('كل الصفحات بتترسم من غير أخطاء', function () use ($m, $client, $db) {
    $sum = $m->client_summary($client);
    $balances = [];
    $pi = $pe = $pp = $pb = [];
    foreach ($sum['packages'] as $p) { $balances[$p['id']] = $m->balance($p['id']); $pi[$p['id']] = $m->package_invoices($p['id']); $pe[$p['id']] = $m->package_events($p['id']); $pp[$p['id']] = $m->package_paid_amount($p); $pb[$p['id']] = $m->package_has_activity($p['id']); }
    render('client', $sum + ['studios' => $m->studios(), 'staff' => [['staffid' => 7, 'firstname' => 'أندرو', 'lastname' => 'إسحاق']],
        'sessions' => $m->client_sessions($client), 'scripts' => $m->client_scripts($client), 'posts' => $m->client_posts($client),
        'outbox' => $m->outbox(20, $client), 'invoices' => [['id' => 1, 'prefix' => 'INV-', 'number' => 5, 'total' => 15000, 'status' => 2, 'date' => '2026-11-01']],
        'balances' => $balances, 'pkg_invoices' => $pi, 'pkg_events' => $pe, 'pkg_paid' => $pp, 'pkg_busy' => $pb, 'free_invoices' => []]);
    // صفحة عميل اترقّى
    $up = q("SELECT clientid FROM tbldse_packages WHERE name = 'Basic'")[0]['clientid'];
    $db->query("UPDATE tbldse_packages SET status = 'active' WHERE name = 'Basic'");
    $m->fixed_time = strtotime('2026-11-10 10:00:00');
    $sum = $m->client_summary($up);
    $pi = $pe = $pp = $pb = $balances = [];
    foreach ($sum['packages'] as $p) { $balances[$p['id']] = $m->balance($p['id']); $pi[$p['id']] = $m->package_invoices($p['id']); $pe[$p['id']] = $m->package_events($p['id']); $pp[$p['id']] = $m->package_paid_amount($p); $pb[$p['id']] = $m->package_has_activity($p['id']); }
    $html = render('client', $sum + ['studios' => $m->studios(), 'staff' => [], 'sessions' => $m->client_sessions($up), 'scripts' => [], 'posts' => [],
        'outbox' => [], 'invoices' => [], 'balances' => $balances, 'pkg_invoices' => $pi, 'pkg_events' => $pe, 'pkg_paid' => $pp, 'pkg_busy' => $pb, 'free_invoices' => [], 'pay_modes' => $m->payment_modes(), 'pkg_payments' => array_map(function ($p) use ($m) { return $m->package_payments($p['id']); }, array_column($sum['packages'], null, 'id'))]);
    file_put_contents(sys_get_temp_dir() . '/dse_client.html', $html);
    ok(strpos($html, 'سجّل الدفعة') !== false && strpos($html, 'جدّد الاشتراك') !== false, 'زرار الدفعة والتجديد');
    ok(strpos($html, 'اربط فاتورة عملتها') !== false && strpos($html, 'ترقية / تعديل الباقة') !== false && strpos($html, 'احفظ الترقية') !== false, 'الزراير على الباقة الشغالة');
    $m->fixed_time = strtotime('2026-11-02 10:00:00');
    ok(strpos($html, 'ترقية') !== false && strpos($html, 'ريلز 8←20') !== false && strpos($html, 'فرق الترقية') !== false, 'تاريخ الترقية');
    render('index', ['clients' => [['userid' => 1, 'company' => 'x']], 'packages' => $m->clients_with_packages(),
        'today' => $m->sessions_between('2026-11-01', '2026-12-01'), 'alerts' => 2]);
    $cal = render('calendar', ['start' => '2026-10-31', 'studio' => 0, 'cancelled' => true, 'studios' => $m->studios(),
        'sessions' => $m->sessions_between('2026-10-31', '2026-11-07')]);
    file_put_contents(sys_get_temp_dir() . '/dse_calendar.html', $cal);
    ok(strpos($cal, 'مطعم الشيف') !== false && strpos($cal, '📷 أندرو إسحاق') !== false, 'الاسم والمصور');
    ok(count($m->sessions_between('2026-10-31', '2026-11-30', null, 999)) === 0, 'فلتر الاستوديو');
    ok(count($m->sessions_between('2026-10-31', '2026-11-30', null, null, false)) < count($m->sessions_between('2026-10-31', '2026-11-30')), 'من غير الملغية');
    render('alerts', ['all' => true, 'alerts' => $m->alerts(false)]);
    $db->insert('tblclients', ['company' => 'عيادة د. سامي']); $rc = $db->insert_id();
    $m->create_package($rc, 'Growth', 6000, '2026-08-01', 30, ['reel' => 6], null, null, true);
    $m->save_client_wa($rc, ['wa_group_jid' => '120363000000000077@g.us']);
    $m->send_manual($rc, 'renewal_request');
    $rn = render('renewals', ['rows' => $m->renewals(''), 'status' => '']);
    ok(strpos($rn, 'عيادة د. سامي') !== false && strpos($rn, 'ابعت طلب التجديد') !== false && strpos($rn, 'آخر طلب') !== false && strpos($rn, 'قديمة') !== false, 'صف التجديد');
    file_put_contents(sys_get_temp_dir() . '/dse_renewals.html', $rn);
    render('outbox', ['outbox' => $m->outbox(), 'enabled' => false]);
    $pub = render('publishing', ['clients' => $m->clients_for_publishing(), 'recent' => $m->recent_posts(), 'selected' => 1]);
    ok(strpos($pub, 'وصل الجروب') !== false || strpos($pub, 'مستني') !== false, 'حالة الرسالة');
    ok(strpos($pub, '⚠️ مفيش جروب') !== false, 'العملاء من غير جروب متعلّمين');
    render('settings', ['studios' => $m->studios(false), 'staff' => [['staffid' => 1, 'firstname' => 'Ibrahim']], 'state' => 'open']);
    $client = (object) ['userid' => 1];
    render('customer_tab', ['client' => $client]);
});
restore_error_handler();

echo "\nتحديث النسخة:\n";
test('لو Perfex ماشغّلش الـ migration: الجداول الناقصة بتتعمل لوحدها والفواتير القديمة بتتنقل', function () use ($m, $db) {
    global $OPTIONS;
    // نسخة 1.0.0: الفاتورة كانت على الباقة نفسها
    $db->insert('tblinvoices', ['clientid' => 1, 'total' => 1000, 'status' => 2]);
    $old_inv = $db->insert_id();
    $db->query('UPDATE tbldse_packages SET invoice_id = ? WHERE id = (SELECT MIN(id) FROM tbldse_packages)', [$old_inv]);
    $db->pdo->exec('DROP TABLE tbldse_package_invoices');
    $db->pdo->exec('DROP TABLE tbldse_package_events');
    unset($OPTIONS['dse_schema_version']);
    dse_studio_ensure_schema();
    ok($db->table_exists('tbldse_package_invoices') && $db->table_exists('tbldse_package_events'), 'اتعملت');
    ok($OPTIONS['dse_schema_version'] === DSE_STUDIO_SCHEMA_VERSION);
    dse_studio_ensure_schema(); // مرة تانية مبتعملش حاجة
    ok(count(q('SELECT id FROM tbldse_package_invoices WHERE invoice_id = ?', [$old_inv])) === 1, 'الفاتورة القديمة اتنقلت');
    ok(count(q('SELECT id FROM tbldse_package_invoices')) === 1, 'مفيش تكرار');
});


echo "\nرسايل الواتساب (تتعدّل):\n";
test('النص الأصلي زي ما كان · التعديل بيمشي على الرسايل الجاية · السطر اللي متغيراته فاضية بيتشال · رجوع للأصلي · متغير غلط بيترفض', function () use ($m) {
    $p = ['start' => '2026-11-03 17:00:00', 'qty' => 3, 'type' => 'reel', 'studio' => '', 'outfits' => 0];
    ok(dse_wa_text('session_booked', $p) === "تم حجز ميعاد التصوير ✅\n📅 " . dse_day('2026-11-03 17:00:00') . ' الساعة ' . dse_time('2026-11-03 17:00:00') . "\n🎬 3 ريل", dse_wa_text('session_booked', $p));
    update_option('dse_wa_tpl_session_booked', "يا {client_name} 👋\n{headline}\nيوم {date} الساعة {time}\nفي {studio}\nهات معاك: {outfits}");
    $t = dse_wa_text('session_booked', $p + ['client_name' => 'مطعم الشيف']);
    ok($t === "يا مطعم الشيف 👋\nتم حجز ميعاد التصوير\nيوم " . dse_day('2026-11-03 17:00:00') . ' الساعة ' . dse_time('2026-11-03 17:00:00'), $t);
    try { dse_wa_validate('session_booked', 'الساعة {وقت}'); ok(false); } catch (Exception $e) { ok(strpos($e->getMessage(), 'متغير مش معروف: {وقت}') === 0, $e->getMessage()); }
    try { dse_wa_validate('session_booked', '  '); ok(false); } catch (Exception $e) { ok(strpos($e->getMessage(), 'فاضية') !== false); }
    ok(dse_wa_validate('published', '{headline} {post_url} {balance}') === true);
    update_option('dse_wa_tpl_session_booked', '');
    ok(strpos(dse_wa_text('session_booked', $p), 'تم حجز ميعاد التصوير ✅') === 0, 'رجع للأصلي');
    // الصفحة
    $list = [];
    foreach (dse_wa_defaults() as $k => $def) {
        $list[$k] = ['label' => dse_wa_labels()[$k], 'auto' => !isset(dse_manual_keys()[$k]), 'body' => dse_wa_template($k), 'default' => $def, 'edited' => false, 'vars' => dse_wa_vars($k), 'preview' => dse_wa_text($k, dse_wa_sample($k))];
    }
    set_error_handler(function ($no, $str, $file, $line) { throw new ErrorException($str . ' in ' . basename($file) . ':' . $line); });
    $h = render('messages', ['list' => $list]);
    restore_error_handler();
    file_put_contents(sys_get_temp_dir() . '/dse_messages.html', $h);
    ok(substr_count($h, '<textarea name="body"') === 10 && strpos($h, 'تم حجز') !== false && strpos($h, 'DSE_SAMPLE') !== false && strpos($h, '{client_name}') !== false);
});
echo "\n$passed passed · $failed failed\n";
exit($failed ? 1 : 0);
