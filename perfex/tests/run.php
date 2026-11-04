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
$db->pdo->exec('CREATE TABLE tblitemable (id INTEGER PRIMARY KEY AUTOINCREMENT, rel_id INT, rel_type TEXT, description TEXT, long_description TEXT, qty REAL, rate REAL, item_order INT)');
$db->pdo->exec('CREATE TABLE tblexpenses (id INTEGER PRIMARY KEY AUTOINCREMENT, category INT, amount REAL, date TEXT, expense_name TEXT)');
$db->pdo->exec('CREATE TABLE tblexpenses_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
$db->pdo->exec('CREATE TABLE tblpayment_modes (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INT)');
$db->pdo->exec("INSERT INTO tblpayment_modes (name, active) VALUES ('كاش', 1), ('فودافون كاش', 1), ('قديمة', 0)");
$db->pdo->exec('CREATE TABLE tblinvoices (id INTEGER PRIMARY KEY AUTOINCREMENT, clientid INT, total REAL, status INT, prefix TEXT DEFAULT "INV-", number INT DEFAULT 1, date TEXT, currency INT DEFAULT 3)');
$db->pdo->exec('CREATE TABLE tblcurrencies (id INTEGER PRIMARY KEY, name TEXT, symbol TEXT, isdefault INT)');
$db->pdo->exec("INSERT INTO tblcurrencies VALUES (1, 'USD', '$', 1), (2, 'EUR', '€', 0), (3, 'EGP', 'LE', 0)");

$sent = [];
$evo  = new Dse_evolution();
$evo->transport = function ($method, $url, $body) use (&$sent) {
    if (strpos($url, '/message/sendText/') !== false) {
        if (strpos($body['text'], 'FAIL') !== false) { return [500, ['error' => 'boom']]; }
        $sent[] = $body;
        return [201, ['key' => ['id' => 'x']]];
    }
    if (strpos($url, '/instance/connectionState/') !== false) {
        if (($GLOBALS['evo_state'] ?? 'open') === 'down') { return [502, ['error' => 'bad gateway']]; }
        return [200, ['instance' => ['state' => $GLOBALS['evo_state'] ?? 'open']]];
    }
    if (strpos($url, '/group/create/') !== false) { $GLOBALS['group_members'] = $body['participants']; return [201, ['id' => '120363000000000001@g.us']]; }
    if (strpos($url, '/group/inviteCode/') !== false) { return [200, ['inviteCode' => 'AbCdEfGhIj12']]; }
    if (strpos($url, '/group/fetchAllGroups/') !== false) { return [200, [['id' => '120363000000000888@g.us', 'subject' => 'عيادات الشهيد × DS', 'size' => 6], ['id' => '120363000000000777@g.us', 'subject' => 'جروب تاني', 'size' => 3], ['id' => '2010@s.whatsapp.net', 'subject' => 'مش جروب']]]; }
    if (!empty($GLOBALS['evo_join_fails']) && (strpos($url, '/group/acceptInviteCode/') !== false || strpos($url, '/group/inviteInfo/') !== false)) { return [403, ['error' => 'not-authorized']]; }
    if (strpos($url, '/group/acceptInviteCode/') !== false) { return [200, ['accepted' => true, 'groupJid' => '120363000000000555@g.us']]; }
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
    // مدّ فترة السماح للعميل ده بس (أسبوع): خلصت 30/10 والنهارده 01/11 ← من النهارده لحد 08/11 · شغالة تاني والحجز ماشي · مفيش واتساب
    throws(function () use ($m, $pkg) { $m->extend_grace($pkg, 0); }, 'اختار المدة');
    $wa = count(q('SELECT id FROM tbldse_wa_outbox'));
    $end = $m->extend_grace($pkg, 7, 'العميل كان مسافر');
    ok($end === '2026-11-08' && $m->package($pkg)['status'] === 'active' && (int) $m->package($pkg)['grace_days'] === 24, json_encode($m->package($pkg)));
    ok(count(q('SELECT id FROM tbldse_wa_outbox')) === $wa, 'مفيش رسالة للعميل');
    $ev = q("SELECT * FROM tbldse_package_events WHERE package_id = ? AND event = 'grace'", [$pkg]);
    ok(count($ev) === 1 && $ev[0]['old_end_date'] === '2026-10-30' && $ev[0]['new_end_date'] === '2026-11-08' && $ev[0]['note'] === 'العميل كان مسافر');
    $m->book_session($c, $st, '2026-11-05 12:00:00', 'reel', 1);
    throws(function () use ($m, $c, $st) { $m->book_session($c, $st, '2026-11-10 12:00:00', 'reel', 1); }, '08/11 (بفترة السماح)');
    ok($m->extend_grace($pkg, 14) === '2026-11-22', 'أسبوعين كمان من آخر يوم سماح');
    ok($m->grace_days() === 15, 'الباقي على الإعداد العام');
    $db->query("UPDATE tbldse_sessions SET status = 'cancelled' WHERE package_id = ?", [$pkg]);
    // باقة خلصت ومفيهاش رصيد ← بتقفل في ميعادها من غير سماح
    $m->fixed_time = strtotime('2026-09-01 10:00:00');
    $p2 = $m->create_package($c, 'Done', 1000, '2026-09-01', 10, ['design' => 1]);
    $m->record_payment($p2, 1000);
    $db->insert('tbldse_usage', ['package_id' => $p2, 'type' => 'design', 'qty' => 1, 'reason' => 'post', 'created_at' => '2026-09-05 10:00:00']);
    $m->fixed_time = strtotime('2026-09-12 10:00:00');
    ok($m->sync_package_status($p2) === 'expired', 'من غير رصيد');
    throws(function () use ($m, $p2) { $m->extend_grace($p2, 10); }, 'مفيهاش رصيد');
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
    ok($p['status'] === 'expired' && (int) $p['historic'] === 1 && $m->balance($old)['reel']['remaining'] == 0 && $m->balance($old)['design']['remaining'] == 0, json_encode($p));
    // فواتير ودفعات على الباقة القديمة: بتتسجل وبتتجمع، والباقة بتفضل خلصت ومن غير تنبيه دفع
    $alerts = count(q('SELECT id FROM tbldse_alerts'));
    $r = $m->record_payment($old, 2000, '2026-08-01', null, 'دفعة قديمة');
    $db->insert('tblinvoices', ['clientid' => $c, 'total' => 1500, 'status' => 2, 'date' => '2026-08-15']); $oi = $db->insert_id();
    $m->link_invoice($old, $oi, 'فاتورة قديمة');
    $p = $m->package($old);
    ok($m->package_paid_amount($p) == 3500 && $r['left'] == 4000 && $p['status'] === 'expired' && count($m->package_payments($old)) === 1, json_encode([$m->package_paid_amount($p), $r, $p['status']]));
    ok(count(q('SELECT id FROM tbldse_alerts')) === $alerts, 'مفيش تنبيه دفع على القديمة');
    $m->unlink_invoice($old, $oi);
    ok($m->package_paid_amount($m->package($old)) == 2000);
    // مدّ السماح على الباقة القديمة: لازم يكتب فاضله كام · بترجع شغالة برصيد 2 ريل لحد أسبوعين من النهارده
    throws(function () use ($m, $old) { $m->extend_grace($old, 14); }, 'فاضله كام');
    $wa = count(q('SELECT id FROM tbldse_wa_outbox'));
    $e = $m->extend_grace($old, 14, '', ['reel' => 2, 'design' => '']);
    $b = $m->balance($old);
    ok($e === '2026-11-16' && $m->package($old)['status'] === 'active' && $b['reel']['remaining'] == 2 && $b['design']['remaining'] == 0 && count(q('SELECT id FROM tbldse_wa_outbox')) === $wa, json_encode([$e, $b, $m->package($old)['status']]));
    ok(strpos(q("SELECT note FROM tbldse_package_events WHERE package_id = ? AND event = 'grace'", [$old])[0]['note'], '2 ريل') !== false);
    $db->query("UPDATE tbldse_packages SET status = 'expired', grace_days = NULL WHERE id = ?", [$old]); // نرجّعها زي ما كانت لباقي الاختبار
    $db->query("UPDATE tbldse_usage SET qty = 6 WHERE package_id = ? AND type = 'reel'", [$old]);
    $noitems = $m->create_package($c, 'من غير عدد', 3000, '2026-07-01', 30, [], null, null, true);
    $e2 = $m->extend_grace($noitems, 7, '', ['reel' => 3]);
    ok($m->balance($noitems)['reel']['remaining'] == 3 && $m->package($noitems)['status'] === 'active', 'القديمة من غير عدد ← الرصيد اتضاف');

    $db->query('DELETE FROM tbldse_packages WHERE id = ?', [$noitems]);
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

test('مسح: الباقة القديمة بتتمسح (الخصم الوهمي مش شغل) · امسح العميل من الاستوديو بكود التأكيد وPerfex زي ما هو', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'اتضاف غلط']); $c = $db->insert_id();
    $h = $m->create_package($c, 'Old', 5000, '2026-08-01', 30, ['reel' => 6], null, null, true);
    ok(!$m->package_has_activity($h), 'القديمة ينفع تتمسح');
    $m->delete_package($h);
    ok(!q('SELECT id FROM tbldse_packages WHERE id = ?', [$h]) && !q('SELECT id FROM tbldse_usage WHERE package_id = ?', [$h]));
    $h = $m->create_package($c, 'Old', 5000, '2026-08-01', 30, ['reel' => 6], null, null, true);
    $n = $m->create_package($c, 'New', 3000, '2026-11-01', 30, ['reel' => 4]);
    $m->record_payment($n, 3000);
    $m->save_client_wa($c, ['wa_group_jid' => '120363000000000111@g.us']);
    $st = q('SELECT id FROM tbldse_studios LIMIT 1')[0]['id'];
    $m->book_session($c, $st, '2026-11-20 12:00:00', 'reel', 1);
    $code = dse_client_code($c);
    throws(function () use ($m, $c) { $m->delete_client_data($c, 'غلط'); }, 'اكتب كود العميل');
    $cnt = $m->delete_client_data($c, strtolower($code));
    ok($cnt['packages'] === 2 && $cnt['sessions'] === 1 && $cnt['messages'] >= 1, json_encode($cnt));
    foreach (['dse_packages', 'dse_sessions', 'dse_wa_outbox', 'dse_clients', 'dse_alerts'] as $t) { ok(!q("SELECT * FROM tbl$t WHERE clientid = ?", [$c]), $t); }
    ok(q('SELECT userid FROM tblclients WHERE userid = ?', [$c]) && q('SELECT id FROM tblinvoices WHERE clientid = ?', [$c]), 'العميل وفواتيره في Perfex موجودين');
    ok(!q('SELECT id FROM tbldse_package_items WHERE package_id IN (?, ?)', [$h, $n]));
});
test('ربط جروب لعميل باقته خلصت: رقم السيستم مش في الجروب ← بيدخل باللينك ويتربط · أو بالـ JID على طول', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'جروب قديم']); $c = $db->insert_id();
    $m->create_package($c, 'Old', 5000, '2026-08-01', 30, ['reel' => 6], null, null, true);
    $jid = $m->link_group($c, 'https://chat.whatsapp.com/AbCdEfGhIj12XyZ');
    ok($jid === '120363000000000555@g.us' && $m->client($c)['wa_group_jid'] === $jid, $jid);
    $db->query('DELETE FROM tbldse_clients WHERE clientid = ?', [$c]);
    ok($m->link_group($c, '120363000000000777@g.us') === '120363000000000777@g.us');
    throws(function () use ($m, $c) { $m->link_group($c, 'مش لينك'); }, 'مش لينك جروب');
    // اختار من جروبات رقم السيستم: الجروبات بس (مش الشاتات) · المربوط بعميل تاني متعلّم ومينفعش يتربط مرتين
    $g = $m->system_groups();
    ok(count($g) === 2 && $g[0]['subject'] === 'جروب تاني' && (int) $g[0]['linked']['clientid'] === $c && $g[1]['linked'] === null, json_encode($g, JSON_UNESCAPED_UNICODE));
    $db->insert('tblclients', ['company' => 'عيادات الشهيد']); $c2 = $db->insert_id();
    ok($m->link_group($c2, '120363000000000777@g.us') === null && strpos($m->client($c2)['wa_link_error'], 'مربوط بعميل تاني') !== false, 'جروب عميل تاني');
    $m->link_group($c2, '120363000000000888@g.us');
    ok($m->client($c2)['wa_group_jid'] === '120363000000000888@g.us');
    $GLOBALS['pick'] = [$m->client($c2), $m->system_groups()];
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid = ?", [$c]);
});

