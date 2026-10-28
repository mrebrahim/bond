<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * كل قواعد الشغل هنا: الرصيد، الحجز، الخصم، الرسايل، التنبيهات.
 * الكنترولر بينادي الدوال دي بس — ومفيش حسابات في الـ views.
 * الأخطاء بترجع Exception برسالة عربي جاهزة تتعرض للموظف زي ما هي.
 */
class Dse_studio_model extends App_Model
{
    /** @var int|null وقت ثابت للاختبارات */
    public $fixed_time = null;

    /** @var Dse_evolution|null */
    public $evo = null;

    private function t()
    {
        return $this->fixed_time ?? time();
    }

    private function now()
    {
        return date('Y-m-d H:i:s', $this->t());
    }

    private function today()
    {
        return date('Y-m-d', $this->t());
    }

    private function tbl($name)
    {
        return db_prefix() . $name;
    }

    private function rows($sql, $binds = [])
    {
        return $this->db->query($sql, $binds)->result_array();
    }

    private function row($sql, $binds = [])
    {
        $r = $this->db->query($sql, $binds)->row_array();
        return $r ?: null;
    }

    private function opt($k, $default = null)
    {
        $v = get_option($k);
        return ($v === '' || $v === null || $v === false) ? $default : $v;
    }

    private function staff_id()
    {
        return function_exists('get_staff_user_id') ? (get_staff_user_id() ?: null) : null;
    }

    // =================================================================
    // العملاء
    // =================================================================
    /**
     * اسم العميل: الشركة، ولو فاضية اسم جهة الاتصال الأساسية، ولو مفيش الرقم
     * (عملاء الأفراد في Perfex اسمهم بيبقى في جهة الاتصال مش في company)
     */
    public function name_sql($a = 'c')
    {
        // لو الاسم متكتب كامل في الخانتين ("د/ميشيل كرم" + "د/ميشيل كرم") ميتكررش
        $full = "CASE WHEN TRIM(COALESCE(ct.lastname, '')) = '' OR INSTR(ct.firstname, TRIM(ct.lastname)) > 0 THEN ct.firstname"
            . " WHEN INSTR(ct.lastname, TRIM(ct.firstname)) > 0 THEN ct.lastname ELSE CONCAT(ct.firstname, ' ', ct.lastname) END";
        return "COALESCE(NULLIF(TRIM($a.company), ''), (SELECT NULLIF(TRIM($full), '') FROM " . $this->tbl('contacts')
            . " ct WHERE ct.userid = $a.userid ORDER BY ct.is_primary DESC, ct.id LIMIT 1), NULLIF(TRIM($a.phonenumber), ''), CONCAT('عميل #', $a.userid))";
    }

    /** للقايمة: كل العملاء الشغالين بأسمائهم */
    public function clients_list()
    {
        return $this->rows('SELECT c.userid, ' . $this->name_sql('c') . ' AS company FROM ' . $this->tbl('clients') . ' c WHERE c.active = 1 ORDER BY company');
    }

    public function client($clientid)
    {
        $c = $this->row('SELECT c.userid, ' . $this->name_sql('c') . ' AS company, c.phonenumber FROM ' . $this->tbl('clients') . ' c WHERE c.userid = ?', [(int) $clientid]);
        if (!$c) {
            throw new Exception('العميل مش موجود');
        }
        $x = $this->row('SELECT * FROM ' . $this->tbl('dse_clients') . ' WHERE clientid = ?', [(int) $clientid]) ?: [];
        $c['code']          = dse_client_code($c['userid']);
        $c['wa_phone']      = $x['wa_phone'] ?? null ?: dse_normalize_phone($c['phonenumber']);
        $c['wa_group_jid']  = $x['wa_group_jid'] ?? null;
        $c['wa_group_link'] = $x['wa_group_link'] ?? null;
        return $c;
    }

    public function save_client_wa($clientid, array $data)
    {
        $exists = $this->row('SELECT clientid FROM ' . $this->tbl('dse_clients') . ' WHERE clientid = ?', [(int) $clientid]);
        $data['updated_at'] = $this->now();
        if ($exists) {
            $this->db->update($this->tbl('dse_clients'), $data, ['clientid' => (int) $clientid]);
        } else {
            $this->db->insert($this->tbl('dse_clients'), array_merge(['clientid' => (int) $clientid], $data));
        }
    }

    public function set_wa_phone($clientid, $phone)
    {
        $p = dse_normalize_phone($phone);
        if (!$p) {
            throw new Exception('رقم الواتساب لازم يبقى مصري: 01xxxxxxxxx أو 201xxxxxxxxx');
        }
        $this->save_client_wa($clientid, ['wa_phone' => $p]);
    }

    // =================================================================
    // الباقات والرصيد
    // =================================================================
    /**
     * @param array $items ['reel' => 10, 'design' => 8]
     */
    public function create_package($clientid, $name, $price, $start_date, $validity_days, array $items, $invoice_id = null, $notes = null, $historic = false)
    {
        $this->client($clientid);
        $items = array_filter(array_map('floatval', $items), function ($q) { return $q > 0; });
        $items = array_intersect_key($items, dse_types());
        if (!$items) {
            throw new Exception('ضيف خدمة واحدة على الأقل في الباقة');
        }
        $validity_days = (int) $validity_days;
        if ($validity_days < 1) {
            throw new Exception('مدة الباقة لازم تبقى يوم واحد على الأقل');
        }
        $start = $start_date ?: $this->today();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            throw new Exception('تاريخ البداية غلط');
        }
        $end = date('Y-m-d', strtotime($start . ' +' . ($validity_days - 1) . ' days'));
        if ($historic && $end >= $this->today()) {
            throw new Exception('الباقة القديمة لازم تكون خلصت قبل النهارده — لو لسه شغالة اعملها عادي وسجّل الدفعة عليها');
        }