test('حط اللينك وبس: لو رقم السيستم مش قادر يدخل دلوقتي اللينك بيتحفظ، والرسايل بتستنى، وأول ما يدخل بيتربط ويتبعت لوحده', function () use ($m, $db, &$sent) {
    $db->insert('tblclients', ['company' => 'لينك بس']); $c = $db->insert_id();
    throws(function () use ($m, $c) { $m->link_group($c, 'https://example.com/abc'); }, 'مش لينك جروب واتساب');
    $GLOBALS['evo_join_fails'] = true;
    $r = $m->link_group($c, 'https://chat.whatsapp.com/KxYz1234567890abc?mode=ac_t');
    $cl = $m->client($c);
    ok($r === null && $cl['wa_group_link'] === 'https://chat.whatsapp.com/KxYz1234567890abc' && !$cl['wa_group_jid'] && strpos($cl['wa_link_error'], 'مقدرتش أوصل للجروب') === 0, json_encode($cl, JSON_UNESCAPED_UNICODE));
    $id = $m->send_manual($c, 'welcome'); // مبيرميش "مالوش جروب"
    ok(q('SELECT destination, status FROM tbldse_wa_outbox WHERE id = ?', [$id])[0] === ['destination' => 'pending', 'status' => 'queued']);
    update_option('dse_wa_enabled', '1');
    $res = $m->process_outbox(10);
    ok(q('SELECT status FROM tbldse_wa_outbox WHERE id = ?', [$id])[0]['status'] === 'queued' && $res['waiting'] >= 1, 'لسه مستنية');
    $GLOBALS['evo_join_fails'] = false; // رقم السيستم دخل الجروب
    $before = count($sent);
    $m->process_outbox(10);
    $o = q('SELECT destination, status FROM tbldse_wa_outbox WHERE id = ?', [$id])[0];
    ok($o['destination'] === '120363000000000555@g.us' && $o['status'] === 'sent' && count($sent) > $before && $m->client($c)['wa_group_jid'] === '120363000000000555@g.us' && !$m->client($c)['wa_link_error'], json_encode($o));
    $db->query('DELETE FROM tbldse_clients WHERE clientid = ?', [$c]);
});

test('مين محتاج ييجي يصوّر: الجاهز = اتصوّر − نزل · معدل النشر · بقاله أسبوع · مع ملحوظة السكربت والـ B-roll · مرة في الأسبوع · الباقة اللي هتخلص', function () use ($m, $db) {
    $save = $m->fixed_time;
    $m->fixed_time = strtotime('2026-10-29 10:00:00');
    $st = q('SELECT id FROM tbldse_studios LIMIT 1')[0]['id'];
    $sess = function ($c, $p, $qty, $day, $status = 'done') use ($db, $st) {
        $db->insert('tbldse_sessions', ['clientid' => $c, 'package_id' => $p, 'studio_id' => $st, 'type' => 'reel', 'qty' => $qty, 'actual_qty' => $status === 'done' ? $qty : null,
            'start_at' => $day . ' 12:00:00', 'end_at' => $day . ' 13:00:00', 'status' => $status, 'created_at' => $day . ' 09:00:00']);
    };
    $post = function ($c, $n, $day) use ($db) { for ($i = 0; $i < $n; $i++) { $db->insert('tbldse_posts', ['clientid' => $c, 'type' => 'reel', 'post_url' => 'https://x/' . $c . $day . $i, 'published_at' => $day . ' 18:00:00']); } };
    // (أ) اتصوّر 4 ونزلوا 4 ← مفيش جاهز ← urgent
    $db->insert('tblclients', ['company' => 'خلص محتواه']); $a = $db->insert_id();
    $pa = $m->create_package($a, 'G', 8000, '2026-10-01', 60, ['reel' => 10]); $m->record_payment($pa, 8000, '2026-10-01');
    $sess($a, $pa, 4, '2026-10-26'); $post($a, 4, '2026-10-27');
    // (ب) اتصوّر 6 ونزل 2 (في 4 أسابيع) وصوّر من 3 أيام ← كفاية ← مفيش تنبيه
    $db->insert('tblclients', ['company' => 'عنده محتوى']); $b = $db->insert_id();
    $pb = $m->create_package($b, 'G', 8000, '2026-10-01', 60, ['reel' => 10]); $m->record_payment($pb, 8000, '2026-10-01');
    $sess($b, $pb, 6, '2026-10-26'); $post($b, 2, '2026-10-20');
    // (ج) مصوّرش من 10 أيام بس عنده ميعاد جاي ← مفيش تنبيه
    $db->insert('tblclients', ['company' => 'جاي يصوّر']); $cc = $db->insert_id();
    $pc = $m->create_package($cc, 'G', 8000, '2026-10-01', 60, ['reel' => 10]); $m->record_payment($pc, 8000, '2026-10-01');
    $sess($cc, $pc, 2, '2026-10-19'); $sess($cc, $pc, 2, '2026-11-02', 'booked');
    $rw = []; foreach ($m->content_runway() as $r) { $rw[$r['clientid']] = $r; }
    ok($rw[$a]['ready'] == 0 && $rw[$b]['ready'] == 4 && $rw[$b]['rate'] == 0.5 && $rw[$b]['days_left'] === 56 && $rw[$cc]['next_session'], json_encode([$rw[$a], $rw[$b]], JSON_UNESCAPED_UNICODE));
    $new = $m->check_content_runway();
    $mine = function ($ids, $c) { return array_values(array_filter($ids, function ($id) use ($c) { return q('SELECT clientid FROM tbldse_alerts WHERE id = ?', [$id])[0]['clientid'] == $c; })); };
    ok(count($mine($new, $a)) === 1 && !$mine($new, $b) && !$mine($new, $cc), 'بس اللي خلص محتواه');
    $al = q('SELECT * FROM tbldse_alerts WHERE id = ?', [$mine($new, $a)[0]])[0];
    ok($al['alert_key'] === 'no_content' && $al['priority'] === 'urgent' && strpos($al['message'], 'مفيش ولا ريل متصوّر جاهز') !== false && strpos($al['message'], 'B-roll') !== false && strpos($al['message'], 'يسجّل عليه فويس') !== false, $al['message']);
    ok(!$mine($m->check_content_runway(), $a), 'مرة في الأسبوع');
    // بعد أسبوع: (ب) بقاله 10 أيام مصوّرش ← تنبيه عادي، و(أ) بيتفكّر تاني
    $m->fixed_time = strtotime('2026-11-05 10:00:00');
    $new = $m->check_content_runway();
    ok(count($mine($new, $b)) === 1 && count($mine($new, $a)) === 1, 'بقاله أسبوع');
    ok(q('SELECT priority FROM tbldse_alerts WHERE id = ?', [$mine($new, $b)[0]])[0]['priority'] === 'normal');
    // الباقة اللي هتخلص خلال 7 أيام ← تنبيه مرة واحدة · لو جدد ← لأ
    $m->fixed_time = strtotime('2026-11-25 10:00:00');
    $e = $m->check_packages_ending();
    ok(count($mine($e, $a)) === 1 && strpos(q('SELECT message FROM tbldse_alerts WHERE id = ?', [$mine($e, $a)[0]])[0]['message'], 'هتخلص') !== false, json_encode($e));
    ok(!$m->check_packages_ending(), 'مرة واحدة');
    $m->renew_package($pb);
    $db->query("DELETE FROM tbldse_alerts WHERE alert_key = 'package_ending'");
    $e = $m->check_packages_ending();
    ok(!$mine($e, $b) && count($mine($e, $a)) === 1, 'اللي جدد مبيتنبهش');
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid IN (?, ?, ?)", [$a, $b, $cc]);
    $m->fixed_time = $save;
});

test('مواعيد التنزيل (الأحد ريل · التلات تصميم · الخميس ريل): المحتوى بيخلص يوم إيه · لازم ينزل النهارده · التنبيه بالتاريخ', function () use ($m, $db) {
    $save = $m->fixed_time;
    $m->fixed_time = strtotime('2026-10-29 10:00:00'); // خميس
    $st = q('SELECT id FROM tbldse_studios LIMIT 1')[0]['id'];
    $db->insert('tblclients', ['company' => 'مواعيد ثابتة']); $c = $db->insert_id();
    $p = $m->create_package($c, 'G', 8000, '2026-10-01', 60, ['reel' => 12, 'design' => 4]); $m->record_payment($p, 8000, '2026-10-01');
    $db->insert('tbldse_sessions', ['clientid' => $c, 'package_id' => $p, 'studio_id' => $st, 'type' => 'reel', 'qty' => 2, 'actual_qty' => 2, 'start_at' => '2026-10-27 12:00:00', 'end_at' => '2026-10-27 13:00:00', 'status' => 'done', 'created_at' => '2026-10-27 09:00:00']);
    ok($m->save_publish_slots($c, [0 => 'reel', 1 => '', 2 => 'design', 4 => 'reel', 9 => 'reel', 5 => 'video']) === 3, 'التلاتة الصح بس');
    ok($m->publish_slots($c) === [0 => 'reel', 2 => 'design', 4 => 'reel']);
    $r = $m->content_runway($c)[0];
    // جاهز 2: النهارده (الخميس) + الأحد ← الخميس الجاي 05/11 مفيش
    ok($r['ready'] == 2 && $r['rate'] == 2 && $r['from_schedule'] && $r['runout'] === '2026-11-05' && $r['days_left'] === 7, json_encode($r, JSON_UNESCAPED_UNICODE));
    ok(array_column($r['upcoming'], 'day') === ['2026-10-29', '2026-11-01', '2026-11-03', '2026-11-05'] && $r['upcoming'][2]['type'] === 'design');
    $due = array_values(array_filter($m->due_today(), function ($d) use ($c) { return (int) $d['clientid'] === $c; }));
    ok(count($due) === 1 && $due[0]['type'] === 'reel' && !$due[0]['done']);
    // نزل ريل النهارده ← الميعاد اتعلّم ✅ والمحتوى بيكفي لحد الأحد 08/11
    $db->insert('tbldse_posts', ['clientid' => $c, 'type' => 'reel', 'post_url' => 'https://x/today', 'published_at' => '2026-10-29 09:00:00']);
    $due = array_values(array_filter($m->due_today(), function ($d) use ($c) { return (int) $d['clientid'] === $c; }));
    ok($due[0]['done'], 'نزل');
    $r = $m->content_runway($c)[0];
    ok($r['ready'] == 1 && $r['runout'] === '2026-11-05' && $r['upcoming'][0]['day'] === '2026-11-01', json_encode($r, JSON_UNESCAPED_UNICODE));
    // التنبيه: بيخلص خلال أسبوع ← "بتخلص يوم الخميس 05/11 … (حسب مواعيد التنزيل)"
    $ids = $m->check_content_runway();
    $a = array_values(array_filter($ids, function ($id) use ($c) { return q('SELECT clientid FROM tbldse_alerts WHERE id = ?', [$id])[0]['clientid'] == $c; }));
    $msg = q('SELECT message FROM tbldse_alerts WHERE id = ?', [$a[0]])[0]['message'];
    ok(strpos($msg, 'بتخلص يوم الخميس 05/11') !== false && strpos($msg, '(حسب مواعيد التنزيل)') !== false, 'MSG=' . $msg . json_encode($a));
    $GLOBALS['slot_client'] = $c;
    $m->fixed_time = $save;
});

test('الأنواع الأربعة: ريل · تصميم · فيديو يوتيوب (بالساعات) · بودكاست (بالعدد — الحلقة ساعتين بالكتير)', function () use ($m, $db) {
    $save = $m->fixed_time;
    $m->fixed_time = strtotime('2026-10-29 10:00:00');
    ok($m->session_minutes('podcast', 2) === 240 && $m->session_minutes('trainer_hours', 1.5) === 90 && $m->session_minutes('reel', 3) === 60);
    update_option('dse_minutes_per_podcast', '200');
    ok($m->session_minutes('podcast', 1) === 120, 'أقصاها ساعتين');
    update_option('dse_minutes_per_podcast', '120');
    $db->insert('tblclients', ['company' => 'أكاديمية المدرس']); $c = $db->insert_id();
    $p = $m->create_package($c, 'Edu', 20000, '2026-10-01', 60, ['trainer_hours' => 10, 'podcast' => 4, 'reel' => 4]);
    $m->record_payment($p, 20000, '2026-10-01');
    $st = q('SELECT id FROM tbldse_studios LIMIT 1')[0]['id'];
    $s1 = $m->book_session($c, $st, '2026-11-01 10:00:00', 'podcast', 2);
    ok(q('SELECT end_at FROM tbldse_sessions WHERE id = ?', [$s1])[0]['end_at'] === '2026-11-01 14:00:00' && $m->balance($p)['podcast']['reserved'] == 2);
    $s2 = $m->book_session($c, $st, '2026-11-01 15:00:00', 'trainer_hours', 3);
    ok(q('SELECT end_at FROM tbldse_sessions WHERE id = ?', [$s2])[0]['end_at'] === '2026-11-01 18:00:00');
    $m->fixed_time = strtotime('2026-11-02 10:00:00');
    $m->complete_session($s1, 2);
    $m->complete_session($s2, 3);
    // التنزيل: يوتيوب وبودكاست مبيتخصموش (اتخصموا في التصوير) · الرسالة بنوعها
    $m->save_client_wa($c, ['wa_group_jid' => '120363000000000444@g.us']);
    $m->publish_post($c, 'youtube', 'https://youtube.com/watch?v=1');
    $m->publish_post($c, 'podcast', 'https://youtube.com/watch?v=2');
    throws(function () use ($m, $c) { $m->publish_post($c, 'tiktok', 'https://x'); }, 'نوع البوست غلط');
    ok($m->balance($p)['podcast']['used'] == 2 && $m->balance($p)['trainer_hours']['used'] == 3);
    $o = array_column(q("SELECT body FROM tbldse_wa_outbox WHERE clientid = ? AND msg_key = 'published' ORDER BY id", [$c]), 'body');
    ok(strpos($o[0], 'الفيديو بتاعك نزل على اليوتيوب 📺') === 0 && strpos($o[1], 'حلقة البودكاست بتاعتك نزلت 🎙') === 0, json_encode($o, JSON_UNESCAPED_UNICODE));
    // المخزون: بودكاست اتصوّر 2 ونزل 1 ← جاهز 1 · مواعيد: الاتنين بودكاست (نزلت النهارده) ← الجاهزة تغطي 09/11 وبيخلص 16/11
    ok($m->save_publish_slots($c, [1 => 'podcast', 3 => 'youtube', 5 => 'reel']) === 3);
    $rows = []; foreach ($m->content_runway($c) as $r) { $rows[$r['type']] = $r; }
    ok(isset($rows['podcast'], $rows['reel']) && !isset($rows['trainer_hours']) && $rows['podcast']['ready'] == 1 && $rows['podcast']['runout'] === '2026-11-16' && $rows['podcast']['unit'] === 'حلقة', json_encode($rows['podcast'] ?? null, JSON_UNESCAPED_UNICODE));
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid = ?", [$c]);
    $m->fixed_time = $save;
});