        $this->db->insert($this->tbl('dse_packages'), [
            'clientid'   => (int) $clientid,
            'name'       => trim($name) ?: 'باقة',
            'price'      => (float) $price,
            'start_date' => $start,
            'end_date'   => $end,
            'status'     => 'pending',
            'notes'      => $notes,
            'created_by' => $this->staff_id(),
            'created_at' => $this->now(),
        ]);
        $id = (int) $this->db->insert_id();
        foreach ($items as $type => $qty) {
            $this->db->insert($this->tbl('dse_package_items'), ['package_id' => $id, 'type' => $type, 'qty' => $qty]);
        }
        if ($historic) { // اتدفعت واتصرفت قبل السيستم ← خلصت ومفيهاش رصيد (علشان متتفتحش تاني بفترة السماح)
            $this->db->update($this->tbl('dse_packages'), ['status' => 'expired', 'historic' => 1, 'activated_at' => $start . ' 00:00:00'], ['id' => $id]);
            foreach ($items as $type => $qty) {
                $this->db->insert($this->tbl('dse_usage'), ['package_id' => $id, 'type' => $type, 'qty' => $qty, 'reason' => 'manual',
                    'note' => 'باقة قديمة قبل السيستم', 'staff_id' => $this->staff_id(), 'created_at' => $this->now()]);
            }
            return $id;
        }
        if ($invoice_id) {
            $this->link_invoice($id, $invoice_id, 'الفاتورة الأولى');
        }
        $this->sync_package_status($id);
        return $id;
    }

    public function package($id)
    {
        $p = $this->row('SELECT * FROM ' . $this->tbl('dse_packages') . ' WHERE id = ?', [(int) $id]);
        if (!$p) {
            throw new Exception('الباقة مش موجودة');
        }
        return $p;
    }

    public function client_packages($clientid)
    {
        return $this->rows('SELECT * FROM ' . $this->tbl('dse_packages') . ' WHERE clientid = ? ORDER BY start_date DESC, id DESC', [(int) $clientid]);
    }

    /** الباقة الشغالة النهارده (لو فيه أكتر من واحدة: اللي بتخلص الأول) */
    public function active_package($clientid)
    {
        $this->sync_client_packages($clientid);
        // شغالة = active ولسه جوه مدتها أو فترة السماح (sync بيقفل اللي خلصت) — القديمة الأول علشان رصيدها يخلص الأول
        return $this->row(
            'SELECT * FROM ' . $this->tbl('dse_packages') . " WHERE clientid = ? AND status = 'active' AND start_date <= ? ORDER BY end_date, id LIMIT 1",
            [(int) $clientid, $this->today()]
        );
    }

    /** أيام السماح بعد نهاية الباقة لو فيها رصيد (ريلز / تصميمات لسه ماتعملتش) */
    public function grace_days()
    {
        return max(0, (int) $this->opt('dse_grace_days', 15));
    }

    /** آخر يوم الباقة تفضل شغالة فيه: تاريخ النهاية، أو + فترة السماح لو لسه فيها رصيد */
    public function effective_end(array $p)
    {
        $left = array_sum(array_map(function ($b) { return $b['total'] - $b['used']; }, $this->balance($p['id'])));
        return $left > 0.001 && $this->grace_days() ? date('Y-m-d', strtotime($p['end_date'] . ' +' . $this->grace_days() . ' days')) : $p['end_date'];
    }

    /**
     * الرصيد لكل نوع: الإجمالي − المستخدم − المحجوز
     * @return array type => [total, used, reserved, remaining]
     */
    public function balance($package_id)
    {
        $out = [];
        foreach ($this->rows('SELECT type, qty FROM ' . $this->tbl('dse_package_items') . ' WHERE package_id = ? ORDER BY id', [(int) $package_id]) as $i) {
            $out[$i['type']] = ['total' => (float) $i['qty'], 'used' => 0.0, 'reserved' => 0.0, 'remaining' => 0.0];
        }
        foreach ($this->rows('SELECT type, SUM(qty) AS q FROM ' . $this->tbl('dse_usage') . ' WHERE package_id = ? GROUP BY type', [(int) $package_id]) as $u) {
            if (isset($out[$u['type']])) {
                $out[$u['type']]['used'] = (float) $u['q'];
            }
        }
        foreach ($this->rows('SELECT type, SUM(qty) AS q FROM ' . $this->tbl('dse_sessions') . " WHERE package_id = ? AND status = 'booked' GROUP BY type", [(int) $package_id]) as $s) {
            if (isset($out[$s['type']])) {
                $out[$s['type']]['reserved'] = (float) $s['q'];
            }
        }
        foreach ($out as $k => $b) {
            $out[$k]['remaining'] = max(0, $b['total'] - $b['used'] - $b['reserved']);
        }
        return $out;
    }

    public function balance_text($package_id, $only_remaining = true)
    {
        $parts = [];
        foreach ($this->balance($package_id) as $type => $b) {
            if (!$only_remaining || $b['remaining'] > 0) {
                $parts[] = dse_num($b['remaining']) . ' ' . dse_unit($type);
            }
        }
        return implode(' · ', $parts);
    }

    /**
     * المدفوع من فاتورة واحدة (من مدفوعات Perfex)
     * حالات الفاتورة: 1 مش مدفوعة · 2 مدفوعة · 3 جزئي · 4 متأخرة · 5 ملغية · 6 درافت
     */
    public function invoice_paid_amount($invoice_id)
    {
        $inv = $this->row('SELECT total, status FROM ' . $this->tbl('invoices') . ' WHERE id = ?', [(int) $invoice_id]);
        if (!$inv || (int) $inv['status'] === 5) {
            return 0.0;
        }
        if ((int) $inv['status'] === 2) {
            return (float) $inv['total'];
        }
        $paid = $this->row('SELECT SUM(amount) AS s FROM ' . $this->tbl('invoicepaymentrecords') . ' WHERE invoiceid = ?', [(int) $invoice_id]);
        return min((float) $inv['total'], (float) ($paid['s'] ?? 0));
    }

    /** فواتير الباقة (الأولى + الأقساط + فرق الترقية) ومدفوع من كل واحدة كام */
    public function package_invoices($package_id)
    {
        $rows = $this->rows('SELECT pi.invoice_id, pi.note, i.prefix, i.number, i.total, i.status, i.date FROM ' . $this->tbl('dse_package_invoices') . ' pi
             JOIN ' . $this->tbl('invoices') . ' i ON i.id = pi.invoice_id WHERE pi.package_id = ? ORDER BY pi.id', [(int) $package_id]);
        foreach ($rows as &$r) {
            $r['paid'] = $this->invoice_paid_amount($r['invoice_id']);
        }
        return $rows;
    }

    /** الباقة مدفوع منها كام جنيه (مجموع كل فواتيرها) */
    public function package_paid_amount(array $p)
    {
        $sum = 0.0;
        foreach ($this->rows('SELECT invoice_id FROM ' . $this->tbl('dse_package_invoices') . ' WHERE package_id = ?', [(int) $p['id']]) as $r) {
            $sum += $this->invoice_paid_amount($r['invoice_id']);
        }
        return $sum;
    }

    /**
     * نسبة اللي اتدفع من سعر الباقة (0 → 1).
     * الباقة اللي اتفعلت يدوي ومن غير فواتير = متدفعة كلها.
     */
    public function package_paid_ratio(array $p)
    {
        $has = $this->row('SELECT id FROM ' . $this->tbl('dse_package_invoices') . ' WHERE package_id = ? LIMIT 1', [(int) $p['id']]);
        if (!$has) {
            return $p['status'] === 'pending' ? 0.0 : 1.0;
        }
        if ((float) $p['price'] <= 0) {
            return 1.0;
        }
        return min(1.0, $this->package_paid_amount($p) / (float) $p['price']);
    }

    /** بيفعّل الباقة لما المدفوع يوصل لنسبة التفعيل (50% افتراضي)، وبيقفلها لما مدتها تخلص */
    public function sync_package_status($package_id)
    {
        $p = $this->package($package_id);
        if ($p['status'] === 'pending'
            && $this->package_paid_ratio($p) * 100 >= (float) $this->opt('dse_activation_paid_pct', 50) - 0.001
            && $this->row('SELECT id FROM ' . $this->tbl('dse_package_invoices') . ' WHERE package_id = ? LIMIT 1', [(int) $p['id']])) {
            $this->db->update($this->tbl('dse_packages'), ['status' => 'active', 'activated_at' => $this->now()], ['id' => $p['id']]);
            $p['status'] = 'active';
        }
        if ($p['status'] === 'active' && $p['end_date'] < $this->today() && $this->effective_end($p) < $this->today()) {
            $this->db->update($this->tbl('dse_packages'), ['status' => 'expired'], ['id' => $p['id']]);
            $p['status'] = 'expired';
        }
        // اتقفلت قبل فترة السماح (نسخة قديمة) ولسه فيها رصيد ← ترجع شغالة لحد آخر يوم سماح
        if ($p['status'] === 'expired' && !empty($p['activated_at']) && $this->effective_end($p) >= $this->today()) {
            $this->db->update($this->tbl('dse_packages'), ['status' => 'active'], ['id' => $p['id']]);
            $p['status'] = 'active';
        }
        return $p['status'];
    }

    public function sync_client_packages($clientid)
    {
        foreach ($this->rows('SELECT id FROM ' . $this->tbl('dse_packages') . " WHERE clientid = ? AND (status IN ('pending','active') OR (status = 'expired' AND end_date >= ?))", [(int) $clientid, date('Y-m-d', strtotime($this->today() . ' -' . $this->grace_days() . ' days'))]) as $p) {
            $this->sync_package_status($p['id']);
        }
    }

    /** يربط فاتورة (قسط / فرق ترقية) بالباقة — الفاتورة الواحدة لباقة واحدة بس */
    public function link_invoice($package_id, $invoice_id, $note = null)
    {
        $p   = $this->package($package_id);
        $inv = $this->row('SELECT id, clientid FROM ' . $this->tbl('invoices') . ' WHERE id = ?', [(int) $invoice_id]);
        if (!$inv || (int) $inv['clientid'] !== (int) $p['clientid']) {
            throw new Exception('الفاتورة دي مش بتاعة العميل ده');
        }
        $used = $this->row('SELECT package_id FROM ' . $this->tbl('dse_package_invoices') . ' WHERE invoice_id = ?', [(int) $invoice_id]);
        if ($used) {
            throw new Exception((int) $used['package_id'] === (int) $p['id'] ? 'الفاتورة دي متربطة بالباقة دي خلاص' : 'الفاتورة دي متربطة بباقة تانية');
        }
        $this->db->insert($this->tbl('dse_package_invoices'), [
            'package_id' => $p['id'], 'invoice_id' => (int) $invoice_id, 'note' => $note,
            'created_by' => $this->staff_id(), 'created_at' => $this->now(),
        ]);
        $st = $this->sync_package_status($p['id']);
        $this->check_payment_due($p['id']);
        return $st;
    }

    public function unlink_invoice($package_id, $invoice_id)
    {
        $this->db->query('DELETE FROM ' . $this->tbl('dse_package_invoices') . ' WHERE package_id = ? AND invoice_id = ?', [(int) $package_id, (int) $invoice_id]);
    }

    /**
     * ترقية / تعديل الباقة: العدد الجديد لكل خدمة + السعر الجديد (+ تمديد اختياري + فاتورة الفرق).
     * اللي اتصوّر واتنشر قبل كده بيفضل محسوب من نفس الباقة.
     * مينفعش تنزّل خدمة أقل من اللي اتستخدم + المحجوز.
     */
    public function upgrade_package($package_id, array $items, $new_price, $extend_days = 0, $invoice_id = null, $note = null)
    {
        $p = $this->package($package_id);
        if (!in_array($p['status'], ['pending', 'active'], true)) {
            throw new Exception('الباقة دي خلصت أو اتلغت — اعمل باقة جديدة');
        }
        $bal   = $this->balance($p['id']);
        $items = array_intersect_key(array_map('floatval', $items), dse_types());
        $new   = [];
        foreach (dse_types() as $type => $_) {
            $q = isset($items[$type]) ? max(0, $items[$type]) : ($bal[$type]['total'] ?? 0);
            if (isset($bal[$type]) && $q < $bal[$type]['used'] + $bal[$type]['reserved']) {
                throw new Exception(dse_type_name($type) . ': مينفعش أقل من ' . dse_num($bal[$type]['used'] + $bal[$type]['reserved']) . ' (اتستخدم ' . dse_num($bal[$type]['used']) . ' ومحجوز ' . dse_num($bal[$type]['reserved']) . ')');
            }
            if ($q > 0 || isset($bal[$type])) {
                $new[$type] = $q;
            }
        }
        if (!array_filter($new)) {
            throw new Exception('الباقة لازم يبقى فيها خدمة واحدة على الأقل');
        }
        $new_price = (float) $new_price;
        if ($new_price < 0) {
            throw new Exception('السعر غلط');
        }
        $old_items = array_map(function ($b) { return $b['total']; }, $bal);
        $old_value = array_sum($old_items);
        $new_value = array_sum($new);
        $event     = $new_value > $old_value || $new_price > (float) $p['price'] ? 'upgrade' : ($new_value < $old_value ? 'downgrade' : 'edit');
        $new_end   = (int) $extend_days > 0 ? date('Y-m-d', strtotime($p['end_date'] . ' +' . (int) $extend_days . ' days')) : $p['end_date'];

        foreach ($new as $type => $q) {
            if (isset($bal[$type])) {
                $this->db->update($this->tbl('dse_package_items'), ['qty' => $q], ['package_id' => $p['id'], 'type' => $type]);
            } elseif ($q > 0) {
                $this->db->insert($this->tbl('dse_package_items'), ['package_id' => $p['id'], 'type' => $type, 'qty' => $q]);
            }
        }
        $this->db->update($this->tbl('dse_packages'), ['price' => $new_price, 'end_date' => $new_end], ['id' => $p['id']]);
        $this->db->insert($this->tbl('dse_package_events'), [
            'package_id' => $p['id'], 'event' => $event,
            'old_price' => (float) $p['price'], 'new_price' => $new_price,
            'old_items' => json_encode($old_items), 'new_items' => json_encode($new),
            'old_end_date' => $p['end_date'], 'new_end_date' => $new_end,
            'note' => $note, 'staff_id' => $this->staff_id(), 'created_at' => $this->now(),
        ]);
        if ($invoice_id) {
            $this->link_invoice($p['id'], $invoice_id, $event === 'upgrade' ? 'فرق الترقية' : 'تعديل الباقة');
        }
        $this->sync_package_status($p['id']);
        $this->check_payment_due($p['id']);
        return $event;
    }

    public function package_events($package_id)
    {
        return $this->rows('SELECT * FROM ' . $this->tbl('dse_package_events') . ' WHERE package_id = ? ORDER BY id', [(int) $package_id]);
    }

    /** تفعيل يدوي (أدمن) — مثلًا لو الدفع كاش ومفيش فاتورة */
    public function activate_package($package_id)
    {
        $p = $this->package($package_id);
        if ($p['status'] !== 'pending') {
            throw new Exception('الباقة دي مش مستنية تفعيل');
        }
        $this->db->update($this->tbl('dse_packages'), ['status' => 'active', 'activated_at' => $this->now()], ['id' => $p['id']]);
        return $this->sync_package_status($p['id']);
    }

    public function cancel_package($package_id)
    {
        $p = $this->package($package_id);
        if ($this->row('SELECT id FROM ' . $this->tbl('dse_sessions') . " WHERE package_id = ? AND status = 'booked' LIMIT 1", [$p['id']])) {
            throw new Exception('فيه جلسات محجوزة على الباقة — الغيها الأول');
        }
        $this->db->update($this->tbl('dse_packages'), ['status' => 'cancelled'], ['id' => $p['id']]);
    }

    /** اتلغت بالغلط ← ترجع (مستنية الدفع لحد ما الفواتير تفعّلها تاني) */
    public function restore_package($package_id)
    {
        $p = $this->package($package_id);
        if ($p['status'] !== 'cancelled') {
            throw new Exception('الباقة دي مش ملغية');
        }
        $this->db->update($this->tbl('dse_packages'), ['status' => $p['activated_at'] ? 'active' : 'pending'], ['id' => $p['id']]);
        return $this->sync_package_status($p['id']);
    }

    /** تعديل بيانات الباقة لو اتكتبت غلط: الاسم والتواريخ والملاحظات (العدد والسعر من "ترقية / تعديل") */
    public function edit_package($package_id, $name, $start_date, $end_date, $notes = null)
    {
        $p = $this->package($package_id);
        $name = trim((string) $name);
        if ($name === '') {
            throw new Exception('اكتب اسم الباقة');
        }
        foreach ([$start_date, $end_date] as $d) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d)) {
                throw new Exception('التاريخ غلط');
            }
        }
        if ($end_date < $start_date) {
            throw new Exception('تاريخ النهاية لازم يبقى بعد البداية');
        }
        $out = $this->row('SELECT start_at FROM ' . $this->tbl('dse_sessions') . " WHERE package_id = ? AND status <> 'cancelled' AND (start_at < ? OR start_at >= ?) ORDER BY start_at LIMIT 1",
            [$p['id'], $start_date . ' 00:00:00', date('Y-m-d', strtotime($end_date . ' +1 day')) . ' 00:00:00']);
        if ($out) {
            throw new Exception('فيه جلسة على الباقة يوم ' . dse_day($out['start_at']) . ' — خارج التواريخ الجديدة');
        }
        $this->db->update($this->tbl('dse_packages'), [
            'name' => $name, 'start_date' => $start_date, 'end_date' => $end_date, 'notes' => $notes,
        ], ['id' => $p['id']]);
        if ($p['status'] === 'expired' && $end_date >= $this->today()) {
            $this->db->update($this->tbl('dse_packages'), ['status' => 'active'], ['id' => $p['id']]);
        }
        $this->db->insert($this->tbl('dse_package_events'), [
            'package_id' => $p['id'], 'event' => 'edit', 'old_price' => $p['price'], 'new_price' => $p['price'],
            'old_end_date' => $p['end_date'], 'new_end_date' => $end_date,
            'note' => 'تعديل البيانات' . ($p['name'] !== $name ? ' (الاسم: ' . $p['name'] . ' ← ' . $name . ')' : '') . ($p['start_date'] !== $start_date ? ' (البداية: ' . $start_date . ')' : ''),
            'staff_id' => $this->staff_id(), 'created_at' => $this->now(),
        ]);
        return $this->sync_package_status($p['id']);
    }

    /** الباقة اتسجل عليها حاجة؟ (جلسات / خصم / نشر) — لو آه مينفعش تتمسح، تتلغي بس */
    public function package_has_activity($package_id)
    {
        foreach (['dse_usage' => '', 'dse_posts' => '', 'dse_sessions' => " AND status <> 'cancelled'"] as $t => $extra) {
            if ($this->row('SELECT id FROM ' . $this->tbl($t) . ' WHERE package_id = ?' . $extra . ' LIMIT 1', [(int) $package_id])) {
                return true;
            }
        }
        return false;
    }

    /** ملخص الشغل اللي على الباقة — علشان يبان ليه مينفعش تتمسح */
    public function package_activity_text($package_id)
    {
        $parts = [];
        $done  = $this->row('SELECT COUNT(*) n, SUM(actual_qty) q FROM ' . $this->tbl('dse_sessions') . " WHERE package_id = ? AND status = 'done'", [(int) $package_id]);
        if ((int) $done['n']) {
            $parts[] = (int) $done['n'] . ' جلسة اتصورت (' . dse_num($done['q']) . ')';
        }
        $ns = $this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dse_sessions') . " WHERE package_id = ? AND status = 'no_show'", [(int) $package_id]);
        if ((int) $ns['n']) {
            $parts[] = (int) $ns['n'] . ' مجاش';
        }
        $bk = $this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dse_sessions') . " WHERE package_id = ? AND status = 'booked'", [(int) $package_id]);
        if ((int) $bk['n']) {
            $parts[] = (int) $bk['n'] . ' جلسة محجوزة';
        }
        $po = $this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dse_posts') . ' WHERE package_id = ?', [(int) $package_id]);
        if ((int) $po['n']) {
            $parts[] = (int) $po['n'] . ' بوست نزل';
        }
        $lc = $this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dse_usage') . " WHERE package_id = ? AND reason = 'late_cancel'", [(int) $package_id]);
        if ((int) $lc['n']) {
            $parts[] = (int) $lc['n'] . ' إلغاء متأخر';
        }
        return implode(' · ', $parts);
    }

    /** مسح باقة اتعملت بالغلط — بس لو مفيش عليها أي شغل. الفواتير بتتفك وتفضل في Perfex زي ما هي */
    public function delete_package($package_id)
    {
        $p = $this->package($package_id);
        if ($this->package_has_activity($p['id'])) {
            throw new Exception('الباقة دي اتسجل عليها شغل (تصوير / نشر / خصم) — مينفعش تتمسح، الغيها بدل كده');
        }
        foreach (['dse_package_items', 'dse_package_invoices', 'dse_package_events', 'dse_sessions'] as $t) {
            $this->db->query('DELETE FROM ' . $this->tbl($t) . ' WHERE package_id = ?', [$p['id']]);
        }
        $this->db->query('DELETE FROM ' . $this->tbl('dse_packages') . ' WHERE id = ?', [$p['id']]);
        return $p['clientid'];
    }

    // =================================================================
    // الحجز
    // =================================================================
    public function session_minutes($type, $qty)
    {
        if ($type === 'trainer_hours') {
            return (int) round($qty * 60);
        }
        return (int) max(30, round($qty * (float) $this->opt('dse_minutes_per_reel', 20)));
    }

    public function session_outfits($type, $qty)
    {
        if ($type !== 'reel') {
            return null;
        }
        return (int) ceil($qty / max(1, (float) $this->opt('dse_reels_per_outfit', 3)));
    }

    /** معاينة قبل الحجز: المدة واللبسات والرصيد */
    public function preview_booking($clientid, $type, $qty)
    {
        $pkg = $this->active_package($clientid);
        $bal = $pkg ? $this->balance($pkg['id']) : [];
        return [
            'package'   => $pkg,
            'minutes'   => $this->session_minutes($type, (float) $qty),
            'outfits'   => $this->session_outfits($type, (float) $qty),
            'remaining' => isset($bal[$type]) ? $bal[$type]['remaining'] : 0,
        ];
    }

    private function assert_free($studio_id, $photographer_id, $start, $end, $ignore_id = 0)
    {
        $clash = $this->row(
            'SELECT s.id, s.start_at, st.name AS studio FROM ' . $this->tbl('dse_sessions') . ' s JOIN ' . $this->tbl('dse_studios') . " st ON st.id = s.studio_id
              WHERE s.studio_id = ? AND s.status = 'booked' AND s.id <> ? AND s.start_at < ? AND s.end_at > ? LIMIT 1",
            [(int) $studio_id, (int) $ignore_id, $end, $start]
        );
        if ($clash) {
            throw new Exception('الاستوديو (' . $clash['studio'] . ') محجوز في الوقت ده — فيه جلسة ' . dse_time($clash['start_at']));
        }
        if ($photographer_id) {
            $clash = $this->row(
                'SELECT id, start_at FROM ' . $this->tbl('dse_sessions') . " WHERE photographer_id = ? AND status = 'booked' AND id <> ? AND start_at < ? AND end_at > ? LIMIT 1",
                [(int) $photographer_id, (int) $ignore_id, $end, $start]
            );
            if ($clash) {
                throw new Exception('المصور عنده جلسة تانية في الوقت ده (' . dse_time($clash['start_at']) . ')');
            }
        }
    }

    public function book_session($clientid, $studio_id, $start_at, $type, $qty, $photographer_id = null, $notes = null)
    {
        $types = dse_types();
        if (!isset($types[$type]) || !$types[$type]['session']) {
            throw new Exception('نوع الجلسة غلط');
        }
        $qty = (float) $qty;
        if ($qty <= 0) {
            throw new Exception('الكمية لازم تبقى أكبر من صفر');
        }
        $ts = strtotime((string) $start_at);
        if (!$ts) {
            throw new Exception('اختار ميعاد الجلسة');
        }
        if ($ts < $this->t() - 3600) {
            throw new Exception('الميعاد ده فات');
        }
        $studio = $this->row('SELECT * FROM ' . $this->tbl('dse_studios') . ' WHERE id = ? AND active = 1', [(int) $studio_id]);
        if (!$studio) {
            throw new Exception('اختار الاستوديو');
        }
        $this->client($clientid);
        $pkg = $this->active_package($clientid);
        if (!$pkg) {
            throw new Exception('العميل مفيش عنده باقة شغالة — الباقة بتتفعل بعد ما الفاتورة تتدفع');
        }
        if (date('Y-m-d', $ts) > $this->effective_end($pkg)) {
            throw new Exception('الباقة بتخلص ' . dse_day($this->effective_end($pkg)) . ($this->effective_end($pkg) > $pkg['end_date'] ? ' (بفترة السماح)' : '') . ' — الميعاد بعدها');
        }
        $bal = $this->balance($pkg['id']);
        if (!isset($bal[$type])) {
            throw new Exception('الباقة مفيهاش ' . dse_type_name($type));
        }
        if ($qty > $bal[$type]['remaining']) {
            throw new Exception('الرصيد مش مكفي: فاضل ' . dse_num($bal[$type]['remaining']) . ' ' . dse_unit($type) . ' (بعد الحجوزات التانية)');
        }
        $start = date('Y-m-d H:i:s', $ts);
        $end   = date('Y-m-d H:i:s', $ts + 60 * $this->session_minutes($type, $qty));
        $this->assert_free($studio_id, $photographer_id, $start, $end);

        $this->db->insert($this->tbl('dse_sessions'), [
            'clientid'        => (int) $clientid,
            'package_id'      => (int) $pkg['id'],
            'studio_id'       => (int) $studio_id,
            'photographer_id' => $photographer_id ? (int) $photographer_id : null,
            'type'            => $type,
            'qty'             => $qty,
            'start_at'        => $start,
            'end_at'          => $end,
            'outfits'         => $this->session_outfits($type, $qty),
            'status'          => 'booked',
            'notes'           => $notes,
            'created_by'      => $this->staff_id(),
            'created_at'      => $this->now(),
        ]);
        $id = (int) $this->db->insert_id();
        $this->queue_session_message($id, false);
        return $id;
    }

    public function session($id)
    {
        $s = $this->row(
            'SELECT s.*, st.name AS studio, ' . $this->name_sql('c') . ' AS company FROM ' . $this->tbl('dse_sessions') . ' s
               JOIN ' . $this->tbl('dse_studios') . ' st ON st.id = s.studio_id
               JOIN ' . $this->tbl('clients') . ' c ON c.userid = s.clientid WHERE s.id = ?',
            [(int) $id]
        );
        if (!$s) {
            throw new Exception('الجلسة مش موجودة');
        }
        return $s;
    }

    private function queue_session_message($id, $rescheduled)
    {
        $s = $this->session($id);
        $this->queue_wa($s['clientid'], 'session_booked', 'session_booked:' . $s['id'] . ':' . strtotime($s['start_at']), [
            'start' => $s['start_at'], 'qty' => $s['qty'], 'type' => $s['type'], 'studio' => $s['studio'],
            'outfits' => $s['outfits'], 'rescheduled' => $rescheduled ? '1' : '',
        ]);
    }

    public function reschedule_session($id, $start_at, $studio_id = null, $photographer_id = null)
    {
        $s = $this->session($id);
        if ($s['status'] !== 'booked') {
            throw new Exception('الجلسة دي مش محجوزة');
        }
        $ts = strtotime((string) $start_at);
        if (!$ts || $ts < $this->t() - 3600) {
            throw new Exception('اختار ميعاد جديد صح');
        }
        $pkg = $this->package($s['package_id']);
        if (date('Y-m-d', $ts) > $this->effective_end($pkg)) {
            throw new Exception('الباقة بتخلص ' . dse_day($this->effective_end($pkg)) . ($this->effective_end($pkg) > $pkg['end_date'] ? ' (بفترة السماح)' : '') . ' — الميعاد بعدها');
        }
        $studio_id       = $studio_id ?: $s['studio_id'];
        $photographer_id = $photographer_id === null ? $s['photographer_id'] : ($photographer_id ?: null);
        $start           = date('Y-m-d H:i:s', $ts);
        $end             = date('Y-m-d H:i:s', $ts + (strtotime($s['end_at']) - strtotime($s['start_at'])));
        $this->assert_free($studio_id, $photographer_id, $start, $end, $s['id']);
        $this->db->update($this->tbl('dse_sessions'), [
            'start_at' => $start, 'end_at' => $end, 'studio_id' => (int) $studio_id,
            'photographer_id' => $photographer_id ? (int) $photographer_id : null, 'updated_at' => $this->now(),
        ], ['id' => $s['id']]);
        if ($start !== $s['start_at']) {
            $this->queue_session_message($s['id'], true);
        }
    }

    private function add_usage($package_id, $type, $qty, $reason, $session_id = null, $post_id = null, $note = null)
    {
        if ($qty <= 0) {
            return;
        }
        $this->db->insert($this->tbl('dse_usage'), [
            'package_id' => (int) $package_id, 'type' => $type, 'qty' => $qty, 'reason' => $reason,
            'session_id' => $session_id, 'post_id' => $post_id, 'note' => $note,
            'staff_id' => $this->staff_id(), 'created_at' => $this->now(),
        ]);
    }

    /** الجلسة خلصت: بيخصم اللي اتصور فعلًا */
    public function complete_session($id, $actual_qty)
    {
        $s = $this->session($id);
        if ($s['status'] !== 'booked') {
            throw new Exception('الجلسة دي مش محجوزة');
        }
        $actual_qty = (float) $actual_qty;
        if ($actual_qty <= 0) {
            throw new Exception('اكتب اتصور كام');
        }
        $bal   = $this->balance($s['package_id']);
        $avail = $bal[$s['type']]['remaining'] + (float) $s['qty']; // المحجوز للجلسة دي نفسها
        if ($actual_qty > $avail) {
            throw new Exception('اللي اتصور أكتر من رصيد الباقة (المتاح ' . dse_num($avail) . ')');
        }
        $this->db->update($this->tbl('dse_sessions'), ['status' => 'done', 'actual_qty' => $actual_qty, 'updated_at' => $this->now()], ['id' => $s['id']]);
        $this->add_usage($s['package_id'], $s['type'], $actual_qty, 'session', $s['id']);
        $this->check_payment_due($s['package_id']);
    }

    /**
     * الشغل اللي اتسلّم لكل نوع: الريلز = الأكبر بين اللي اتصور واللي نزل، التصميمات = اللي نزل، الساعات = اللي اتستخدم
     * @return array type => [done, total, ratio]
     */
    public function delivered($package_id)
    {
        $out    = [];
        $posts  = [];
        foreach ($this->rows('SELECT type, COUNT(*) AS n FROM ' . $this->tbl('dse_posts') . ' WHERE package_id = ? GROUP BY type', [(int) $package_id]) as $r) {
            $posts[$r['type']] = (float) $r['n'];
        }
        $filmed = [];
        foreach ($this->rows('SELECT type, SUM(qty) AS q FROM ' . $this->tbl('dse_usage') . " WHERE package_id = ? AND reason = 'session' GROUP BY type", [(int) $package_id]) as $r) {
            $filmed[$r['type']] = (float) $r['q'];
        }
        foreach ($this->balance($package_id) as $type => $b) {
            $done = $type === 'design' ? ($posts['design'] ?? 0) : max($filmed[$type] ?? 0, $posts[$type] ?? 0);
            $out[$type] = ['done' => $done, 'total' => $b['total'], 'ratio' => $b['total'] > 0 ? min(1, $done / $b['total']) : 0];
        }
        return $out;
    }

    /**
     * العميل دافع جزء بس (50% مثلًا) والشغل اللي اتسلّم وصل لنفس النسبة → تنبيه + إيميل لإنجي ومريم
     * (مرة واحدة لكل نسبة دفع — لو دفع زيادة والشغل وصلها تاني بيطلع تنبيه جديد)
     */
    public function check_payment_due($package_id)
    {
        $p = $this->package($package_id);
        if ($p['status'] !== 'active') {
            return false;
        }
        $paid = $this->package_paid_ratio($p);
        if ($paid >= 0.999) {
            return false;
        }
        $del  = $this->delivered($p['id']);
        $hit  = array_filter($del, function ($d) use ($paid) { return $d['total'] > 0 && $d['ratio'] >= $paid - 0.001; });
        if (!$hit) {
            return false;
        }
        $c     = $this->client($p['clientid']);
        $parts = [];
        foreach ($hit as $type => $d) {
            $parts[] = dse_num($d['done']) . ' من ' . dse_num($d['total']) . ' ' . dse_unit($type);
        }
        $pct    = (int) round($paid * 100);
        $amount = $this->package_paid_amount($p);
        $msg    = $c['company'] . ' دافع ' . $pct . '% بس من باقة "' . $p['name'] . '" (' . dse_money($amount) . ' من ' . dse_money($p['price'])
            . ' — الباقي ' . dse_money(max(0, $p['price'] - $amount)) . ') والشغل وصل لـ ' . implode(' · ', $parts) . ' — لازم يدفع الباقي قبل ما نكمل.';
        $units  = array_sum(array_column($del, 'total'));
        $id     = $this->add_alert($p['clientid'], 'payment_due', 'payment_due:' . $p['id'] . ':' . $pct . ':' . dse_num($units) . ':' . dse_num($p['price']), $msg, 'urgent');
        if ($id) {
            $this->new_alerts[] = $id;
        }
        return $id;
    }

    /** @var int[] تنبيهات اتعملت في الطلب ده (الكنترولر بيبعت عنها إشعار وإيميل) */
    public $new_alerts = [];

    /**
     * صحّح العدد: جلسة اتقفلت بعدد غلط ← بيعدّل اللي اتصور واللي اتخصم من الرصيد، ويسجّل مين صحّح وليه
     */
    public function correct_session_qty($id, $new_qty, $reason)
    {
        $s = $this->session($id);
        if ($s['status'] !== 'done') {
            throw new Exception('التصحيح للجلسات اللي اتقفلت بس ("اتصورت")');
        }
        $new_qty = (float) $new_qty;
        $old     = (float) $s['actual_qty'];
        if ($new_qty <= 0) {
            throw new Exception('العدد لازم يبقى أكبر من صفر — لو العميل مجاش اكتب ده في السبب وكلّم الأدمن');
        }
        if (trim((string) $reason) === '') {
            throw new Exception('اكتب سبب التصحيح');
        }
        if (abs($new_qty - $old) < 0.001) {
            throw new Exception('ده نفس العدد المتسجل');
        }
        $bal   = $this->balance($s['package_id']);
        $avail = ($bal[$s['type']]['remaining'] ?? 0) + $old;
        if ($new_qty > $avail) {
            throw new Exception('العدد أكبر من رصيد الباقة (المتاح ' . dse_num($avail) . ')');
        }
        $who  = function_exists('get_staff_full_name') ? get_staff_full_name() : '';
        $line = 'تصحيح العدد ' . dse_num($old) . ' ← ' . dse_num($new_qty) . ': ' . trim($reason) . ($who ? ' (' . $who . ')' : '') . ' — ' . date('d/m H:i', $this->t());
        $this->db->update($this->tbl('dse_sessions'), [
            'actual_qty' => $new_qty, 'updated_at' => $this->now(),
            'notes' => trim(($s['notes'] ? $s['notes'] . "\n" : '') . $line),
        ], ['id' => $s['id']]);
        $this->db->update($this->tbl('dse_usage'), ['qty' => $new_qty, 'note' => mb_substr($line, 0, 255)], ['session_id' => $s['id'], 'reason' => 'session']);
        $this->check_payment_due($s['package_id']);
        return $old;
    }

    private function penalty($s, $avail_extra = 0)
    {
        $bal   = $this->balance($s['package_id']);
        $avail = $bal[$s['type']]['remaining'] + $avail_extra;
        return min((float) $this->opt('dse_penalty_units', 1), (float) $s['qty'], $avail);
    }

    public function mark_no_show($id)
    {
        $s = $this->session($id);
        if ($s['status'] !== 'booked') {
            throw new Exception('الجلسة دي مش محجوزة');
        }
        $pen = $this->penalty($s, (float) $s['qty']);
        $this->db->update($this->tbl('dse_sessions'), ['status' => 'no_show', 'updated_at' => $this->now()], ['id' => $s['id']]);
        $this->add_usage($s['package_id'], $s['type'], $pen, 'no_show', $s['id'], null, 'خصم الغياب');
        return $pen;
    }

    /** الإلغاء قبل الميعاد بأقل من dse_late_cancel_hours بيتخصم */
    public function cancel_session($id, $reason = null)
    {
        $s = $this->session($id);
        if ($s['status'] !== 'booked') {
            throw new Exception('الجلسة دي مش محجوزة');
        }
        $late = strtotime($s['start_at']) - $this->t() < 3600 * (float) $this->opt('dse_late_cancel_hours', 24);
        $pen  = $late ? $this->penalty($s, (float) $s['qty']) : 0;
        $this->db->update($this->tbl('dse_sessions'), [
            'status' => 'cancelled', 'late_cancel' => $late ? 1 : 0, 'updated_at' => $this->now(),
            'notes' => trim(($s['notes'] ? $s['notes'] . "\n" : '') . ($reason ? 'سبب الإلغاء: ' . $reason : '')) ?: null,
        ], ['id' => $s['id']]);
        $this->add_usage($s['package_id'], $s['type'], $pen, 'late_cancel', $s['id'], null, 'إلغاء متأخر');
        return ['late' => $late, 'penalty' => $pen];
    }

    public function sessions_between($from, $to, $clientid = null, $studio_id = null, $with_cancelled = true)
    {
        $sql   = 'SELECT s.*, st.name AS studio, ' . $this->name_sql('c') . ' AS company,
                         (SELECT CONCAT(sf.firstname, \' \', sf.lastname) FROM ' . $this->tbl('staff') . ' sf WHERE sf.staffid = s.photographer_id) AS photographer
                    FROM ' . $this->tbl('dse_sessions') . ' s
                    JOIN ' . $this->tbl('dse_studios') . ' st ON st.id = s.studio_id
                    JOIN ' . $this->tbl('clients') . ' c ON c.userid = s.clientid
                   WHERE s.start_at >= ? AND s.start_at < ?';
        $binds = [$from, $to];
        if ($clientid) {
            $sql .= ' AND s.clientid = ?';
            $binds[] = (int) $clientid;
        }
        if ($studio_id) {
            $sql .= ' AND s.studio_id = ?';
            $binds[] = (int) $studio_id;
        }
        if (!$with_cancelled) {
            $sql .= " AND s.status <> 'cancelled'";
        }
        return $this->rows($sql . ' ORDER BY s.start_at', $binds);
    }

    public function client_sessions($clientid, $limit = 30)
    {
        return $this->rows(
            'SELECT s.*, st.name AS studio FROM ' . $this->tbl('dse_sessions') . ' s JOIN ' . $this->tbl('dse_studios') . ' st ON st.id = s.studio_id
              WHERE s.clientid = ? ORDER BY s.start_at DESC LIMIT ' . (int) $limit,
            [(int) $clientid]
        );
    }

    // =================================================================
    // الاستوديوهات
    // =================================================================
    public function studios($active_only = true)
    {
        return $this->rows('SELECT * FROM ' . $this->tbl('dse_studios') . ($active_only ? ' WHERE active = 1' : '') . ' ORDER BY id');
    }

    public function add_studio($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw new Exception('اكتب اسم الاستوديو');
        }
        $this->db->insert($this->tbl('dse_studios'), ['name' => $name, 'active' => 1]);
        return (int) $this->db->insert_id();
    }

    public function toggle_studio($id)
    {
        $s = $this->row('SELECT * FROM ' . $this->tbl('dse_studios') . ' WHERE id = ?', [(int) $id]);
        if ($s) {
            $this->db->update($this->tbl('dse_studios'), ['active' => $s['active'] ? 0 : 1], ['id' => $s['id']]);
        }
    }

    // =================================================================
    // السكربتات والنشر
    // =================================================================
    public function add_script($clientid, $title, $body)
    {
        $this->client($clientid);
        if (trim((string) $title) === '' || trim((string) $body) === '') {
            throw new Exception('اكتب عنوان السكربت والنص');
        }
        $this->db->insert($this->tbl('dse_scripts'), [
            'clientid' => (int) $clientid, 'title' => trim($title), 'body' => trim($body), 'status' => 'draft',
            'created_by' => $this->staff_id(), 'created_at' => $this->now(),
        ]);
        return (int) $this->db->insert_id();
    }

    public function script($id)
    {
        $s = $this->row('SELECT * FROM ' . $this->tbl('dse_scripts') . ' WHERE id = ?', [(int) $id]);
        if (!$s) {
            throw new Exception('السكربت مش موجود');
        }
        return $s;
    }

    public function update_script($id, $title, $body)
    {
        $s = $this->script($id);
        if (trim((string) $title) === '' || trim((string) $body) === '') {
            throw new Exception('اكتب عنوان السكربت والنص');
        }
        $this->db->update($this->tbl('dse_scripts'), ['title' => trim($title), 'body' => trim($body)], ['id' => $s['id']]);
    }

    /** ابعت للعميل — رسالة واحدة لكل نسخة من السكربت */
    public function send_script($id)
    {
        $s = $this->script($id);
        if ($s['status'] === 'approved') {
            throw new Exception('السكربت ده العميل وافق عليه خلاص');
        }
        $rev = (int) $s['revision'] + ($s['status'] === 'draft' ? 0 : 1);
        $this->db->update($this->tbl('dse_scripts'), ['status' => 'sent', 'revision' => $rev, 'sent_at' => $this->now()], ['id' => $s['id']]);
        return $this->queue_wa($s['clientid'], 'script_sent', 'script_sent:' . $s['id'] . ':' . $rev, ['title' => $s['title'], 'body' => $s['body']]);
    }

    public function set_script_status($id, $status)
    {
        if (!in_array($status, ['approved', 'changes'], true)) {
            throw new Exception('حالة غلط');
        }
        $s = $this->script($id);
        $this->db->update($this->tbl('dse_scripts'), ['status' => $status], ['id' => $s['id']]);
    }

    public function client_scripts($clientid)
    {
        return $this->rows('SELECT * FROM ' . $this->tbl('dse_scripts') . ' WHERE clientid = ? ORDER BY id DESC', [(int) $clientid]);
    }

    /** البوست نزل: رسالة للعميل فيها اللينك ولينك التقييم والرصيد — والتصميم بيتخصم */
    public function publish_post($clientid, $type, $post_url, $title = null)
    {
        $this->client($clientid);
        $post_url = trim((string) $post_url);
        if (!preg_match('~^https?://~i', $post_url)) {
            throw new Exception('حط لينك البوست (https://...)');
        }
        if (!in_array($type, ['reel', 'design'], true)) {
            throw new Exception('نوع البوست غلط');
        }
        $pkg = $this->active_package($clientid);
        if ($type === 'design') {
            if (!$pkg) {
                throw new Exception('العميل مفيش عنده باقة شغالة');
            }
            $bal = $this->balance($pkg['id']);
            if (!isset($bal['design']) || $bal['design']['remaining'] < 1) {
                throw new Exception('رصيد التصميمات خلص');
            }
        }
        $this->db->insert($this->tbl('dse_posts'), [
            'clientid' => (int) $clientid, 'package_id' => $pkg ? (int) $pkg['id'] : null, 'type' => $type,
            'title' => $title, 'post_url' => $post_url, 'published_by' => $this->staff_id(), 'published_at' => $this->now(),
        ]);
        $id = (int) $this->db->insert_id();
        if ($type === 'design') {
            $this->add_usage($pkg['id'], 'design', 1, 'post', null, $id);
        }
        $feedback = strtr((string) $this->opt('dse_feedback_url', ''), [
            '{client_code}' => dse_client_code($clientid), '{post_id}' => (string) $id,
        ]);
        $this->queue_wa($clientid, 'published', 'published:' . $id, [
            'type' => $type, 'post_url' => $post_url, 'feedback_url' => $feedback,
            'balance' => $pkg ? $this->balance_text($pkg['id']) : '', 'end_date' => $pkg ? $pkg['end_date'] : '',
        ]);
        if ($pkg) {
            $this->check_payment_due($pkg['id']);
        }
        return $id;
    }

    /** العملاء اللي عندهم جروب أو باقة — للمودريشن */
    public function clients_for_publishing()
    {
        return $this->rows('SELECT c.userid, ' . $this->name_sql('c') . ' AS company, x.wa_group_jid,
                   (SELECT p.status FROM ' . $this->tbl('dse_packages') . " p WHERE p.clientid = c.userid AND p.status = 'active' LIMIT 1) AS pkg
              FROM " . $this->tbl('clients') . ' c LEFT JOIN ' . $this->tbl('dse_clients') . ' x ON x.clientid = c.userid
             WHERE c.active = 1 ORDER BY (x.wa_group_jid IS NULL), company');
    }

    public function recent_posts($limit = 40)
    {
        return $this->rows('SELECT po.*, ' . $this->name_sql('c') . ' AS company,
                   (SELECT o.status FROM ' . $this->tbl('dse_wa_outbox') . " o WHERE o.dedupe_key = CONCAT('published:', po.id)) AS wa_status
              FROM " . $this->tbl('dse_posts') . ' po JOIN ' . $this->tbl('clients') . ' c ON c.userid = po.clientid
             ORDER BY po.id DESC LIMIT ' . (int) $limit);
    }

    public function client_posts($clientid, $limit = 30)
    {
        return $this->rows('SELECT * FROM ' . $this->tbl('dse_posts') . ' WHERE clientid = ? ORDER BY id DESC LIMIT ' . (int) $limit, [(int) $clientid]);
    }

    // =================================================================
    // الواتساب: جروب العميل + الـ outbox (بيتبعت من الـ cron)
    // =================================================================
    /** رسايل العميل بتروح جروبه بس — مفيش خاص */
    public function wa_destination($clientid)
    {
        $c = $this->client($clientid);
        return $c['wa_group_jid'] ?: null;
    }

    /**
     * بيحط رسالة في الطابور مرة واحدة بس لكل dedupe_key.
     * لو العميل مالوش جروب: الرسالة اليدوية بترجع خطأ، والأوتوماتيك بيطلع تنبيه للفريق (ومبتتبعتش خاص)
     */
    public function queue_wa($clientid, $key, $dedupe, array $payload, $manual = false)
    {
        if ($this->row('SELECT id FROM ' . $this->tbl('dse_wa_outbox') . ' WHERE dedupe_key = ?', [$dedupe])) {
            return false;
        }
        $body = dse_wa_text($key, $payload);
        if ($body === null || $body === '') {
            return false;
        }
        $dest = $this->wa_destination($clientid);
        if (!$dest) {
            if ($manual) {
                throw new Exception('العميل مالوش جروب واتساب — اعمل الجروب أو اربطه الأول (الرسايل بتروح الجروب بس)');
            }
            $c  = $this->client($clientid);
            $id = $this->add_alert($clientid, 'no_group', 'no_group:' . $clientid . ':' . $this->today(),
                $c['company'] . ' مالوش جروب واتساب — رسالة "' . (dse_wa_labels()[$key] ?? $key) . '" ماتبعتتش. اعمل الجروب من صفحة العميل.');
            if ($id) {
                $this->new_alerts[] = $id;
            }
            return false;
        }
        $this->db->insert($this->tbl('dse_wa_outbox'), [
            'clientid' => (int) $clientid, 'msg_key' => $key, 'dedupe_key' => $dedupe, 'destination' => $dest,
            'body' => $body, 'manual' => $manual ? 1 : 0, 'status' => 'queued',
            'created_by' => $this->staff_id(), 'created_at' => $this->now(),
        ]);
        return (int) $this->db->insert_id();
    }

    /** رسايل المانوال (زرار من بروفايل العميل) */
    public function send_manual($clientid, $key)
    {
        if (!isset(dse_manual_keys()[$key])) {
            throw new Exception('نوع رسالة غير معروف');
        }
        $c    = $this->client($clientid);
        $last = $this->last_packages()[(int) $clientid] ?? null;
        $id   = $this->queue_wa($clientid, $key, 'manual:' . $key . ':' . $clientid . ':' . microtime(true), [
            'client_name' => $c['company'], 'rules' => $this->opt('dse_rules_text', ''),
            'package_name' => $last['name'] ?? '', 'end_date' => $last['end_date'] ?? '',
        ], true);
        if ($key === 'renewal_request') {
            $x = $this->row('SELECT renewal_status FROM ' . $this->tbl('dse_clients') . ' WHERE clientid = ?', [(int) $clientid]);
            if (empty($x['renewal_status'])) {
                $this->set_renewal_status($clientid, 'contacted', 'اتبعتله طلب التجديد على الجروب');
            }
        }
        return $id;
    }

    /**
     * بيبعت اللي في الطابور: بيحجز الرسالة قبل الإرسال (مستحيل تتبعت مرتين)،
     * والأوتوماتيك ليه حد في اليوم لكل جروب علشان الرقم مياخدش بان.
     */
    public function process_outbox($limit = 10)
    {
        $res = ['sent' => 0, 'failed' => 0, 'waiting' => 0];
        if ($this->opt('dse_wa_enabled', '0') !== '1' || !$this->evo || !$this->evo->configured()) {
            return $res;
        }
        $cap   = (int) $this->opt('dse_wa_daily_cap', 6);
        $since = date('Y-m-d H:i:s', $this->t() - 86400);
        $queue = $this->rows('SELECT * FROM ' . $this->tbl('dse_wa_outbox') . " WHERE status = 'queued' ORDER BY manual DESC, id LIMIT " . (int) ($limit * 3));
        $done  = 0;
        foreach ($queue as $m) {
            if ($done >= $limit) {
                break;
            }
            if (substr($m['destination'], -5) !== '@g.us') {
                $this->db->update($this->tbl('dse_wa_outbox'), ['status' => 'failed', 'error' => 'مش جروب — رسايل العملاء بتروح الجروب بس'], ['id' => $m['id'], 'status' => 'queued']);
                continue;
            }
            if (!$m['manual']) {
                $n = $this->row('SELECT COUNT(*) AS n FROM ' . $this->tbl('dse_wa_outbox') . " WHERE destination = ? AND manual = 0 AND status IN ('sent','sending') AND sent_at >= ?", [$m['destination'], $since]);
                if ((int) $n['n'] >= $cap) {
                    $res['waiting']++;
                    continue;
                }
            }
            // claim
            $this->db->update($this->tbl('dse_wa_outbox'), ['status' => 'sending', 'sent_at' => $this->now()], ['id' => $m['id'], 'status' => 'queued']);
            if ($this->db->affected_rows() !== 1) {
                continue;
            }
            $done++;
            try {
                $this->evo->send_text($m['destination'], $m['body']);
                $this->db->update($this->tbl('dse_wa_outbox'), ['status' => 'sent'], ['id' => $m['id']]);
                $res['sent']++;
            } catch (Exception $e) {
                $this->db->update($this->tbl('dse_wa_outbox'), ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)], ['id' => $m['id']]);
                $res['failed']++;
            }
        }
        return $res;
    }

    public function retry_message($id)
    {
        $this->db->update($this->tbl('dse_wa_outbox'), ['status' => 'queued', 'error' => null], ['id' => (int) $id, 'status' => 'failed']);
    }

    public function outbox($limit = 100, $clientid = null)
    {
        $sql = 'SELECT o.*, ' . $this->name_sql('c') . ' AS company FROM ' . $this->tbl('dse_wa_outbox') . ' o JOIN ' . $this->tbl('clients') . ' c ON c.userid = o.clientid';
        $b   = [];
        if ($clientid) {
            $sql .= ' WHERE o.clientid = ?';
            $b[] = (int) $clientid;
        }
        return $this->rows($sql . ' ORDER BY o.id DESC LIMIT ' . (int) $limit, $b);
    }

    public function create_group($clientid)
    {
        $c = $this->client($clientid);
        if ($c['wa_group_jid']) {
            throw new Exception('العميل عنده جروب خلاص');
        }
        if (!$c['wa_phone']) {
            throw new Exception('حط رقم واتساب العميل الأول');
        }
        if (!$this->evo || !$this->evo->configured()) {
            throw new Exception('ظبط Evolution API من إعدادات الـ module الأول');
        }
        $members = array_values(array_unique(array_merge([$c['wa_phone']], dse_phone_list($this->opt('dse_wa_team_numbers', '')))));
        list($jid, $link) = $this->evo->create_group($c['company'] . ' × Digital Solution', 'جروب المتابعة مع Digital Solution — مواعيد التصوير والمحتوى والتحديثات', $members);
        $this->save_client_wa($clientid, ['wa_group_jid' => $jid, 'wa_group_link' => $link]);
        return ['jid' => $jid, 'link' => $link];
    }

    public function link_group($clientid, $link)
    {
        if (!$this->evo || !$this->evo->configured()) {
            throw new Exception('ظبط Evolution API من إعدادات الـ module الأول');
        }
        list($jid, $url) = $this->evo->group_from_link($link);
        $this->save_client_wa($clientid, ['wa_group_jid' => $jid, 'wa_group_link' => $url]);
        return $jid;
    }

    public function add_team_to_group($clientid)
    {
        $c = $this->client($clientid);
        if (!$c['wa_group_jid']) {
            throw new Exception('العميل مالوش جروب لسه');
        }
        return $this->evo->add_members($c['wa_group_jid'], dse_phone_list($this->opt('dse_wa_team_numbers', '')));
    }

    // =================================================================
    // التنبيهات للفريق
    // =================================================================
    public function add_alert($clientid, $key, $dedupe, $message, $priority = 'normal')
    {
        if ($this->row('SELECT id FROM ' . $this->tbl('dse_alerts') . ' WHERE dedupe_key = ?', [$dedupe])) {
            return false;
        }
        $this->db->insert($this->tbl('dse_alerts'), [
            'clientid' => $clientid ? (int) $clientid : null, 'alert_key' => $key, 'dedupe_key' => $dedupe,
            'message' => $message, 'priority' => $priority, 'created_at' => $this->now(),
        ]);
        return (int) $this->db->insert_id();
    }

    public function alerts($open_only = true, $limit = 200)
    {
        return $this->rows(
            'SELECT a.*, ' . $this->name_sql('c') . ' AS company FROM ' . $this->tbl('dse_alerts') . ' a LEFT JOIN ' . $this->tbl('clients') . ' c ON c.userid = a.clientid'
            . ($open_only ? ' WHERE a.handled_at IS NULL' : '') . " ORDER BY a.handled_at IS NULL DESC, a.priority = 'urgent' DESC, a.id DESC LIMIT " . (int) $limit
        );
    }

    public function resolve_alert($id, $resolution)
    {
        if (trim((string) $resolution) === '') {
            throw new Exception('اكتب حصل إيه');
        }
        $this->db->update($this->tbl('dse_alerts'), [
            'handled_at' => $this->now(), 'handled_by' => $this->staff_id(), 'resolution' => trim($resolution),
        ], ['id' => (int) $id]);
    }

    /**
     * العميل بقاله فترة مابيصوّرش: عنده باقة شغالة فيها رصيد، وآخر تصوير (أو بداية الباقة)
     * من أكتر من dse_inactive_days يوم، ومفيش حجز جاي.
     * لو مفيش أي ريل جاهز للنشر خلال الأسبوع اللي فات يبقى urgent.
     * @return int[] ids التنبيهات الجديدة
     */
    public function check_inactive_clients()
    {
        $days  = (int) $this->opt('dse_inactive_days', 21);
        $limit = date('Y-m-d', $this->t() - 86400 * $days);
        $new   = [];
        foreach ($this->rows('SELECT DISTINCT clientid FROM ' . $this->tbl('dse_packages') . " WHERE status IN ('pending','active')") as $r) {
            $pkg = $this->active_package($r['clientid']);
            if (!$pkg) {
                continue;
            }
            $bal  = $this->balance($pkg['id']);
            $left = 0;
            foreach (['reel', 'trainer_hours'] as $t) {
                $left += isset($bal[$t]) ? $bal[$t]['remaining'] : 0;
            }
            if ($left <= 0) {
                continue;
            }
            $last = $this->row('SELECT MAX(end_at) AS d FROM ' . $this->tbl('dse_sessions') . " WHERE clientid = ? AND status = 'done'", [$r['clientid']]);
            $from = max($last && $last['d'] ? substr($last['d'], 0, 10) : '0000-00-00', $pkg['start_date']);
            if ($from > $limit) {
                continue;
            }
            if ($this->row('SELECT id FROM ' . $this->tbl('dse_sessions') . " WHERE clientid = ? AND status = 'booked' AND start_at > ? LIMIT 1", [$r['clientid'], $this->now()])) {
                continue;
            }
            $recent = $this->row('SELECT COUNT(*) AS n FROM ' . $this->tbl('dse_posts') . ' WHERE clientid = ? AND published_at >= ?', [$r['clientid'], date('Y-m-d H:i:s', $this->t() - 7 * 86400)]);
            $out    = (int) $recent['n'] === 0;
            $c      = $this->client($r['clientid']);
            $since  = (int) floor(($this->t() - strtotime($from)) / 86400);
            $msg    = $c['company'] . ' بقاله ' . $since . ' يوم مابيصوّرش'
                . ($last && $last['d'] ? ' (آخر تصوير ' . dse_day($last['d']) . ')' : ' (مصوّرش خالص في الباقة دي)')
                . ' · فاضله ' . $this->balance_text($pkg['id'])
                . ($out ? ' · ⚠️ مفيش حاجة نزلت على السوشيال بتاعه آخر أسبوع — المحتوى خلص' : '')
                . ' · كلّمه واحجزله ميعاد.';
            $id = $this->add_alert($r['clientid'], 'client_inactive', 'inactive:' . $r['clientid'] . ':' . $from, $msg, $out ? 'urgent' : 'normal');
            if ($id) {
                $new[] = $id;
            }
        }
        return $new;
    }

    /** كل الباقات الشغالة: هل الشغل سبق الفلوس؟ (مع الـ cron — لو اتسجل دفع أو اتلغى) */
    public function check_all_payments()
    {
        $this->new_alerts = [];
        foreach ($this->rows('SELECT id FROM ' . $this->tbl('dse_packages') . " WHERE status = 'active'") as $p) {
            $this->check_payment_due($p['id']);
        }
        return $this->new_alerts;
    }

    /** كل الباقات: تفعيل بالفاتورة + انتهاء المدة */
    // =================================================================
    // الدفعات (أقساط من غير عدد) + التجديد
    // =================================================================
    /** موديل Perfex (invoices_model / payments_model) لو موجود — في الاختبارات بيرجع null */
    private function perfex_model($name)
    {
        if (!function_exists('get_instance')) {
            return null;
        }
        $CI = &get_instance();
        if (!is_object($CI) || !isset($CI->load) || !method_exists($CI->load, 'model')) {
            return null;
        }
        try {
            $CI->load->model($name);
        } catch (Throwable $e) {
            return null;
        }
        return isset($CI->$name) && is_object($CI->$name) ? $CI->$name : null;
    }

    public function payment_modes()
    {
        if (!$this->db->table_exists($this->tbl('payment_modes'))) {
            return [];
        }
        return $this->rows('SELECT id, name FROM ' . $this->tbl('payment_modes') . ' WHERE active = 1 ORDER BY id');
    }

    /** كل الدفعات اللي اتسجلت على فواتير الباقة */
    public function package_payments($package_id)
    {
        $modes = $this->db->table_exists($this->tbl('payment_modes'));
        return $this->rows('SELECT r.*' . ($modes ? ', pm.name AS mode_name' : '') . ' FROM ' . $this->tbl('invoicepaymentrecords') . ' r
              JOIN ' . $this->tbl('dse_package_invoices') . ' pi ON pi.invoice_id = r.invoiceid'
            . ($modes ? ' LEFT JOIN ' . $this->tbl('payment_modes') . ' pm ON pm.id = r.paymentmode' : '')
            . ' WHERE pi.package_id = ? ORDER BY r.date, r.id', [(int) $package_id]);
    }

    /** فاتورة Perfex للباقة بالمبلغ ده (لما مفيش فاتورة مربوطة فيها مكان للدفعة) */
    private function create_package_invoice(array $p, $amount, $label)
    {
        $c        = $this->row('SELECT default_currency FROM ' . $this->tbl('clients') . ' WHERE userid = ?', [(int) $p['clientid']]);
        $currency = (int) ($c['default_currency'] ?? 0);
        if (!$currency && $this->db->table_exists($this->tbl('currencies'))) {
            $currency = (int) ($this->row('SELECT id FROM ' . $this->tbl('currencies') . ' WHERE isdefault = 1')['id'] ?? 0);
        }
        $desc = $label . ' — ' . $p['name'];
        $long = dse_day($p['start_date']) . ' ← ' . dse_day($p['end_date']);
        $inv  = $this->perfex_model('invoices_model');
        if ($inv) {
            $d  = function ($v) { return function_exists('_d') ? _d($v) : $v; };
            $id = $inv->add([
                'clientid' => (int) $p['clientid'], 'number' => get_option('next_invoice_number'), 'date' => $d($this->today()), 'duedate' => $d($this->today()),
                'currency' => $currency, 'subtotal' => $amount, 'total' => $amount, 'discount_total' => 0, 'adjustment' => 0, 'show_quantity_as' => 1,
                'clientnote' => '', 'terms' => '', 'adminnote' => 'اتعملت من الاستوديو والباقات',
                'newitems' => [['description' => $desc, 'long_description' => $long, 'qty' => 1, 'unit' => '', 'rate' => $amount, 'order' => 1, 'taxname' => []]],
            ]);
            if (!$id) {
                throw new Exception('Perfex مارضيش يعمل الفاتورة — اعملها من الفواتير واربطها');
            }
        } else { // من غير Perfex (الاختبارات)
            $this->db->insert($this->tbl('invoices'), ['clientid' => (int) $p['clientid'], 'total' => $amount, 'status' => 1, 'date' => $this->today()]);
            $id = (int) $this->db->insert_id();
        }
        $this->db->insert($this->tbl('dse_package_invoices'), [
            'package_id' => $p['id'], 'invoice_id' => (int) $id, 'note' => $label, 'created_by' => $this->staff_id(), 'created_at' => $this->now(),
        ]);
        return (int) $id;
    }

    /**
     * سجّل دفعة على الباقة — من غير حد لعدد الدفعات.
     * بتتسجل Payment على فاتورة الباقة في Perfex (أول فاتورة فيها باقي)، ولو الفواتير المربوطة مش مكفية سعر الباقة
     * بتتعمل فاتورة بالباقي لوحدها. بعدها الباقة بتتفعل لو وصلت نسبة التفعيل، وتنبيه "الشغل عدّى المدفوع" بيتحسب تاني.
     */
    public function record_payment($package_id, $amount, $date = null, $mode = null, $note = null)
    {
        $p      = $this->package($package_id);
        $amount = round((float) $amount, 2);
        if ($p['status'] === 'cancelled') {
            throw new Exception('الباقة دي اتلغت — رجّعها الأول لو الدفعة عليها');
        }
        if ($amount <= 0) {
            throw new Exception('اكتب المبلغ');
        }
        $date = $date ?: $this->today();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > $this->today()) {
            throw new Exception('تاريخ الدفعة غلط (مينفعش يبقى في المستقبل)');
        }
        $left = (float) $p['price'] - $this->package_paid_amount($p);
        if ($amount > $left + 0.5) {
            throw new Exception('المبلغ أكبر من الباقي على الباقة (' . dse_money(max(0, $left)) . ')');
        }
        $modes = $this->payment_modes();
        $mode  = (int) $mode ?: (int) ($modes[0]['id'] ?? 0);

        // الفواتير اللي فيها مكان، ولو مش مكفية ← فاتورة بالباقي من سعر الباقة
        $room = [];
        $cap  = 0.0;
        foreach ($this->package_invoices($p['id']) as $i) {
            if ((int) $i['status'] === 5) {
                continue; // ملغية
            }
            $r = (float) $i['total'] - (float) $i['paid'];
            if ($r > 0.004) {
                $room[] = [(int) $i['invoice_id'], $r];
                $cap   += $r;
            }
        }
        if ($cap + 0.004 < $amount) {
            $billed = array_sum(array_map(function ($i) { return (int) $i['status'] === 5 ? 0 : (float) $i['total']; }, $this->package_invoices($p['id'])));
            $need   = round(max($amount - $cap, (float) $p['price'] - $billed), 2);
            $room[] = [$this->create_package_invoice($p, $need, $billed > 0 ? 'باقي الباقة' : 'فاتورة الباقة'), $need];
        }

        $pay  = $this->perfex_model('payments_model');
        $left = $amount;
        $ids  = [];
        foreach ($room as [$inv, $r]) {
            if ($left <= 0.004) {
                break;
            }
            $a    = round(min($left, $r), 2);
            $left = round($left - $a, 2);
            if ($pay) {
                $ids[] = $pay->add([
                    'invoiceid' => $inv, 'amount' => $a, 'paymentmode' => $mode, 'date' => function_exists('_d') ? _d($date) : $date,
                    'note' => (string) $note, 'transactionid' => '', 'do_not_send_email_template' => 'on',
                ]);
            } else { // من غير Perfex (الاختبارات)
                $this->db->insert($this->tbl('invoicepaymentrecords'), [
                    'invoiceid' => $inv, 'amount' => $a, 'paymentmode' => (string) $mode, 'date' => $date, 'daterecorded' => $this->now(), 'note' => (string) $note,
                ]);
                $ids[] = (int) $this->db->insert_id();
                $paid  = (float) ($this->row('SELECT SUM(amount) s FROM ' . $this->tbl('invoicepaymentrecords') . ' WHERE invoiceid = ?', [$inv])['s'] ?? 0);
                $total = (float) $this->row('SELECT total FROM ' . $this->tbl('invoices') . ' WHERE id = ?', [$inv])['total'];
                $this->db->update($this->tbl('invoices'), ['status' => $paid + 0.004 >= $total ? 2 : 3], ['id' => $inv]);
            }
        }
        $st = $this->sync_package_status($p['id']);
        $this->check_payment_due($p['id']);
        return ['status' => $st, 'payments' => $ids, 'left' => round((float) $p['price'] - $this->package_paid_amount($this->package($p['id'])), 2)];
    }

    /**
     * جدّد الاشتراك: باقة جديدة بنفس الخدمات والسعر والمدة، بتبدأ اليوم اللي بعد ما القديمة تخلص (أو النهارده لو خلصت).
     * بتفضل "مستنية الدفع" لحد ما تتسجل دفعة توصل نسبة التفعيل — والقديمة بتكمل لحد آخر يوم فيها.
     */
    public function renew_package($package_id, array $over = [])
    {
        $p = $this->package($package_id);
        if ($p['status'] === 'cancelled') {
            throw new Exception('الباقة دي ملغية — اعمل باقة جديدة بدل التجديد');
        }
        $items = [];
        foreach ($this->rows('SELECT type, qty FROM ' . $this->tbl('dse_package_items') . ' WHERE package_id = ?', [(int) $p['id']]) as $i) {
            $items[$i['type']] = (float) $i['qty'];
        }
        foreach ((array) ($over['items'] ?? []) as $t => $q) {
            if ($q !== '' && $q !== null) {
                $items[$t] = (float) $q;
            }
        }
        $days  = (int) ($over['validity_days'] ?? 0) ?: (int) round((strtotime($p['end_date']) - strtotime($p['start_date'])) / 86400) + 1;
        $start = $over['start_date'] ?? '';
        if ($start === '') {
            $start = max($this->today(), date('Y-m-d', strtotime($p['end_date'] . ' +1 day')));
        }
        $price = isset($over['price']) && $over['price'] !== '' ? (float) $over['price'] : (float) $p['price'];
        $name  = trim((string) ($over['name'] ?? '')) ?: $p['name'];
        $id    = $this->create_package($p['clientid'], $name, $price, $start, $days, $items, null, 'تجديد لباقة ' . $p['name'] . ' (' . dse_day($p['start_date']) . ' ← ' . dse_day($p['end_date']) . ')');
        $this->db->insert($this->tbl('dse_package_events'), [
            'package_id' => $id, 'event' => 'renew', 'old_items' => json_encode($items), 'new_items' => json_encode($items),
            'old_price' => $p['price'], 'new_price' => $price, 'old_end_date' => $p['end_date'], 'new_end_date' => $this->package($id)['end_date'],
            'note' => 'تجديد من باقة #' . $p['id'], 'staff_id' => $this->staff_id(), 'created_at' => $this->now(),
        ]);
        return $id;
    }

    // =================================================================
    // العملاء القدام + "مستنيين تجديد"
    // =================================================================
    /** آخر باقة لكل عميل (غير الملغية) — المفتاح clientid */
    private function last_packages()
    {
        $out = [];
        foreach ($this->rows('SELECT * FROM ' . $this->tbl('dse_packages') . " WHERE status <> 'cancelled' ORDER BY end_date, id") as $p) {
            $out[(int) $p['clientid']] = $p; // الأحدث بيكتب على اللي قبله
        }
        return $out;
    }

    /**
     * العملاء اللي محتاجين يجددوا: آخر باقة خلصت أو هتخلص خلال dse_renewal_days_before يوم، ومفيش باقة جديدة بعدها.
     * $status: '' كله · open (مش "مش هيجدد") · contacted · later · lost
     */
    public function renewals($status = 'open')
    {
        $this->sync_all_packages();
        $soon = date('Y-m-d', strtotime($this->today() . ' +' . max(0, (int) $this->opt('dse_renewal_days_before', 7)) . ' days'));
        $out  = [];
        foreach ($this->last_packages() as $cid => $p) {
            if ($p['end_date'] > $soon || $p['status'] === 'pending') {
                continue; // لسه بدري، أو فيه باقة جديدة (تجديد) مستنية الدفع
            }
            $c  = $this->client($cid);
            $x  = $this->row('SELECT renewal_status, renewal_note, renewal_updated_at FROM ' . $this->tbl('dse_clients') . ' WHERE clientid = ?', [$cid]) ?: [];
            $st = (string) ($x['renewal_status'] ?? '');
            if (($status === 'open' && $st === 'lost') || ($status !== 'open' && $status !== '' && $st !== $status)) {
                continue;
            }
            $msg = $this->row('SELECT created_at, status FROM ' . $this->tbl('dse_wa_outbox') . " WHERE clientid = ? AND msg_key = 'renewal_request' ORDER BY id DESC LIMIT 1", [$cid]);
            $out[] = [
                'clientid' => $cid, 'company' => $c['company'], 'code' => $c['code'], 'phone' => $c['wa_phone'], 'group' => (bool) $c['wa_group_jid'],
                'package' => $p, 'ended' => $p['end_date'] < $this->today(), 'days' => (int) round((strtotime($this->today()) - strtotime($p['end_date'])) / 86400),
                'historic' => (int) $p['historic'] === 1, 'renewal_status' => $st, 'renewal_note' => $x['renewal_note'] ?? null,
                'renewal_updated_at' => $x['renewal_updated_at'] ?? null, 'last_msg' => $msg,
            ];
        }
        // اللي هتخلص قريب الأول، وبعدها اللي خلصت من أقرب لأبعد
        usort($out, function ($a, $b) { return [$a['ended'], $a['days']] <=> [$b['ended'], $b['days']]; });
        return $out;
    }

    public function set_renewal_status($clientid, $status, $note = null)
    {
        if (!in_array($status, ['', 'contacted', 'later', 'lost'], true)) {
            throw new Exception('حالة غلط');
        }
        $this->client($clientid);
        $row = ['renewal_status' => $status ?: null, 'renewal_note' => trim((string) $note) !== '' ? mb_substr(trim($note), 0, 255) : null, 'renewal_updated_at' => $this->now()];
        if ($this->row('SELECT clientid FROM ' . $this->tbl('dse_clients') . ' WHERE clientid = ?', [(int) $clientid])) {
            $this->db->update($this->tbl('dse_clients'), $row, ['clientid' => (int) $clientid]);
        } else {
            $this->db->insert($this->tbl('dse_clients'), $row + ['clientid' => (int) $clientid, 'updated_at' => $this->now()]);
        }
    }

    /** الـ cron: تنبيه مرة واحدة لكل باقة خلصت ومجددتش (للسيلز) */
    public function check_renewals()
    {
        $new = [];
        foreach ($this->renewals('open') as $r) {
            if (!$r['ended'] || $r['historic'] || $r['days'] > 30) {
                continue; // القديمة اللي اتضافت بإيدك بتتابعها من صفحة التجديد من غير تنبيهات
            }
            $id = $this->add_alert($r['clientid'], 'renewal_due', 'renewal:' . $r['package']['id'],
                $r['company'] . ' — باقة "' . $r['package']['name'] . '" خلصت ' . dse_day($r['package']['end_date']) . ' ومجددش. كلمه وابعتله "طلب تجديد الاشتراك" من صفحة التجديد.');
            if ($id) {
                $new[] = $id;
            }
        }
        return $new;
    }

    public function sync_all_packages()
    {
        foreach ($this->rows('SELECT id FROM ' . $this->tbl('dse_packages') . " WHERE status IN ('pending','active') OR (status = 'expired' AND end_date >= ?)", [date('Y-m-d', strtotime($this->today() . ' -' . $this->grace_days() . ' days'))]) as $p) {
            $this->sync_package_status($p['id']);
        }
    }

    // =================================================================
    // الملخص للصفحة الرئيسية
    // =================================================================
    public function client_summary($clientid)
    {
        $pkg = $this->active_package($clientid);
        return [
            'client'   => $this->client($clientid),
            'package'  => $pkg,
            'balance'  => $pkg ? $this->balance($pkg['id']) : [],
            'paid'     => $pkg ? $this->package_paid_ratio($pkg) : 0,
            'delivered'=> $pkg ? $this->delivered($pkg['id']) : [],
            'packages' => $this->client_packages($clientid),
            'next'     => $this->row('SELECT s.*, st.name AS studio FROM ' . $this->tbl('dse_sessions') . ' s JOIN ' . $this->tbl('dse_studios') . " st ON st.id = s.studio_id WHERE s.clientid = ? AND s.status = 'booked' AND s.start_at >= ? ORDER BY s.start_at LIMIT 1", [(int) $clientid, $this->now()]),
        ];
    }

    public function clients_with_packages()
    {
        $this->sync_all_packages();
        $out = [];
        foreach ($this->rows('SELECT p.*, ' . $this->name_sql('c') . ' AS company FROM ' . $this->tbl('dse_packages') . ' p JOIN ' . $this->tbl('clients') . " c ON c.userid = p.clientid WHERE p.status IN ('pending','active') ORDER BY company") as $p) {
            $p['balance_text'] = $this->balance_text($p['id'], false);
            $p['busy']         = $this->package_has_activity($p['id']);
            $p['activity']     = $p['busy'] ? $this->package_activity_text($p['id']) : '';
            $out[]             = $p;
        }
        return $out;
    }
}