test('كالندر المحتوى: هدف الشهر + CTA · المواضيع بتواريخ الشهر · املا من مواعيد التنزيل بتوزيع الهدف · السكربت والحالات · النتيجة', function () use ($m, $db) {
    $save = $m->fixed_time;
    $m->fixed_time = strtotime('2026-10-31 10:00:00');
    $db->insert('tblclients', ['company' => 'عيادة د. منى']); $c = $db->insert_id();
    $pk = $m->create_package($c, 'G', 8000, '2026-11-01', 30, ['reel' => 8, 'design' => 4]);
    ok($m->content_plan($c, '2026-11') === null);
    throws(function () use ($m, $c) { $m->content_plan($c, '2026-13', true); }, 'الشهر غلط');
    $p = $m->content_plan($c, '2026-11', true);
    ok($p['goal'] === 'sales' && strpos($p['cta'], 'احجز') !== false, 'الافتراضي مبيعات');
    $m->save_content_plan($c, '2026-11', ['goal' => 'sales', 'target' => '30', 'cta' => 'اكتب "احجز" وهنبعتلك لينك العيادة', 'cta_link' => 'https://wa.me/201000000000', 'notes' => 'عرض تبييض نوفمبر']);
    $p = $m->content_plan($c, '2026-11');
    ok((int) $p['target'] === 30 && $p['cta_link'] === 'https://wa.me/201000000000');
    // الأحد ريل · التلات تصميم · الخميس ريل ← نوفمبر 2026: 5 أحد + 4 تلات + 4 خميس = 13
    $m->save_publish_slots($c, [0 => 'reel', 2 => 'design', 4 => 'reel']);
    ok($m->fill_plan_from_schedule($p['id']) === 13);
    ok($m->fill_plan_from_schedule($p['id']) === 0, 'مبيكررش');
    $items = $m->content_items($p['id']);
    ok(count($items) === 13 && $items[0]['publish_date'] === '2026-11-01' && $items[0]['type'] === 'reel' && $items[0]['cta'] === 'اكتب "احجز" وهنبعتلك لينك العيادة', json_encode($items[0], JSON_UNESCAPED_UNICODE));
    $sum = $m->content_summary($p, $items);
    ok($sum['total'] === 13 && $sum['no_topic'] === 13 && $sum['mix']['offer']['count'] >= 3 && $sum['mix']['educational']['count'] >= 3, json_encode($sum['mix']));
    // الحالات: مينفعش "السكربت جاهز" من غير موضوع وسكربت · مينفعش "نزل" من غير لينك · التاريخ جوه الشهر
    $id = (int) $items[0]['id'];
    throws(function () use ($m, $p, $id) { $m->save_content_item($p['id'], ['status' => 'script'], $id); }, 'اكتب الموضوع');
    throws(function () use ($m, $p, $id) { $m->save_content_item($p['id'], ['topic' => '3 غلطات في تنضيف السنان', 'status' => 'script'], $id); }, 'اكتب السكربت');
    $m->save_content_item($p['id'], ['topic' => '3 غلطات في تنضيف السنان', 'hook' => 'إنت بتغسل سنانك غلط', 'script' => 'الهوك… المشكلة… الحل… اكتب احجز', 'status' => 'script'], $id);
    throws(function () use ($m, $p, $id) { $m->save_content_item($p['id'], ['status' => 'published'], $id); }, 'لينك البوست');
    $m->save_content_item($p['id'], ['status' => 'published', 'post_url' => 'https://instagram.com/p/1'], $id);
    throws(function () use ($m, $p) { $m->save_content_item($p['id'], ['publish_date' => '2026-12-02', 'topic' => 'x']); }, 'جوه شهر 2026-11');
    $new = $m->save_content_item($p['id'], ['publish_date' => '2026-11-15', 'type' => 'podcast', 'pillar' => 'trust', 'topic' => 'حلقة: قصة حالة']);
    ok($m->content_item($new)['cta'] === 'اكتب "احجز" وهنبعتلك لينك العيادة', 'الـ CTA الفاضي = CTA الشهر');
    $m->delete_content_item($new);
    // النتيجة آخر الشهر
    $m->save_content_plan($c, '2026-11', ['goal' => 'sales', 'result' => '24', 'result_note' => 'أحسن موضوع: الغلطات']);
    $p = $m->content_plan($c, '2026-11');
    ok($m->content_summary($p, $m->content_items($p['id']))['achieved'] === 80);
    // الشهر الجاي بياخد الهدف والـ CTA من اللي قبله · تغيير الهدف بيغيّر الـ CTA
    $d = $m->content_plan($c, '2026-12', true);
    ok($d['goal'] === 'sales' && (int) $d['target'] === 30 && $d['cta'] === 'اكتب "احجز" وهنبعتلك لينك العيادة' && $d['result'] === null);
    $m->save_content_plan($c, '2026-12', ['goal' => 'followers']);
    ok(strpos($m->content_plan($c, '2026-12')['cta'], 'تابعني') === 0);
    ok(count(dse_script_manual('sales')) === 10 && strpos(implode(' ', dse_script_manual('engagement')), 'سؤال') !== false);
    $ov = array_values(array_filter($m->content_overview('2026-11'), function ($r) use ($c) { return $r['clientid'] === $c; }));
    ok(count($ov) === 1 && $ov[0]['summary']['published'] === 1 && $ov[0]['slots'] === 3);
    $GLOBALS['media'] = [$c, $m->client($c), $m->content_plan($c, '2026-11')];
    $m->fixed_time = $save;
});

test('خدمات الباقة: إعلانات ممولة (ميزانية + أتعاب بالجنيه) · ويب سايت / أبلكيشن / أوتوميشن · باقة خدمات بس · ترقية · التجديد بياخد الإعلانات بس', function () use ($m, $db) {
    $save = $m->fixed_time;
    $m->fixed_time = strtotime('2026-11-02 10:00:00');
    $db->insert('tblclients', ['company' => 'شركة البرمجة']); $c = $db->insert_id();
    // ويب سايت بس — السعر الفاضي = مجموع الخدمات
    throws(function () use ($m, $c) { $m->create_package($c, 'x', '', '2026-11-01', 30, []); }, 'ضيف خدمة واحدة');
    $w = $m->create_package($c, 'موقع', '', '2026-11-01', 60, [], null, null, false, [['service' => 'website', 'title' => 'موقع الشركة', 'amount' => 15000], ['service' => 'app', 'amount' => '']]);
    ok((float) $m->package($w)['price'] === 15000.0 && count($m->package_services($w)) === 1 && $m->package_services($w)[0]['status'] === 'pending' && $m->balance($w) === [], json_encode($m->package_services($w), JSON_UNESCAPED_UNICODE));
    // ريلز + إعلانات: ميزانية 10,000 للمنصة + أتعاب 2,500 ثابتة
    $r = $m->create_package($c, 'Growth + Ads', 8000, '2026-11-01', 30, ['reel' => 8], null, null, false, [['service' => 'ads', 'ad_budget' => 10000, 'amount' => 2500, 'platforms' => 'facebook,instagram']]);
    $ads = $m->package_services($r)[0];
    ok((float) $m->package($r)['price'] === 8000.0 && (float) $ads['ad_budget'] === 10000.0 && (float) $ads['amount'] === 2500.0 && $ads['status'] === 'running' && dse_platforms_label($ads['platforms']) === 'فيسبوك + إنستجرام', 'السعر الكلي اللي اتكتب بيفضل');
    ok(strpos($m->services_text($r), '📣 إعلانات ممولة 10,000') !== false, $m->services_text($r));
    // ترقية: ضيف أوتوميشن ← السعر بيزيد ويتسجل
    throws(function () use ($m, $r) { $m->add_package_service($r, ['service' => 'automation', 'amount' => 0]); }, 'سعر الخدمة');
    $sid = $m->add_package_service($r, ['service' => 'automation', 'title' => 'بوت حجز واتساب n8n', 'amount' => 6000]);
    ok((float) $m->package($r)['price'] === 14000.0 && q("SELECT event FROM tbldse_package_events WHERE package_id = ? AND event = 'service'", [$r]));
    $m->update_service($sid, ['status' => 'delivered']);
    ok($m->package_services($r)[1]['status'] === 'delivered');
    throws(function () use ($m, $sid) { $m->update_service($sid, ['status' => 'x']); }, 'حالة غلط');
    // التجديد: الإعلانات بتتجدد · الأوتوميشن (مرة واحدة) لأ وسعره بيتشال
    $m->record_payment($r, 14000);
    $n = $m->renew_package($r);
    $ns = $m->package_services($n);
    ok(count($ns) === 1 && $ns[0]['service'] === 'ads' && (float) $m->package($n)['price'] === 8000.0, json_encode([$ns, $m->package($n)['price']], JSON_UNESCAPED_UNICODE));
    // شيل خدمة ← السعر بيقل
    $m->remove_service($sid);
    ok((float) $m->package($r)['price'] === 8000.0 && count($m->package_services($r)) === 1);
    // مسح الباقة بيمسح خدماتها
    $m->delete_package($w);
    ok(!q('SELECT id FROM tbldse_package_services WHERE package_id = ?', [$w]));
    $GLOBALS['svc_pkg'] = [$c, $r];
    $m->fixed_time = $save;
});

test('اللوحة المالية (مارس 2027): كاش · مصروفات · ربح وهامش · مبيعات جديد وتجديد · توزيع الخدمات · التجديد والفقد · الفلوس برا بعمرها', function () use ($m, $db) {
    $save = $m->fixed_time;
    $snap = q('SELECT id, status FROM tbldse_packages'); // الاختبار بيقدّم الساعة لـ 2027 — نرجّع حالات باقات الاختبارات التانية بعده
    $pay  = function ($pkg, $amount, $day) use ($m) { $m->fixed_time = strtotime($day . ' 12:00:00'); $m->record_payment($pkg, $amount, $day); };
    $db->insert('tblclients', ['company' => 'فين A']); $a = $db->insert_id();
    $db->insert('tblclients', ['company' => 'فين B']); $b = $db->insert_id();
    $db->insert('tblclients', ['company' => 'فين C']); $c = $db->insert_id();
    $m->fixed_time = strtotime('2027-02-01 10:00:00');
    $b1 = $m->create_package($b, 'B1', 8000, '2027-02-01', 30, ['design' => 4]); $pay($b1, 8000, '2027-02-01');
    $c1 = $m->create_package($c, 'C1', 5000, '2027-02-01', 30, ['design' => 4]); $pay($c1, 5000, '2027-02-02');
    $m->fixed_time = strtotime('2027-03-01 10:00:00');
    $a1 = $m->create_package($a, 'A1', 10000, '2027-03-01', 30, ['design' => 4], null, null, false, [['service' => 'ads', 'ad_budget' => 2000, 'amount' => 1000]]);
    $pay($a1, 6000, '2027-03-05');
    $m->fixed_time = strtotime('2027-03-03 10:00:00');
    $b2 = $m->renew_package($b1); $pay($b2, 8000, '2027-03-03');
    // كل البنود اتصرفت علشان ميبقاش فيه فترة سماح
    foreach ([$a1, $b1, $b2, $c1] as $pp) { $db->insert('tbldse_usage', ['package_id' => $pp, 'type' => 'design', 'qty' => 4, 'reason' => 'manual', 'created_at' => '2027-03-01 10:00:00']); }
    $db->insert('tblexpenses_categories', ['name' => 'إيجار']); $rent = $db->insert_id();
    $db->insert('tblexpenses', ['category' => $rent, 'amount' => 3000, 'date' => '2027-03-10']);
    $db->insert('tblexpenses', ['category' => null, 'amount' => 1000, 'date' => '2027-03-12']);
    $m->fixed_time = strtotime('2027-04-15 10:00:00');
    $f = $m->finance_dashboard('2027-03');
    ok($f['cash']['all'] == 14000 && $f['cash']['studio'] == 14000, json_encode($f['cash']));
    ok($f['expenses']['total'] == 4000 && $f['expenses']['by']['إيجار'] == 3000 && $f['expenses']['by']['من غير تصنيف'] == 1000, json_encode($f['expenses'], JSON_UNESCAPED_UNICODE));
    ok($f['profit'] == 10000 && $f['margin'] === 71, $f['profit'] . ' ' . $f['margin']);
    ok($f['booked']['new'] == 10000 && $f['booked']['new_count'] === 1 && $f['booked']['renew'] == 8000 && $f['booked']['renew_count'] === 1, json_encode($f['booked']));
    ok($f['booked']['mix'] == ['content' => 15000.0, 'ads_fee' => 1000.0, 'ads_budget' => 2000.0, 'programming' => 0.0], json_encode($f['booked']['mix']));
    ok($f['renewals']['due'] === 3 && $f['renewals']['pending'] === 1 && $f['renewals']['renewed'] === 1 && $f['renewals']['churned'] === 1 && $f['renewals']['renewal_rate'] === 50 && $f['renewals']['churn_rate'] === 50, json_encode($f['renewals']));
    $ra = array_values(array_filter($f['receivables']['rows'], function ($r) use ($a) { return $r['clientid'] === $a; }));
    ok(count($ra) === 1 && $ra[0]['left'] == 4000 && $ra[0]['age'] === 45 && $f['receivables']['aging']['31-60'] >= 4000, json_encode($ra));
    ok(count($f['trend']) === 12 && end($f['trend'])['month'] === '2027-03' && end($f['trend'])['profit'] == 10000 && $f['trend'][10]['cash'] == 13000, json_encode(array_slice($f['trend'], -2)));
    ok(in_array(['clientid' => $b, 'company' => 'فين B', 'paid' => 16000.0], $f['top']), json_encode($f['top'], JSON_UNESCAPED_UNICODE));
    // الصفحة (أدمن)
    $GLOBALS['fin'] = $f;
    foreach ($snap as $r) { $db->query('UPDATE tbldse_packages SET status = ? WHERE id = ?', [$r['status'], $r['id']]); }
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid IN (?, ?, ?)", [$a, $b, $c]);
    $m->fixed_time = $save;
});

test('تفاصيل الشهر: الإيراد حسب المصدر من بنود الفواتير (الدفعة متوزعة على البنود) · المصروفات بالتصنيف ومقارنة بالشهر اللي قبله · البنود المش متصنفة', function () use ($m, $db) {
    ok($m->classify_line('دبلومة n8n المستوى الأول') === 'course_n8n' && $m->classify_line('بوت حجز واتساب n8n') === 'automation' && $m->classify_line('Vibe Coding Bootcamp') === 'course_vibe'
        && $m->classify_line('باقة 8 ريلز') === 'reels' && $m->classify_line('Website design') === 'website' && $m->classify_line('حاجة غريبة') === null);
    $db->insert('tblclients', ['company' => 'إيراد']); $c = $db->insert_id();
    $inv = function ($lines, $date) use ($db, $c) {
        $db->insert('tblinvoices', ['clientid' => $c, 'total' => array_sum(array_map(function ($l) { return $l[1]; }, $lines)), 'status' => 1, 'date' => $date]); $id = $db->insert_id();
        foreach ($lines as $i => $l) { $db->insert('tblitemable', ['rel_id' => $id, 'rel_type' => 'invoice', 'description' => $l[0], 'long_description' => '', 'qty' => 1, 'rate' => $l[1], 'item_order' => $i]); }
        return $id;
    };
    $pay = function ($id, $amount, $date) use ($db) { $db->insert('tblinvoicepaymentrecords', ['invoiceid' => $id, 'amount' => $amount, 'date' => $date]); };
    $i1 = $inv([['كورس n8n', 3000], ['باقة 8 ريلز', 5000]], '2027-05-02'); $pay($i1, 4000, '2027-05-03');
    $i2 = $inv([['Website', 10000]], '2027-05-05'); $pay($i2, 10000, '2027-05-06');
    $i3 = $inv([['حاجة غريبة', 1000]], '2027-05-07'); $pay($i3, 1000, '2027-05-08');
    $i4 = $inv([['باقة 8 ريلز', 5000]], '2027-04-02'); $pay($i4, 5000, '2027-04-03');
    $cat = function ($name) use ($db) { $db->insert('tblexpenses_categories', ['name' => $name]); return $db->insert_id(); };
    $ads = $cat('إعلانات'); $sal = $cat('مرتبات');
    $db->insert('tblexpenses', ['category' => $sal, 'amount' => 6000, 'date' => '2027-05-01', 'expense_name' => 'مرتبات مايو']);
    $db->insert('tblexpenses', ['category' => $ads, 'amount' => 2000, 'date' => '2027-05-10', 'expense_name' => 'فيسبوك']);
    $db->insert('tblexpenses', ['category' => $sal, 'amount' => 5000, 'date' => '2027-04-01', 'expense_name' => 'مرتبات أبريل']);
    $f = $m->finance_month('2027-05');
    $by = []; foreach ($f['streams'] as $x) { $by[$x['key']] = $x; }
    ok(round($by['website']['cash']) == 10000 && round($by['course_n8n']['cash']) == 1500 && round($by['reels']['cash']) == 2500 && round($by['other']['cash']) == 1000, json_encode($by, JSON_UNESCAPED_UNICODE));
    ok($f['streams'][0]['key'] === 'website' && $by['website']['share'] === 67 && $by['reels']['invoiced'] == 5000 && $by['reels']['change'] === -50, json_encode($by['reels']));
    ok(isset($f['unclassified']['حاجة غريبة']) && round($f['unclassified']['حاجة غريبة']) == 1000);
    ok($f['cash'] == 15000 && $f['expenses'] == 8000 && $f['profit'] == 7000 && $f['prev_cash'] == 5000 && $f['cash_change'] === 200, json_encode([$f['cash'], $f['expenses'], $f['prev_cash']]));
    ok($f['categories'][0]['name'] === 'مرتبات' && $f['categories'][0]['share'] === 75 && $f['categories'][0]['change'] === 20 && $f['categories'][1]['change'] === null);
    ok($f['top_expenses'][0]['expense_name'] === 'مرتبات مايو' && $f['invoices']['count'] === 3 && $f['invoices']['total'] == 19000 && $f['invoices']['paid'] == 15000);
    // تعديل التصنيف: "غريبة" ← مصدر جديد
    $defs = $m->revenue_streams();
    $post = []; foreach ($defs as $k => $x) { $post[$k] = ['label' => $x['label'], 'keywords' => implode(', ', $x['keywords'])]; }
    $post['x9'] = ['label' => '🧪 استشارات', 'keywords' => 'غريبة، استشارة'];
    ok($m->save_revenue_streams($post) === 8);
    $f = $m->finance_month('2027-05');
    ok(!$f['unclassified'] && in_array('🧪 استشارات', array_column($f['streams'], 'label'), true));
    throws(function () use ($m) { $m->save_revenue_streams(['a' => ['label' => '', 'keywords' => '']]); }, 'مصدر واحد');
    $GLOBALS['fm'] = $f;
    update_option('dse_revenue_streams', '');
});

test('العملات: إيرادات بالجنيه والدولار واليورو بتتحول لجنيه بسعر الصرف · المصروفات جنيه زي ما هي · العملة اللي مالهاش سعر بتتعلّم', function () use ($m, $db) {
    $db->insert('tblclients', ['company' => 'عميل دولار', 'default_currency' => 1]); $c = $db->insert_id();
    $inv = function ($total, $cur, $desc) use ($db, $c) {
        $db->insert('tblinvoices', ['clientid' => $c, 'total' => $total, 'status' => 2, 'date' => '2027-07-02', 'currency' => $cur]); $id = $db->insert_id();
        $db->insert('tblitemable', ['rel_id' => $id, 'rel_type' => 'invoice', 'description' => $desc, 'long_description' => '', 'qty' => 1, 'rate' => $total, 'item_order' => 0]);
        $db->insert('tblinvoicepaymentrecords', ['invoiceid' => $id, 'amount' => $total, 'date' => '2027-07-03']);
    };
    $inv(100, 1, 'Vibe Coding Bootcamp');   // دولار
    $inv(50, 2, 'كورس AI Video');            // يورو
    $inv(3000, 3, 'باقة ريلز');              // جنيه
    $db->insert('tblexpenses', ['category' => null, 'amount' => 2000, 'date' => '2027-07-05', 'expense_name' => 'إيجار']);
    update_option('dse_fx_rates', '');
    $f = $m->finance_dashboard('2027-07');
    $fx = $f['fx'];
    ok($fx[1]['missing'] && $fx[2]['missing'] && !$fx[3]['missing'] && $fx[3]['egp'] && $f['cash']['all'] == 3150, 'من غير أسعار: 1 = 1 ومتعلّمة');
    $m->save_fx_settings(['USD' => 48.5, 'eur' => 52, 'XX' => 0]);
    ok($m->fx_settings() == ['USD' => 48.5, 'EUR' => 52], json_encode($m->fx_settings()));
    $f = $m->finance_dashboard('2027-07');
    ok($f['cash']['all'] == 4850 + 2600 + 3000 && $f['cash']['by_currency'] == ['USD' => 100.0, 'EUR' => 50.0, 'EGP' => 3000.0], json_encode($f['cash']));
    ok($f['expenses']['total'] == 2000 && $f['profit'] == 8450, 'المصروفات جنيه زي ما هي');
    $mo = $m->finance_month('2027-07');
    $by = []; foreach ($mo['streams'] as $x) { $by[$x['key']] = $x['cash']; }
    ok(round($by['course_vibe']) == 4850 && round($by['course_ai']) == 2600 && round($by['reels']) == 3000 && $mo['invoices']['total'] == 10450, json_encode([$by, $mo['invoices']], JSON_UNESCAPED_UNICODE));
    ok(in_array(['clientid' => $c, 'company' => 'عميل دولار', 'paid' => 10450.0], $f['top']), json_encode($f['top'], JSON_UNESCAPED_UNICODE));
    // فاتورة الباقة اللي الموديول بيعملها بالجنيه حتى لو العميل عملته دولار
    $m->fixed_time = strtotime('2027-07-10 10:00:00');
    $pk = $m->create_package($c, 'G', 5000, '2027-07-10', 30, ['reel' => 4]);
    $m->record_payment($pk, 1000);
    ok((int) q('SELECT i.currency FROM tblinvoices i JOIN tbldse_package_invoices pi ON pi.invoice_id = i.id WHERE pi.package_id = ?', [$pk])[0]['currency'] === 3, 'فاتورة الباقة بالجنيه');
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE id = ?", [$pk]);
    $m->fixed_time = null;
    update_option('dse_fx_rates', '');
});

test('البوست المتجدول: الرسالة بتروح دلوقتي وفيها "هينزل … الساعة …" · بيتحسب على يوم نزوله · الماضي = نزل دلوقتي', function () use ($m, $db) {
    $save = $m->fixed_time;
    $m->fixed_time = strtotime('2026-11-05 17:30:00'); // الخميس 5:30 م — الموظفين بيمشوا 6
    $db->insert('tblclients', ['company' => 'جدولة']); $c = $db->insert_id();
    $pk = $m->create_package($c, 'G', 4000, '2026-11-01', 30, ['reel' => 8, 'design' => 4]); $m->record_payment($pk, 4000);
    $m->save_client_wa($c, ['wa_group_jid' => '120363000000000666@g.us']);
    throws(function () use ($m, $c) { $m->publish_post($c, 'reel', 'https://ig/p/1', null, '2027-03-01T20:00'); }, 'بعيد قوي');
    $id = $m->publish_post($c, 'reel', 'https://ig/p/1', null, '2026-11-05T20:00');
    $p = q('SELECT * FROM tbldse_posts WHERE id = ?', [$id])[0];
    ok($p['published_at'] === '2026-11-05 20:00:00' && (int) $p['scheduled'] === 1);
    $o = q("SELECT body FROM tbldse_wa_outbox WHERE dedupe_key = ?", ['published:' . $id])[0]['body'];
    ok(strpos($o, 'الريل بتاعك جاهز ومتجدول 🎬') === 0 && strpos($o, '🗓 هينزل الخميس 05/11 الساعة 8:00 م') !== false && strpos($o, 'https://ig/p/1') !== false, $o);
    $id2 = $m->publish_post($c, 'design', 'https://ig/p/2', null, '');
    $o2 = q("SELECT body FROM tbldse_wa_outbox WHERE dedupe_key = ?", ['published:' . $id2])[0]['body'];
    ok(strpos($o2, 'التصميم بتاعك نزل 🎨') === 0 && strpos($o2, 'هينزل') === false && (int) q('SELECT scheduled FROM tbldse_posts WHERE id = ?', [$id2])[0]['scheduled'] === 0, $o2);
    $id3 = $m->publish_post($c, 'reel', 'https://ig/p/3', null, '2026-11-05T10:00'); // وقت فات ← نزل دلوقتي
    ok(q('SELECT published_at, scheduled FROM tbldse_posts WHERE id = ?', [$id3])[0] === ['published_at' => '2026-11-05 17:30:00', 'scheduled' => 0]);
    // رسالة متعدّلة من غير {scheduled}: الميعاد مايضيعش
    update_option('dse_wa_tpl_published', "{headline}\n{post_url}");
    ok(strpos(dse_wa_text('published', ['type' => 'reel', 'post_url' => 'u', 'scheduled_at' => '2026-11-05 20:00:00']), "🗓 هينزل الخميس 05/11 الساعة 8:00 م") !== false);
    update_option('dse_wa_tpl_published', '');
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid = ?", [$c]);
    $m->fixed_time = $save;
});
test('مواعيد الأقساط: الدفعات بتغطي الأقدم الأول · النهارده / متأخر / اتدفع · تقسيم الباقي شهري · تنبيه يوم الاستحقاق وبعد أسبوع (لإنجي ومريم)', function () use ($m, $db) {
    $save = $m->fixed_time;
    $m->fixed_time = strtotime('2026-11-01 10:00:00');
    $db->insert('tblclients', ['company' => 'أقساط مواعيد']); $c = $db->insert_id();
    $pk = $m->create_package($c, 'G', 12000, '2026-11-01', 90, ['reel' => 8]);
    $m->record_payment($pk, 3000, '2026-11-01');
    throws(function () use ($m, $pk) { $m->add_installment($pk, '2026-12-01', 0); }, 'مبلغ القسط');
    ok($m->split_installments($pk, 3, '2026-12-01') === 3);
    $l = $m->package_installments($pk);
    ok(array_column($l, 'due_date') === ['2026-12-01', '2027-01-01', '2027-02-01'] && array_sum(array_column($l, 'amount')) == 9000 && $l[0]['state'] === 'upcoming', json_encode($l, JSON_UNESCAPED_UNICODE));
    // يوم أول قسط ← تنبيه "النهارده ميعاد قسط" مرة واحدة
    $m->fixed_time = strtotime('2026-12-01 09:00:00');
    $a = $m->check_installments();
    ok(count($a) === 1 && $m->package_installments($pk)[0]['state'] === 'due', json_encode($a));
    $al = q('SELECT * FROM tbldse_alerts WHERE id = ?', [$a[0]])[0];
    ok($al['alert_key'] === 'installment_due' && strpos($al['message'], 'النهارده ميعاد قسط') !== false && strpos($al['message'], '3,000') !== false, $al['message']);
    ok(!$m->check_installments(), 'مرة واحدة');
    // دفع جزء (1000) ← لسه 2000 فاضلين · بعد أسبوع ← تنبيه عاجل
    $m->fixed_time = strtotime('2026-12-03 09:00:00');
    $m->record_payment($pk, 1000, '2026-12-03');
    $m->fixed_time = strtotime('2026-12-08 09:00:00');
    $l = $m->package_installments($pk);
    ok($l[0]['state'] === 'overdue' && $l[0]['unpaid'] == 2000 && $l[0]['days'] === -7, json_encode($l[0]));
    $a = $m->check_installments();
    ok(count($a) === 1 && q('SELECT priority FROM tbldse_alerts WHERE id = ?', [$a[0]])[0]['priority'] === 'urgent' && strpos(q('SELECT message FROM tbldse_alerts WHERE id = ?', [$a[0]])[0]['message'], 'متأخر 7 يوم') !== false);
    // دفع الباقي ← القسط اتدفع ومفيش تنبيهات
    $m->record_payment($pk, 2000, '2026-12-08');
    ok($m->package_installments($pk)[0]['state'] === 'paid' && $m->package_installments($pk)[1]['state'] === 'upcoming');
    $m->delete_installment($m->package_installments($pk)[2]['id']);
    ok(count($m->package_installments($pk)) === 2);
    $GLOBALS['inst_client'] = $c;
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid = ?", [$c]);
    $m->fixed_time = $save;
});

test('ليه العملاء بيمشوا: الإلغاء ومش هيجدد بسبب إجباري · "اتعملت بالغلط" مش خسارة · الرجوع بيشيل الحالة · التقرير بالسبب والفلوس وهنعمل إيه', function () use ($m, $db) {
    $save = $m->fixed_time;
    $m->fixed_time = strtotime('2026-11-04 10:00:00');
    $db->query('DELETE FROM tbldse_churn');
    $mk = function ($name, $price) use ($m, $db) { $db->insert('tblclients', ['company' => $name]); $c = $db->insert_id(); return [$c, $m->create_package($c, 'G', $price, '2026-11-01', 30, ['reel' => 4])]; };
    [$a, $pa] = $mk('مشي سعر', 6000);
    [$b, $pb] = $mk('مشي نتيجة', 8000);
    [$c, $pc] = $mk('غلط', 3000);
    [$d, $pd] = $mk('مش هيجدد', 5000);
    throws(function () use ($m, $pa) { $m->cancel_package($pa, '', '', true); }, 'اختار سبب الإلغاء');
    throws(function () use ($m, $pa) { $m->cancel_package($pa, 'other', '', true); }, 'اكتب السبب');
    $m->cancel_package($pa, 'price', 'لقى أرخص', true);
    $m->cancel_package($pb, 'no_results', 'مفيش حجوزات', true);
    $m->cancel_package($pc, 'mistake', '', true);
    ok(count(q('SELECT id FROM tbldse_churn')) === 2, 'الغلط مش خسارة');
    // رجّعها (اتلغت بالغلط) ← الحالة بتتشال
    $m->restore_package($pb);
    ok(count(q('SELECT id FROM tbldse_churn')) === 1);
    $m->cancel_package($pb, 'no_results', 'مفيش حجوزات', true);
    // مش هيجدد: السبب إجباري ومتسجلش مرتين
    throws(function () use ($m, $d) { $m->set_renewal_status($d, 'lost', '', '', true); }, 'اختار ليه مش هيجدد');
    $m->set_renewal_status($d, 'lost', 'هيوقف شهرين', 'pause', true);
    $m->set_renewal_status($d, 'lost', 'هيوقف شهرين', 'pause', true);
    ok(count(q("SELECT id FROM tbldse_churn WHERE kind = 'not_renewed'")) === 1);
    $m->set_renewal_status($d, 'later'); // الحالة اتغيرت ← مبيمسحش التاريخ
    $r = $m->churn_report();
    ok($r['total'] === 3 && $r['value'] == 19000 && $r['by'][0]['reason'] === 'no_results' && $r['by'][0]['value'] == 8000 && strpos($r['by'][0]['fix'], 'تقرير') !== false, json_encode($r['by'], JSON_UNESCAPED_UNICODE));
    ok($r['recent'][0]['company'] !== '' && in_array('لقى أرخص', array_column($r['recent'], 'note'), true));
    $GLOBALS['churn'] = $r;
    $db->query("UPDATE tbldse_packages SET status = 'cancelled' WHERE clientid IN (?, ?, ?, ?)", [$a, $b, $c, $d]);
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
    ok(strpos($html, 'سجّل الدفعة') !== false && strpos($html, 'جدّد الاشتراك') !== false && strpos($html, 'مدّ فترة السماح') !== false, 'زرار الدفعة والتجديد ومدّ السماح');
    ok(strpos($html, '<select name="historic"') !== false && strpos($html, 'type="checkbox" name="historic"') === false, 'نوع الباقة select مش checkbox (CSS بتاع Perfex بيخفي الـ checkbox)');
    ok(strpos($html, 'اربط فاتورة عملتها') !== false && strpos($html, 'ترقية / تعديل الباقة') !== false && strpos($html, 'احفظ الترقية') !== false, 'الزراير على الباقة الشغالة');
    $m->fixed_time = strtotime('2026-11-02 10:00:00');
    ok(strpos($html, 'ترقية') !== false && strpos($html, 'ريلز 8←20') !== false && strpos($html, 'فرق الترقية') !== false, 'تاريخ الترقية');
    $ix = render('index', ['runway' => $m->content_runway(), 'clients' => [['userid' => 1, 'company' => 'x']], 'packages' => $m->clients_with_packages(),
        'today' => $m->sessions_between('2026-11-01', '2026-12-01'), 'alerts' => 2]);
    ok(strpos($ix, 'مين محتاج ييجي يصوّر') !== false && (strpos($ix, '<td><b>') !== false || strpos($ix, 'مفيش عملاء بباقة ريلز') !== false) && strpos($ix, 'B-roll') !== false || strpos($ix, 'مفيش عملاء بباقة ريلز') !== false, 'جدول التصوير');
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
    $hp = q('SELECT id FROM tbldse_packages WHERE clientid = ?', [$rc])[0]['id'];
    $m->record_payment($hp, 2500, '2026-08-02', null, 'قسط قديم');
    $m->extend_grace($hp, 28, '', ['reel' => 1]); // حدث "مدّ السماح" من غير old_items (كان بيطلع json_decode deprecated في PHP 8.1+)
    $hs = $m->client_summary($rc);
    $one = function ($f) use ($hs) { $o = []; foreach ($hs['packages'] as $p) { $o[$p['id']] = $f($p); } return $o; };
    $hh = render('client', $hs + ['studios' => $m->studios(), 'staff' => [], 'sessions' => [], 'scripts' => [], 'posts' => [], 'outbox' => [], 'invoices' => [['id' => 99, 'prefix' => 'INV-', 'number' => 9, 'total' => 1000, 'status' => 2, 'date' => '2026-08-20']],
        'balances' => $one(function ($p) use ($m) { return $m->balance($p['id']); }), 'pkg_invoices' => $one(function ($p) use ($m) { return $m->package_invoices($p['id']); }),
        'pkg_events' => $one(function ($p) use ($m) { return $m->package_events($p['id']); }), 'pkg_paid' => $one(function ($p) use ($m) { return $m->package_paid_amount($p); }),
        'pkg_busy' => $one(function ($p) use ($m) { return $m->package_has_activity($p['id']); }), 'pkg_payments' => $one(function ($p) use ($m) { return $m->package_payments($p['id']); }),
        'free_invoices' => [['id' => 99, 'prefix' => 'INV-', 'number' => 9, 'total' => 1000, 'status' => 2]], 'pay_modes' => $m->payment_modes(), 'data_counts' => $m->client_data_counts($rc),
        'slots' => [0 => 'reel', 2 => 'design', 4 => 'podcast'], 'runways' => [['type' => 'reel', 'type_name' => 'ريلز', 'unit' => 'ريل', 'ready' => 1, 'filmed' => 2, 'published' => 1, 'runout' => '2026-11-05', 'days_left' => 7, 'from_schedule' => true, 'last_film' => '2026-10-27 12:00:00', 'days_since_film' => 2, 'next_session' => null, 'upcoming' => [['day' => '2026-11-01', 'type' => 'reel'], ['day' => '2026-11-05', 'type' => 'podcast']]]]]);
    ok(strpos($hh, 'مواعيد التنزيل ومخزون المحتوى') !== false && strpos($hh, 'بيخلص الخميس 05/11') !== false && substr_count($hh, 'name="days[') === 7 && strpos($hh, '<option value="design" selected>') !== false && strpos($hh, '<option value="podcast" selected>') !== false && strpos($hh, '🎙 بودكاست الخميس') !== false && strpos($hh, 'value="trainer_hours">فيديو يوتيوب (بالعرض) (ساعة)') !== false && strpos($hh, 'name="items[podcast]"') !== false, 'مواعيد التنزيل في البروفايل');
    file_put_contents(sys_get_temp_dir() . '/dse_client_historic.html', $hh);
    ok(strpos($hh, 'مدفوع <b>2,500') !== false && strpos($hh, 'سجّل دفعة') !== false && strpos($hh, 'اربط فاتورة عملتها في Perfex') !== false && strpos($hh, 'قسط قديم') !== false && strpos($hh, 'name="left[reel]"') !== false && strpos($hh, '<option value="4">4 أسابيع') !== false && strpos($hh, 'امسح العميل من الاستوديو') !== false && strpos($hh, '⏳ مدّ السماح') !== false && strpos($hh, ': · ') === false, 'القديمة: دفعات وفواتير ومدّ السماح');
    $pg = render('pick_group', ['client' => $GLOBALS['pick'][0], 'groups' => $GLOBALS['pick'][1]]);
    ok(strpos($pg, 'مربوط بالعميل ده') !== false && strpos($pg, 'مربوط بـ جروب قديم') !== false, 'صفحة اختيار الجروب');
    $fmv = render('finance_month', ['f' => $GLOBALS['fm']]);
    file_put_contents(sys_get_temp_dir() . '/dse_finance_month.html', $fmv);
    ok(strpos($fmv, 'الإيرادات جاية منين') !== false && strpos($fmv, '🥇') !== false && strpos($fmv, 'ويب سايت') !== false && strpos($fmv, 'صرفنا في إيه') !== false && strpos($fmv, 'مرتبات مايو') !== false && strpos($fmv, '▲ 20%') !== false, 'تفاصيل الشهر');
    $fv = render('finance', ['f' => $GLOBALS['fin']]);
    file_put_contents(sys_get_temp_dir() . '/dse_finance.html', $fv);
    ok(strpos($fv, 'اللوحة المالية') !== false && strpos($fv, '10,000') !== false && strpos($fv, 'هامش 71%') !== false && strpos($fv, 'بتكسب') !== false && strpos($fv, 'جدد 1 من 2') !== false && strpos($fv, 'إيجار') !== false, 'اللوحة المالية');
    [$sc, $sp] = $GLOBALS['svc_pkg'];
    $ss = $m->client_summary($sc);
    $sone = function ($f) use ($ss) { $o = []; foreach ($ss['packages'] as $p) { $o[$p['id']] = $f($p); } return $o; };
    $sh = render('client', $ss + ['studios' => $m->studios(), 'staff' => [], 'sessions' => [], 'scripts' => [], 'posts' => [], 'outbox' => [], 'invoices' => [],
        'balances' => $sone(function ($p) use ($m) { return $m->balance($p['id']); }), 'pkg_invoices' => $sone(function ($p) use ($m) { return $m->package_invoices($p['id']); }),
        'pkg_events' => $sone(function ($p) use ($m) { return $m->package_events($p['id']); }), 'pkg_paid' => $sone(function ($p) use ($m) { return $m->package_paid_amount($p); }),
        'pkg_busy' => $sone(function ($p) use ($m) { return $m->package_has_activity($p['id']); }), 'pkg_payments' => $sone(function ($p) use ($m) { return $m->package_payments($p['id']); }),
        'pkg_services' => $sone(function ($p) use ($m) { return $m->package_services($p['id']); }),
        'pkg_installments' => $sone(function ($p) use ($m) { return $m->package_installments($p['id']); }),
        'free_invoices' => [], 'pay_modes' => $m->payment_modes(), 'data_counts' => $m->client_data_counts($sc), 'slots' => [], 'runways' => []]);
    file_put_contents(sys_get_temp_dir() . '/dse_client_services.html', $sh);
    ok(strpos($sh, 'حط ميعاد قسط') !== false && strpos($sh, 'أقساط شهرية') !== false && strpos($sh, 'name="publish_at"') !== false, 'الأقساط وميعاد النزول في صفحة العميل');
    ok(strpos($sh, 'ميزانية 10,000') !== false && strpos($sh, 'أتعاب 2,500') !== false && strpos($sh, 'فيسبوك + إنستجرام') !== false && strpos($sh, 'ضيف خدمة (إعلانات ممولة') !== false && strpos($sh, 'name="svc[ads][ad_budget]"') !== false && strpos($sh, '➕ خدمة') !== false, 'الخدمات في صفحة العميل');
    [$mc, $mcl, $mp] = $GLOBALS['media'];
    $mi = $m->content_items($mp['id']);
    $mv = render('media', ['month' => '2026-11', 'rows' => $m->content_overview('2026-11')]);
    $mpv = render('media_plan', ['client' => $mcl, 'month' => '2026-11', 'plan' => $mp, 'items' => $mi, 'summary' => $m->content_summary($mp, $mi), 'slots' => $m->publish_slots($mc), 'clients' => $m->content_overview('2026-11'), 'open' => (int) $mi[0]['id']]);
    file_put_contents(sys_get_temp_dir() . '/dse_media_plan.html', $mpv);
    file_put_contents(sys_get_temp_dir() . '/dse_media.html', $mv);
    ok(strpos($mv, 'عيادة د. منى') !== false && strpos($mv, 'افتح الكالندر') !== false, 'صفحة الميديا');
    ok(strpos($mpv, 'هدف الشهر') !== false && strpos($mpv, 'وصلنا 80%') !== false && strpos($mpv, 'مانوال السكربت') !== false && strpos($mpv, '3 غلطات في تنضيف السنان') !== false && strpos($mpv, 'املا الشهر من مواعيد التنزيل') !== false && substr_count($mpv, 'class="panel_s dse-item"') === 13 && strpos($mpv, 'action="/admin/dse_studio/media_plan/' . $mc . '?month=2026-11"') !== false, 'كالندر العميل');
    $cv = render('renewals', ['rows' => [], 'status' => 'churn', 'churn' => $GLOBALS['churn']]);
    ok(strpos($cv, 'ليه العملاء بيمشوا') !== false && strpos($cv, 'هنعمل إيه') !== false && strpos($cv, 'لقى أرخص') !== false && strpos($cv, '🔺') !== false, 'تقرير ليه بيمشوا');
    $rn = render('renewals', ['rows' => $m->renewals(''), 'status' => '', 'churn' => null]);
    ok(strpos($rn, 'عيادة د. سامي') !== false && strpos($rn, 'ابعت طلب التجديد') !== false && strpos($rn, 'آخر طلب') !== false && strpos($rn, 'قديمة') !== false, 'صف التجديد');
    file_put_contents(sys_get_temp_dir() . '/dse_renewals.html', $rn);
    render('outbox', ['outbox' => $m->outbox(), 'enabled' => false]);
    $pub = render('publishing', ['clients' => $m->clients_for_publishing(), 'recent' => $m->recent_posts(), 'selected' => 1, 'due' => [['clientid' => 1, 'company' => 'مطعم الشيف', 'type' => 'reel', 'done' => false], ['clientid' => 2, 'company' => 'x', 'type' => 'design', 'done' => true]]]);
    ok(strpos($pub, 'name="publish_at"') !== false && strpos($pub, 'هينزل الخميس') !== false, 'ميعاد النزول في صفحة النشر');
    ok(strpos($pub, 'لازم ينزل النهارده') !== false && strpos($pub, '⏳ لسه') !== false && strpos($pub, '✅ نزل') !== false, 'مواعيد النهارده');
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

echo "\nصحة الواتساب:\n";
test('الرقم خرج: تنبيه urgent للأدمن مرة واحدة + الرسايل مبتتبعتش ومبتفشلش', function () use ($m, $client, $db, &$sent) {
    update_option('dse_wa_enabled', '1');
    $db->query("UPDATE tbldse_wa_outbox SET status = 'sent' WHERE status IN ('queued','failed')");
    $m->fixed_time = strtotime('2026-11-03 09:00:00');
    ok($m->wa_health_check(true) === [] && $m->wa_health() === null, 'متصل: مفيش حاجة');
    $GLOBALS['evo_state'] = 'close';
    $a = $m->wa_health_check(true);
    ok(count($a) === 1, 'تنبيه');
    $al = q('SELECT * FROM tbldse_alerts WHERE id = ?', [$a[0]])[0];
    ok($al['alert_key'] === 'wa_down' && $al['priority'] === 'urgent' && $al['clientid'] === null && strpos($al['message'], 'Scan') !== false, json_encode($al, JSON_UNESCAPED_UNICODE));
    ok($m->wa_health_check(true) === [], 'مفيش تكرار');
    $n = count($sent);
    for ($i = 0; $i < 3; $i++) { $m->queue_wa($client, 'published', 'down:' . $i, ['post_url' => 'https://x/d' . $i, 'type' => 'reel']); }
    $r = $m->process_outbox(10);
    ok(count($sent) === $n && $r['sent'] === 0 && count(q("SELECT id FROM tbldse_wa_outbox WHERE status = 'queued'")) === 3, json_encode($r));
    ok($m->wa_health()['down'] === true);
    // بعد 6 ساعات تذكير
    $m->fixed_time = strtotime('2026-11-03 15:05:00');
    ok(count($m->wa_health_check(true)) === 1, 'تذكير بعد 6 ساعات');
    // Evolution نفسه مش بيرد
    $GLOBALS['evo_state'] = 'down';
    $m->wa_health_check(true);
    ok(strpos(get_option('dse_wa_last_error'), 'Evolution مش بيرد') === 0, get_option('dse_wa_last_error'));
});
test('رجع: تنبيه "رجع" + المتراكم بيتبعت رسالة واحدة كل نص ساعة', function () use ($m, $db, &$sent) {
    $GLOBALS['evo_state'] = 'open';
    $m->fixed_time = strtotime('2026-11-03 16:00:00');
    $a = $m->wa_health_check(true);
    $al = q('SELECT * FROM tbldse_alerts WHERE id = ?', [$a[0]])[0];
    ok($al['alert_key'] === 'wa_up' && strpos($al['message'], 'كل 30 دقيقة') !== false, $al['message']);
    ok(count(q("SELECT id FROM tbldse_alerts WHERE alert_key = 'wa_down' AND handled_at IS NULL")) === 0, 'تنبيهات الوقعة اتقفلت');
    $n = count($sent);
    $r = $m->process_outbox(10);
    ok($r['sent'] === 1 && count($sent) === $n + 1, json_encode($r));
    $m->fixed_time += 10 * 60;
    ok($m->process_outbox(10)['sent'] === 0, 'لسه مفاتش نص ساعة');
    $m->fixed_time += 21 * 60;
    ok($m->process_outbox(10)['sent'] === 1, 'بعد نص ساعة');
    ok($m->wa_health()['down'] === false && $m->wa_health()['queued'] === 1);
    $m->fixed_time += 31 * 60;
    $m->process_outbox(10);
    ok($m->wa_health() === null && get_option('dse_wa_paced_before') === '', 'خلص المتراكم ← رجوع للطبيعي');
});
test('الرقم متصل بس آخر 3 رسايل فشلت = وقعة · ولما رسالة تتبعت يرجع', function () use ($m, $client, $db) {
    $m->fixed_time += 3600;
    for ($i = 0; $i < 3; $i++) { $m->queue_wa($client, 'welcome', 'ff:' . $i, ['client_name' => 'FAIL ' . $i], true); }
    $m->process_outbox(10);
    ok(count(q("SELECT id FROM tbldse_wa_outbox WHERE status = 'failed' AND dedupe_key LIKE 'ff:%'")) === 3);
    $a = $m->wa_health_check(true);
    ok(count($a) === 1 && get_option('dse_wa_down_state') === 'failing' && strpos(get_option('dse_wa_last_error'), 'آخر 3 رسايل فشلت') !== false, get_option('dse_wa_last_error'));
    $db->query("UPDATE tbldse_wa_outbox SET body = 'تمام' WHERE dedupe_key LIKE 'ff:%'");
    $m->fixed_time += 31 * 60;
    $m->queue_wa($client, 'welcome', 'ff:ok', ['client_name' => 'تمام'], true);
    ok($m->process_outbox(10)['sent'] === 1, 'بنجرب رسالة واحدة');
    $a = $m->wa_health_check(true);
    ok(q('SELECT alert_key FROM tbldse_alerts WHERE id = ?', [$a[0]])[0]['alert_key'] === 'wa_up');
    ok(count(q("SELECT id FROM tbldse_wa_outbox WHERE status = 'queued' AND dedupe_key LIKE 'ff:%'")) === 3, 'اللي فشلوا رجعوا للطابور');
    $db->query("UPDATE tbldse_wa_outbox SET status = 'sent' WHERE status = 'queued'");
    update_option('dse_wa_paced_before', '');
    update_option('dse_wa_enabled', '0');
    $m->fixed_time = strtotime('2026-11-02 10:00:00');
});

test('السيرفر بنسخة قديمة من الداتابيز (من غير renewal_status / historic) والـ version متسجلة: صفحة التجديد بتضيف الأعمدة لوحدها بدل ما تقع', function () use ($m, $db) {
    $db->pdo->exec('ALTER TABLE tbldse_clients RENAME TO tbldse_clients_bak');
    $db->pdo->exec('CREATE TABLE tbldse_clients (clientid INTEGER PRIMARY KEY, wa_phone TEXT, wa_group_jid TEXT, wa_group_link TEXT, updated_at TEXT)');
    $db->pdo->exec('INSERT INTO tbldse_clients (clientid, wa_phone, wa_group_jid, wa_group_link, updated_at) SELECT clientid, wa_phone, wa_group_jid, wa_group_link, updated_at FROM tbldse_clients_bak');
    ok(!$db->field_exists('renewal_status', 'tbldse_clients'));
    update_option('dse_schema_version', DSE_STUDIO_SCHEMA_VERSION); // ensure_schema مش هيعمل حاجة
    dse_studio_ensure_schema();
    Dse_studio_model::$renewal_cols_checked = false; // طلب جديد
    $rows = $m->renewals('');
    ok($db->field_exists('renewal_status', 'tbldse_clients') && $db->field_exists('renewal_updated_at', 'tbldse_clients') && is_array($rows), 'اتضافت');
    ok(dse_studio_add_missing_columns() === [], 'مفيش حاجة ناقصة تاني');
});
echo "\n$passed passed · $failed failed\n";
exit($failed ? 1 : 0);
