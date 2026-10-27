<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/../helpers/dv_import.php';

/**
 * كل قواعد الموديول: الدكاترة، العماير، الزيارات وسلم المراحل، الـ XP، الحافز، التقارير، والـ API.
 * الكنترولرز بتنادي هنا بس. الأخطاء Exception برسالة عربي جاهزة تتعرض زي ما هي.
 * (الـ PRD قسّمها 4 models — اتجمعت في واحد لأن كلهم بيحتاجوا بعض في نفس العملية)
 */
class Dv_model extends App_Model
{
    /** @var int|null وقت ثابت للاختبارات */
    public $fixed_time = null;

    /** @var array إشعارات للمندوب بعد آخر عملية (Toast / Level up / Milestones) */
    public $feedback = [];

    // ================================================================= أدوات
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

    private function tbl($n)
    {
        return db_prefix() . $n;
    }

    private function rows($sql, $b = [])
    {
        return $this->db->query($sql, $b)->result_array();
    }

    private function row($sql, $b = [])
    {
        $r = $this->db->query($sql, $b)->row_array();
        return $r ?: null;
    }

    private function me()
    {
        return function_exists('get_staff_user_id') ? (int) get_staff_user_id() : 0;
    }

    public function setting($key, $staff_id = 0, $default = null)
    {
        return dv_setting($key, $staff_id, $default);
    }

    private function json_setting($key)
    {
        return json_decode((string) $this->setting($key), true) ?: [];
    }

    /** المندوب يشوف دكاترته بس لو صلاحيته view_own */
    public function can_see_all()
    {
        if (!function_exists('staff_can')) {
            return true;
        }
        return (function_exists('is_admin') && is_admin()) || staff_can('view', DOCTOR_VISITS_MODULE_NAME);
    }

    private function scope_sql($alias = 'm', $staff_id = null)
    {
        if ($staff_id) {
            return ' AND ' . $alias . '.assigned_staff_id = ' . (int) $staff_id;
        }
        return $this->can_see_all() ? '' : ' AND ' . $alias . '.assigned_staff_id = ' . $this->me();
    }

    public function staff_name($id)
    {
        $s = $this->row('SELECT firstname, lastname FROM ' . $this->tbl('staff') . ' WHERE staffid = ?', [(int) $id]);
        return $s ? trim($s['firstname'] . ' ' . $s['lastname']) : '';
    }

    // ================================================================= العماير (F1)
    public function save_building(array $d, $id = null)
    {
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') {
            throw new Exception('اكتب اسم العمارة');
        }
        $row = [
            'name' => $name, 'cluster' => trim((string) ($d['cluster'] ?? '')) ?: null, 'address' => $d['address'] ?? null,
            'maps_url' => trim((string) ($d['maps_url'] ?? '')) ?: null, 'clinics_estimate' => max(0, (int) ($d['clinics_estimate'] ?? 0)),
            'notes' => $d['notes'] ?? null,
        ];
        if ($id) {
            $this->db->update($this->tbl('dv_buildings'), $row, ['id' => (int) $id]);
            return (int) $id;
        }
        $this->db->insert($this->tbl('dv_buildings'), $row + ['created_by' => $this->me(), 'created_at' => $this->now()]);
        return (int) $this->db->insert_id();
    }

    public function building($id)
    {
        $b = $this->row('SELECT * FROM ' . $this->tbl('dv_buildings') . ' WHERE id = ?', [(int) $id]);
        if (!$b) {
            throw new Exception('العمارة مش موجودة');
        }
        return $b;
    }

    /** العماير ونسبة التغطية = اللي اتزاروا ÷ max(المسجلين، العدد التقريبي) */
    public function buildings()
    {
        $out = [];
        foreach ($this->rows('SELECT b.*, (SELECT COUNT(*) FROM ' . $this->tbl('dv_doctor_meta') . ' m WHERE m.building_id = b.id) AS doctors,
                (SELECT COUNT(*) FROM ' . $this->tbl('dv_doctor_meta') . ' m WHERE m.building_id = b.id AND m.stage >= 1) AS visited
              FROM ' . $this->tbl('dv_buildings') . ' b ORDER BY b.cluster, b.name') as $b) {
            $base           = max((int) $b['doctors'], (int) $b['clinics_estimate'], 1);
            $b['coverage']  = (int) round(100 * (int) $b['visited'] / $base);
            $out[]          = $b;
        }
        return $out;
    }

    // ================================================================= الدكاترة (F2)
    private function new_qr_code()
    {
        $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // من غير O/0/I/1
        for ($i = 0; $i < 50; $i++) {
            $code = 'DV-';
            for ($j = 0; $j < 4; $j++) {
                $code .= $abc[random_int(0, strlen($abc) - 1)];
            }
            if (!$this->row('SELECT lead_id FROM ' . $this->tbl('dv_doctor_meta') . ' WHERE qr_code = ?', [$code])) {
                return $code;
            }
        }
        throw new Exception('مقدرتش أعمل كود QR — جرّب تاني');
    }

    /** Quick Add: الاسم والتخصص والعمارة بس إجباري — بيعمل Lead في Perfex + صف الـ Meta + كود QR */
    public function quick_add(array $d)
    {
        $name = trim((string) ($d['name'] ?? ''));
        $spec = trim((string) ($d['specialty'] ?? ''));
        if ($name === '' || $spec === '' || empty($d['building_id'])) {
            throw new Exception('الاسم والتخصص والعمارة إجباري');
        }
        $this->building($d['building_id']);
        $staff = !empty($d['assigned_staff_id']) && $this->can_see_all() ? (int) $d['assigned_staff_id'] : $this->me();
        $phone = dv_phone($d['phone'] ?? '');
        $this->db->insert($this->tbl('leads'), [
            'name' => !empty($d['raw_name']) || stripos($name, 'د') === 0 || stripos($name, 'dr') === 0 ? $name : 'د/ ' . $name,
            'title' => $spec, 'description' => '', 'phonenumber' => $phone ?: '',
            'status' => (int) $this->setting('stage_status_0'), 'source' => (int) $this->setting('lead_source'),
            'assigned' => $staff, 'dateadded' => $this->now(), 'addedfrom' => $this->me(), 'lastcontact' => null,
            'hash' => function_exists('app_generate_hash') ? app_generate_hash() : md5(uniqid('', true)),
            'last_status_change' => $this->now(),
        ]);
        $lead = (int) $this->db->insert_id();
        $this->db->insert($this->tbl('dv_doctor_meta'), [
            'lead_id' => $lead, 'building_id' => (int) $d['building_id'], 'floor' => trim((string) ($d['floor'] ?? '')) ?: null,
            'specialty' => $spec, 'clinic_whatsapp' => dv_phone($d['clinic_whatsapp'] ?? '') ?: null,
            'qr_code' => $this->new_qr_code(), 'stage' => 0, 'assigned_staff_id' => $staff, 'grade' => dv_grade(0),
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        return $lead;
    }

    public function doctor($lead_id, $check_scope = true)
    {
        $d = $this->row('SELECT m.*, l.name, l.phonenumber, l.status AS lead_status, l.assigned, b.name AS building, b.cluster
              FROM ' . $this->tbl('dv_doctor_meta') . ' m JOIN ' . $this->tbl('leads') . ' l ON l.id = m.lead_id
              LEFT JOIN ' . $this->tbl('dv_buildings') . ' b ON b.id = m.building_id WHERE m.lead_id = ?', [(int) $lead_id]);
        if (!$d) {
            throw new Exception('الدكتور مش موجود');
        }
        if ($check_scope && !$this->can_see_all() && (int) $d['assigned_staff_id'] !== $this->me()) {
            throw new Exception('الدكتور ده مش من دكاترتك');
        }
        return $this->decorate($d);
    }

    /** معلومات العرض: التقدم، الخطوة الجاية، فاضل كام، آخر Touch */
    private function decorate(array $d)
    {
        $lost              = (string) $d['lost_reason'] !== '';
        $d['lost']         = $lost;
        $d['progress']     = $lost ? 0 : dv_progress($d['stage']);
        $d['color']        = dv_stage_color($d['stage'], $lost);
        $touches           = (int) ($this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dv_visits') . ' WHERE lead_id = ?', [$d['lead_id']])['n'] ?? 0);
        $d['touches']      = $touches;
        $d['touches_left'] = max(0, $this->median_touches_to_win() - $touches);
        $d['steps_left']   = dv_steps_left($d['stage']);
        $ns                = dv_next_step($d['stage']);
        $d['next_step']    = _l($ns[0]);
        $d['next_step_xp'] = $ns[1] ? $this->xp_for_stage_outcome($ns[1]) : 0;
        $last              = $this->row('SELECT MAX(visited_at) AS d FROM ' . $this->tbl('dv_visits') . ' WHERE lead_id = ?', [$d['lead_id']]);
        $d['last_touch']   = $last['d'] ?? null;
        $d['days_since_touch'] = $d['last_touch'] ? max(0, (int) floor(($this->t() - strtotime($d['last_touch'])) / 86400)) : null;
        $d['overdue']      = $d['next_action_at'] && strtotime($d['next_action_at']) < strtotime($this->today() . ' 23:59:59') && !$lost && (int) $d['stage'] < 9;
        return $d;
    }

    public function update_doctor($lead_id, array $d)
    {
        $doc   = $this->doctor($lead_id);
        $allow = ['building_id', 'floor', 'specialty', 'clinic_hours', 'gatekeeper_name', 'best_time', 'digital_score', 'facebook_url',
            'instagram_url', 'gmaps_url', 'clinic_whatsapp', 'audit_link', 'key_insight', 'demo_link', 'offer_link', 'meeting_at', 'deal_value',
            'priority_rank', 'clinic_days', 'other_branches', 'phones', 'social_summary', 'followers', 'social_note', 'verification', 'notes'];
        $row = [];
        foreach ($allow as $k) {
            if (array_key_exists($k, $d)) {
                $v = is_string($d[$k]) ? trim($d[$k]) : $d[$k];
                $row[$k] = $v === '' ? null : $v;
            }
        }
        if (isset($row['digital_score'])) {
            $row['digital_score'] = max(0, min(12, (int) $row['digital_score']));
            $row['grade']         = dv_grade($row['digital_score']);
        }
        if (array_key_exists('clinic_whatsapp', $row) && $row['clinic_whatsapp'] !== null) {
            $row['clinic_whatsapp'] = dv_phone($row['clinic_whatsapp']);
            if (!$row['clinic_whatsapp']) {
                throw new Exception('رقم واتساب العيادة لازم يبقى مصري (01xxxxxxxxx)');
            }
        }
        if (isset($row['priority_rank'])) {
            $row['priority_rank'] = max(1, min(9, (int) $row['priority_rank']));
        }
        if (isset($row['meeting_at'])) {
            $row['meeting_at'] = date('Y-m-d H:i:s', strtotime($row['meeting_at']));
        }
        if (isset($row['gatekeeper_name'])) {
            $row['gatekeeper_name'] = mb_substr(preg_split('/\s+/', $row['gatekeeper_name'])[0], 0, 50); // الاسم الأول بس (PRD §9)
        }
        if ($row) {
            $row['updated_at'] = $this->now();
            $this->db->update($this->tbl('dv_doctor_meta'), $row, ['lead_id' => $doc['lead_id']]);
        }
        if (array_key_exists('name', $d) && trim($d['name']) !== '') {
            $this->db->update($this->tbl('leads'), ['name' => trim($d['name'])], ['id' => $doc['lead_id']]);
        }
        if (array_key_exists('phone', $d)) {
            $this->db->update($this->tbl('leads'), ['phonenumber' => dv_phone($d['phone']) ?: ''], ['id' => $doc['lead_id']]);
        }
        if (!empty($d['assigned_staff_id']) && $this->can_see_all()) {
            $this->db->update($this->tbl('dv_doctor_meta'), ['assigned_staff_id' => (int) $d['assigned_staff_id']], ['lead_id' => $doc['lead_id']]);
            $this->db->update($this->tbl('leads'), ['assigned' => (int) $d['assigned_staff_id']], ['id' => $doc['lead_id']]);
        }
    }

    public function set_consent($lead_id, $on, $source = 'in_person')
    {
        $this->db->update($this->tbl('dv_doctor_meta'), [
            'wa_consent' => $on ? 1 : 0, 'wa_consent_at' => $on ? $this->now() : null, 'wa_consent_source' => $on ? $source : null,
        ], ['lead_id' => (int) $lead_id]);
    }

    /** الدكاترة (للقوايم): فلتر بالعمارة / المرحلة / البحث */
    public function doctors(array $f = [])
    {
        $sql = 'SELECT m.*, l.name, l.phonenumber, b.name AS building FROM ' . $this->tbl('dv_doctor_meta') . ' m
                  JOIN ' . $this->tbl('leads') . ' l ON l.id = m.lead_id LEFT JOIN ' . $this->tbl('dv_buildings') . ' b ON b.id = m.building_id WHERE 1=1'
            . $this->scope_sql('m', $f['staff_id'] ?? null);
        $b = [];
        if (!empty($f['building_id'])) {
            $sql .= ' AND m.building_id = ?';
            $b[]  = (int) $f['building_id'];
        }
        if (!empty($f['q'])) {
            $sql .= ' AND (l.name LIKE ? OR m.specialty LIKE ? OR b.name LIKE ?)';
            $q    = '%' . $f['q'] . '%';
            array_push($b, $q, $q, $q);
        }
        if (isset($f['open'])) {
            $sql .= " AND (m.lost_reason IS NULL OR m.lost_reason = '') AND m.stage < 9";
        }
        $sql .= ' ORDER BY ' . ($f['order'] ?? 'b.name, m.floor, l.name');
        return array_map([$this, 'decorate'], $this->rows($sql, $b));
    }

    // ================================================================= استيراد شيت الدكاترة (Excel / CSV)
    /** الخانات اللي بتتنقل من الشيت لصف الدكتور (لو مكتوبة في الشيت) */
    private function import_meta_fields()
    {
        return ['floor', 'specialty', 'clinic_days', 'clinic_hours', 'other_branches', 'phones', 'social_summary', 'facebook_url', 'followers',
            'social_note', 'instagram_url', 'gmaps_url', 'clinic_whatsapp', 'verification', 'priority_rank', 'notes', 'gatekeeper_name', 'best_time'];
    }

    /** حفظ المعاينة (قبل التأكيد) في dv_events علشان تفضل بين الصفحتين */
    public function import_save_preview(array $parsed, $filename)
    {
        $this->db->insert($this->tbl('dv_events'), [
            'lead_id' => null, 'type' => 'import_preview', 'created_at' => $this->now(),
            'payload' => json_encode(['file' => $filename, 'by' => $this->me()] + $parsed, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $this->db->insert_id();
    }

    public function import_load($id)
    {
        $e = $this->row('SELECT * FROM ' . $this->tbl('dv_events') . " WHERE id = ? AND type IN ('import_preview', 'import_done')", [(int) $id]);
        $p = $e ? json_decode($e['payload'], true) : null;
        if (!$p || ((int) $p['by'] !== $this->me() && !$this->can_see_all())) {
            throw new Exception('الملف ده مش موجود — ارفعه تاني');
        }
        return array_merge($p, ['id' => (int) $e['id'], 'done' => $e['type'] === 'import_done', 'created_at' => $e['created_at']]);
    }

    /** الدكاترة الموجودين، متفهرسين بالاسم+العمارة وبالاسم وبالموبايل */
    private function import_index()
    {
        $ix = ['nb' => [], 'n' => [], 'p' => [], 'b' => []];
        foreach ($this->rows('SELECT id, name FROM ' . $this->tbl('dv_buildings')) as $b) {
            $ix['b'][dv_norm($b['name'])] = (int) $b['id'];
        }
        foreach ($this->rows('SELECT m.*, l.name, l.phonenumber, b.name AS building FROM ' . $this->tbl('dv_doctor_meta') . ' m
                JOIN ' . $this->tbl('leads') . ' l ON l.id = m.lead_id LEFT JOIN ' . $this->tbl('dv_buildings') . ' b ON b.id = m.building_id') as $d) {
            $n = dv_norm_name($d['name']);
            $ix['nb'][$n . '|' . dv_norm($d['building'])] = $d;
            $ix['n'][$n][] = $d;
            foreach (array_merge([$d['phonenumber']], dv_import_phones($d['phones'])['mobile']) as $p) {
                if ($p = dv_phone($p)) {
                    $ix['p'][$p][] = $d;
                }
            }
        }
        return $ix;
    }

    /**
     * المعاينة: كل صف هيحصل فيه إيه — new / update / same / skip — والخانات اللي هتتغير والزيارة اللي هتتسجل.
     * مبيكتبش حاجة في الداتابيز.
     */
    public function import_plan(array $parsed)
    {
        $ix    = $this->import_index();
        $seen  = [];
        $plan  = [];
        $count = ['new' => 0, 'update' => 0, 'same' => 0, 'skip' => 0, 'visits' => 0];
        $new_b = [];
        $mine  = $this->can_see_all() ? null : $this->me();
        foreach ($parsed['rows'] as $i => $r) {
            $n   = dv_norm_name($r['name']);
            $bn  = dv_norm($r['building']);
            $key = $n . '|' . $bn;
            $p   = ['i' => $i, 'row' => $r, 'action' => 'new', 'lead_id' => null, 'changes' => [], 'visit' => null, 'why' => ''];
            if (isset($seen[$key])) {
                $p['action'] = 'skip';
                $p['why']    = 'متكرر في الشيت (سطر ' . $seen[$key] . ')';
            } else {
                $seen[$key] = $r['line'];
                $doc = $ix['nb'][$key] ?? null;
                if (!$doc && count($ix['n'][$n] ?? []) === 1) {
                    $doc = $ix['n'][$n][0]; // نفس الاسم في عمارة تانية ← نفس الدكتور اتنقل أو اتكتب غلط
                }
                if (!$doc && $r['phone'] && count($ix['p'][$r['phone']] ?? []) === 1) {
                    $doc = $ix['p'][$r['phone']][0];
                }
                if ($doc && $mine && (int) $doc['assigned_staff_id'] !== $mine) {
                    $p['action'] = 'skip';
                    $p['why']    = 'متسجل عند مندوب تاني (' . $this->staff_name($doc['assigned_staff_id']) . ')';
                } elseif ($doc) {
                    $p['lead_id'] = (int) $doc['lead_id'];
                    foreach ($this->import_meta_fields() as $f) {
                        $v = $r[$f] ?? '';
                        if ($f === 'phones' && (string) $doc['phones'] === '' && dv_import_phones($v)['mobile'] === array_filter([(string) $doc['phonenumber']])) {
                            continue; // نفس الموبايل اللي متسجل — مش تغيير
                        }
                        if ($v !== '' && $v !== null && (string) $v !== (string) $doc[$f]) {
                            $p['changes'][$f] = [(string) $doc[$f], (string) $v];
                        }
                    }
                    if ($r['phone'] !== '' && $r['phone'] !== (string) $doc['phonenumber']) {
                        $p['changes']['phone'] = [(string) $doc['phonenumber'], $r['phone']];
                    }
                    if ($bn !== '' && $bn !== dv_norm($doc['building'])) {
                        $p['changes']['building'] = [(string) $doc['building'], $r['building']];
                    }
                    if (!$r['outcome_code'] && $r['next_action_at'] && $r['next_action_at'] !== (string) $doc['next_action_at']) {
                        $p['changes']['next_action_at'] = [(string) $doc['next_action_at'], $r['next_action_at']];
                    }
                    $p['action'] = $p['changes'] ? 'update' : 'same';
                    $stage = (int) $doc['stage'];
                } else {
                    $stage = 0;
                }
                if ($p['action'] !== 'skip' && $r['outcome_code']) {
                    $target = dv_outcomes()[$r['outcome_code']];
                    if ($target !== null && $target > $stage && ($stage < 9) && empty($doc['lost_reason'])) {
                        $p['visit'] = $r['outcome_code'];
                        if ($p['action'] === 'same') {
                            $p['action'] = 'update';
                        }
                    }
                }
                if ($p['action'] !== 'skip' && $bn !== '' && !isset($ix['b'][$bn])) {
                    $new_b[$bn] = $r['building'];
                }
            }
            $count[$p['action']]++;
            $count['visits'] += $p['visit'] ? 1 : 0;
            $plan[] = $p;
        }
        if ($count['new'] && array_filter($plan, function ($p) { return $p['action'] === 'new' && $p['row']['building'] === ''; })) {
            $new_b[dv_norm('غير محدد')] = $new_b[dv_norm('غير محدد')] ?? (isset($ix['b'][dv_norm('غير محدد')]) ? null : 'غير محدد');
        }
        return ['rows' => $plan, 'count' => $count, 'new_buildings' => array_values(array_filter($new_b))];
    }

    /** التنفيذ: بيعمل العماير الناقصة، والدكاترة الجداد (Lead + Meta + QR)، ويحدّث الموجودين، ويسجّل الزيارات اللي في الشيت (من غير XP) */
    public function import_run($id, $staff_id = null)
    {
        $file = $this->import_load($id);
        if ($file['done']) {
            throw new Exception('الملف ده اتستورد قبل كده');
        }
        $staff  = $staff_id && $this->can_see_all() ? (int) $staff_id : $this->me();
        $plan   = $this->import_plan($file);
        $ix     = $this->import_index();
        $result = ['new' => 0, 'update' => 0, 'same' => 0, 'skip' => 0, 'visits' => 0, 'buildings' => 0, 'errors' => []];
        foreach ($plan['rows'] as $p) {
            $r = $p['row'];
            if ($p['action'] === 'skip' || $p['action'] === 'same') {
                $result[$p['action']]++;
                continue;
            }
            try {
                $bname = $r['building'] !== '' ? $r['building'] : 'غير محدد';
                $bid   = $ix['b'][dv_norm($bname)] ?? null;
                if (!$bid) {
                    $bid = $this->save_building(['name' => $bname]);
                    $ix['b'][dv_norm($bname)] = $bid;
                    $result['buildings']++;
                }
                $meta = [];
                foreach ($this->import_meta_fields() as $f) {
                    if (($r[$f] ?? '') !== '' && $r[$f] !== null) {
                        $meta[$f] = $r[$f];
                    }
                }
                if ($p['action'] === 'new') {
                    $lead = $this->quick_add([
                        'name' => $r['name'], 'specialty' => $r['specialty'], 'building_id' => $bid, 'phone' => $r['phone'],
                        'assigned_staff_id' => $staff, 'raw_name' => 1,
                    ]);
                    $result['new']++;
                } else {
                    $lead = $p['lead_id'];
                    if (isset($p['changes']['building'])) {
                        $meta['building_id'] = $bid;
                    }
                    if (isset($p['changes']['phone'])) {
                        $this->db->update($this->tbl('leads'), ['phonenumber' => $r['phone']], ['id' => $lead]);
                    }
                    $result['update']++;
                }
                if (isset($meta['priority_rank'])) {
                    $meta['priority_rank'] = (int) $meta['priority_rank'];
                }
                if (!$p['visit'] && $r['next_action_at']) {
                    $meta['next_action']    = $r['next_action'] !== '' ? $r['next_action'] : 'متابعة';
                    $meta['next_action_at'] = $r['next_action_at'];
                }
                $meta['updated_at'] = $this->now();
                $this->db->update($this->tbl('dv_doctor_meta'), $meta, ['lead_id' => $lead]);
                $lead_row = [];
                if ($r['facebook_url'] !== '') {
                    $lead_row['website'] = $r['facebook_url'];
                }
                if ($r['notes'] !== '' && $p['action'] === 'new') {
                    $lead_row['description'] = $r['notes'];
                }
                if ($lead_row && $this->db->field_exists('website', $this->tbl('leads'))) {
                    $this->db->update($this->tbl('leads'), $lead_row, ['id' => $lead]);
                }
                if (!$p['visit'] && $r['next_action_at'] && strtotime($r['next_action_at']) > $this->t()) {
                    $this->db->insert($this->tbl('reminders'), [
                        'description' => $meta['next_action'] . ' — ' . $r['name'], 'date' => $r['next_action_at'], 'isnotified' => 0,
                        'rel_id' => $lead, 'staff' => $staff, 'rel_type' => 'lead', 'notify_by_email' => 0, 'creator' => $this->me(),
                    ]);
                }
                if ($p['visit']) {
                    $doc = $this->doctor($lead, false);
                    $this->log_visit($lead, [
                        'outcome' => $p['visit'], 'channel' => 'system', 'no_xp' => 1, 'note' => 'من شيت الإكسل: ' . ($file['file'] ?? ''),
                        'next_action' => $r['next_action'] !== '' ? $r['next_action'] : 'متابعة',
                        'next_action_at' => $r['next_action_at'] ?: date('Y-m-d 10:00:00', $this->t() + 2 * 86400),
                    ], (int) $doc['assigned_staff_id'] ?: $staff);
                    $this->feedback = [];
                    $result['visits']++;
                }
            } catch (Exception $e) {
                $result['errors'][] = 'سطر ' . $r['line'] . ' (' . $r['name'] . '): ' . $e->getMessage();
            }
        }
        $this->db->update($this->tbl('dv_events'), [
            'type' => 'import_done', 'payload' => json_encode(['result' => $result, 'staff' => $staff] + $file, JSON_UNESCAPED_UNICODE),
        ], ['id' => (int) $id]);
        return $result;
    }

    /** نفس أعمدة الشيت — علشان تنزّل الدكاترة، تعدّل في Excel، وترفعهم تاني */
    public function import_export_rows()
    {
        $out = [['#', 'البرج', 'الدكتور / المكان', 'التخصص', 'الدور', 'السوشيال / الموقع', 'رابط فيسبوك', 'متابعين', 'ملاحظة فيسبوك', 'أيام العيادة',
            'الساعات', 'العيادات / الفروع التانية', 'التليفون', 'حالة التحقق', 'الأولوية (1=أعلى)', 'ملاحظات', 'اسم الممرضة', 'أنسب وقت', 'زرنا؟', 'النتيجة',
            'الخطوة الجاية', 'تاريخ المتابعة']];
        foreach ($this->doctors() as $d) {
            $last = $this->row('SELECT outcome, visited_at FROM ' . $this->tbl('dv_visits') . " WHERE lead_id = ? AND outcome NOT IN ('WA_SENT', 'QR_SCAN') ORDER BY visited_at DESC, id DESC LIMIT 1", [$d['lead_id']]);
            $out[] = [
                $d['lead_id'], $d['building'], $d['name'], $d['specialty'], $d['floor'], $d['social_summary'], $d['facebook_url'], $d['followers'],
                $d['social_note'], $d['clinic_days'], $d['clinic_hours'], $d['other_branches'], $d['phones'] ?: preg_replace('/^20/', '0', (string) $d['phonenumber']), $d['verification'],
                $d['priority_rank'], $d['notes'], $d['gatekeeper_name'], $d['best_time'], $d['stage'] >= 1 ? 'نعم' : '',
                $d['lost'] ? 'Lost' : ($last ? dv_outcome_label($last['outcome']) : ''), $d['next_action'],
                $d['next_action_at'] ? date('Y-m-d H:i', strtotime($d['next_action_at'])) : '',
            ];
        }
        return $out;
    }

    // ================================================================= الزيارات + محرك المراحل (F3)
    /**
     * تسجيل زيارة. $d: outcome, note, next_action, next_action_at, lost_reason, visited_at, channel
     * القواعد: الـ Stage بيطلع لقدام بس · Next Action + تاريخ إجباري (غير WON/LOST) · LOST بسبب + قاعدة الـ 3 زيارات
     */
    public function log_visit($lead_id, array $d, $staff_id = null)
    {
        $this->feedback = [];
        $doc     = $this->doctor($lead_id, $staff_id === null);
        $staff   = $staff_id === null ? $this->me() : (int) $staff_id;
        $outcome = strtoupper(trim((string) ($d['outcome'] ?? '')));
        $map     = dv_outcomes();
        if (!array_key_exists($outcome, $map)) {
            throw new Exception('اختار نتيجة الزيارة');
        }
        if ((int) $doc['stage'] === 9 && !in_array($outcome, ['WA_SENT', 'NOT_NOW'], true)) {
            throw new Exception('الدكتور ده اتقفل (Won) خلاص');
        }
        $visited = !empty($d['visited_at']) ? date('Y-m-d H:i:s', strtotime($d['visited_at'])) : $this->now();
        if (strtotime($visited) > $this->t() + 300) {
            if ($outcome !== 'QR_SCAN') {
                throw new Exception('وقت الزيارة لسه مجاش');
            }
            $visited = $this->now(); // ساعة n8n مش مظبوطة مع السيرفر — منرفضش الـ QR
        }
        $next_at = !empty($d['next_action_at']) ? date('Y-m-d H:i:s', strtotime($d['next_action_at'])) : null;
        $next    = trim((string) ($d['next_action'] ?? ''));
        $needs_next = !in_array($outcome, ['WON', 'LOST', 'QR_SCAN'], true);
        if ($needs_next && (!$next_at || $next === '')) {
            throw new Exception('حدد الخطوة الجاية وتاريخها');
        }

        $reason = null;
        if ($outcome === 'LOST') {
            $reason = (string) ($d['lost_reason'] ?? '');
            if (!in_array($reason, dv_lost_reasons(), true)) {
                throw new Exception('اختار سبب الخسارة');
            }
            if ($reason !== 'wrong_lead') {
                $first = $doc['first_visit_at'] ?: $visited;
                $days  = (strtotime($visited) - strtotime($first)) / 86400;
                if ($doc['touches'] < 3 || $days < 14) {
                    throw new Exception('قاعدة الـ 3 زيارات: مينفعش تقفله Lost قبل 3 زيارات و14 يوم من أول زيارة (زرته ' . $doc['touches'] . ' مرة من ' . (int) floor($days) . ' يوم) — إلا لو "Wrong lead"');
                }
            }
        }

        $before = (int) $doc['stage'];
        $target = $map[$outcome];
        $after  = $target !== null ? max($before, $target) : $before; // لقدام بس
        $channel = in_array($d['channel'] ?? '', ['field', 'whatsapp', 'phone', 'system'], true) ? $d['channel']
            : ($outcome === 'WA_SENT' ? 'whatsapp' : ($outcome === 'QR_SCAN' ? 'system' : 'field'));

        $this->db->insert($this->tbl('dv_visits'), [
            'lead_id' => $doc['lead_id'], 'staff_id' => $staff, 'outcome' => $outcome, 'channel' => $channel,
            'note' => trim((string) ($d['note'] ?? '')) ?: null, 'lost_reason' => $reason,
            'stage_before' => $before, 'stage_after' => $after,
            'next_action' => $next ?: null, 'next_action_at' => $next_at,
            'visited_at' => $visited, 'logged_at' => $this->now(),
        ]);
        $visit_id = (int) $this->db->insert_id();

        // الدكتور
        $meta = ['stage' => $after, 'updated_at' => $this->now()];
        if (!$doc['first_visit_at'] && $outcome !== 'QR_SCAN') {
            $meta['first_visit_at'] = $visited;
        }
        if ($needs_next) {
            $meta['next_action'] = $next;
            $meta['next_action_at'] = $next_at;
        }
        if ($outcome === 'WON') {
            $meta['won_at'] = $visited;
            $meta['next_action'] = null;
            $meta['next_action_at'] = null;
            if (!empty($d['deal_value'])) {
                $meta['deal_value'] = (float) $d['deal_value'];
            }
        }
        if ($outcome === 'LOST') {
            $meta['lost_reason'] = $reason;
            $meta['next_action'] = null;
            $meta['next_action_at'] = null;
        }
        if ($outcome === 'WA' || $outcome === 'QR_SCAN') {
            $meta['wa_consent'] = 1;
            $meta['wa_consent_at'] = $this->now();
            $meta['wa_consent_source'] = $outcome === 'QR_SCAN' ? 'qr_scan' : 'in_person';
        }
        if (!empty($d['consent_now'])) {
            $meta['wa_consent'] = 1;
            $meta['wa_consent_at'] = $this->now();
            $meta['wa_consent_source'] = 'in_person';
        }
        $this->db->update($this->tbl('dv_doctor_meta'), $meta, ['lead_id' => $doc['lead_id']]);

        // Lead Status في Perfex (الـ Kanban يفضل متوافق)
        $status = $outcome === 'LOST' ? (int) $this->setting('lost_status') : (int) $this->setting('stage_status_' . $after);
        $lead   = ['lastcontact' => $visited];
        if ($status && ($after !== $before || $outcome === 'LOST')) {
            $lead['status'] = $status;
            $lead['last_status_change'] = $this->now();
        }
        $this->db->update($this->tbl('leads'), $lead, ['id' => $doc['lead_id']]);

        // Reminder في Perfex على تاريخ الخطوة الجاية
        if ($needs_next && $next_at) {
            $this->db->insert($this->tbl('reminders'), [
                'description' => $next . ' — ' . $doc['name'], 'date' => $next_at, 'isnotified' => 0,
                'rel_id' => $doc['lead_id'], 'staff' => $staff, 'rel_type' => 'lead', 'notify_by_email' => 0, 'creator' => $staff,
            ]);
        }

        $xp = empty($d['no_xp']) ? $this->award_visit_xp($staff, $doc, $outcome, $before, $after, $visit_id, $visited, $d) : 0;
        if ($staff && empty($d['no_xp'])) {
            $this->check_milestones($staff, $doc['lead_id'], $outcome, $after);
            $this->check_quests_and_streak($staff);
        }

        // الـ Toast: قيمة الزيارة ورقمها ناحية الإغلاق الجاي
        $vpc   = $this->visits_per_close($staff);
        $count = $this->visits_since_last_win($staff);
        $this->feedback['toast'] = [
            'value'  => (int) round($this->value_per_visit()),
            'number' => $count,
            'of'     => (int) round($vpc),
            'xp'     => $xp,
            'stage_moved' => $after > $before,
            'late'   => strtotime($this->now()) - strtotime($visited) > 86400,
        ];
        return $visit_id;
    }

    /** زرار الواتساب: "اتبعتت؟ ← أيوه" — بيتسجل WA_SENT والـ Next Action بعد يومين */
    public function wa_sent($lead_id, $template_key = null, $next_days = 2)
    {
        $doc = $this->doctor($lead_id);
        // في ميعادها = الخطوة الجاية ميعادها النهارده أو لسه مجاش (مش فات)
        $on_time = !$doc['next_action_at'] || strtotime($doc['next_action_at']) >= strtotime($this->today() . ' 00:00:00');
        return $this->log_visit($lead_id, [
            'outcome' => 'WA_SENT', 'channel' => 'whatsapp', 'note' => $template_key ? 'قالب: ' . $template_key : null,
            'next_action' => 'متابعة الرد على الواتساب', 'next_action_at' => date('Y-m-d 10:00:00', $this->t() + 86400 * max(1, (int) $next_days)),
            '_on_time' => $on_time,
        ]);
    }

    public function timeline($lead_id)
    {
        return $this->rows('SELECT v.*, CONCAT(s.firstname, \' \', s.lastname) AS staff_name FROM ' . $this->tbl('dv_visits') . ' v
              LEFT JOIN ' . $this->tbl('staff') . ' s ON s.staffid = v.staff_id WHERE v.lead_id = ? ORDER BY v.visited_at DESC, v.id DESC', [(int) $lead_id]);
    }

    // ================================================================= XP و Levels (§5.5)
    private function xp_rules()
    {
        return $this->json_setting('xp_rules');
    }

    public function xp_for_stage_outcome($outcome)
    {
        $r = $this->xp_rules();
        if ($outcome === 'WON') {
            return (int) ($r['WON'] ?? 500);
        }
        return (int) ($r['stage_' . $outcome] ?? 0);
    }

    /** بيرجع XP اتضاف ولا لأ لو المفتاح الفريد موجود (Stage مرة واحدة لكل دكتور) */
    private function add_xp($staff, $lead, $action, $xp, $visit_id = null, $uniq = null)
    {
        if ($xp <= 0) {
            return 0;
        }
        if ($uniq && $this->row('SELECT id FROM ' . $this->tbl('dv_xp_ledger') . ' WHERE uniq_key = ?', [$uniq])) {
            return 0;
        }
        $this->db->insert($this->tbl('dv_xp_ledger'), [
            'staff_id' => (int) $staff, 'lead_id' => $lead, 'action' => $action, 'xp' => (int) round($xp),
            'ref_visit_id' => $visit_id, 'uniq_key' => $uniq, 'created_at' => $this->now(),
        ]);
        return (int) round($xp);
    }

    private function award_visit_xp($staff, array $doc, $outcome, $before, $after, $visit_id, $visited, array $d)
    {
        if (!$staff) {
            return 0; // دكتور مش متعيّن لمندوب (QR من غير صاحب) — مفيش XP
        }
        $level0 = $this->level($staff);
        $r      = $this->xp_rules();
        $lead   = (int) $doc['lead_id'];
        $day    = substr($visited, 0, 10);
        $total  = 0;

        if ($outcome !== 'QR_SCAN') {
            $total += $this->add_xp($staff, $lead, 'visit', $r['visit_logged'] ?? 5, $visit_id, 'visit:' . $lead . ':' . $day);
        }
        if ($outcome === 'WA_SENT') {
            $total += $this->add_xp($staff, $lead, 'WA_SENT', !empty($d['_on_time']) || !isset($d['_on_time']) ? ($r['WA_SENT'] ?? 15) : ($r['WA_SENT_late'] ?? 5), $visit_id);
        } elseif ($outcome === 'WON') {
            $value  = (float) ($d['deal_value'] ?? 0) ?: (float) $this->setting('avg_deal_value', 0, 0);
            $total += $this->add_xp($staff, $lead, 'WON', ($r['WON'] ?? 500) + floor($value / max(1, (int) ($r['WON_per_egp'] ?? 50))), $visit_id, 'stage_WON:' . $lead);
        } elseif (isset($r['stage_' . $outcome]) && $after > $before) {
            // الـ Stage XP بس لو الخطوة دي حرّكت الدكتور فعلًا (مرة واحدة لكل دكتور)
            // Combo: لو 3 دكاترة اتحركوا Stage النهارده ← ×1.5 على Stage XP بتاع اليوم
            $mult  = $this->combo_active($staff, $day) ? (float) ($r['combo_multiplier'] ?? 1.5) : 1.0;
            $got   = $this->add_xp($staff, $lead, 'stage_' . $outcome, $r['stage_' . $outcome] * $mult, $visit_id, 'stage_' . $outcome . ':' . $lead);
            $total += $got;
            if ($got && !$this->combo_active($staff, $day, true) && $this->stage_moves_on($staff, $day) >= (int) ($r['combo_min_leads'] ?? 3)) {
                // أول مرة الـ Combo يتفتح النهارده: الـ 0.5 الزيادة على اللي اتاخد قبل كده النهارده
                $sum = (int) ($this->row("SELECT SUM(xp) s FROM " . $this->tbl('dv_xp_ledger') . " WHERE staff_id = ? AND action LIKE 'stage_%' AND created_at >= ? AND created_at < ?",
                    [$staff, $day . ' 00:00:00', date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00'])['s'] ?? 0);
                $bonus = $this->add_xp($staff, null, 'combo', $sum * (($r['combo_multiplier'] ?? 1.5) - 1), $visit_id, 'combo:' . $staff . ':' . $day);
                $total += $bonus;
                if ($bonus) {
                    $this->feedback['combo'] = $bonus;
                }
            }
        }
        $level1 = $this->level($staff);
        if ($level1['level'] > $level0['level']) {
            $this->feedback['level_up'] = $level1;
            $this->notify_managers('dv_notify_level_up', [$this->staff_name($staff), $level1['name'], $level1['reward']], 'doctor_visits');
        }
        return $total;
    }

    private function stage_moves_on($staff, $day)
    {
        return (int) ($this->row('SELECT COUNT(DISTINCT lead_id) n FROM ' . $this->tbl('dv_visits') . ' WHERE staff_id = ? AND stage_after > stage_before AND visited_at >= ? AND visited_at < ?',
            [$staff, $day . ' 00:00:00', date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00'])['n'] ?? 0);
    }

    private function combo_active($staff, $day, $only_ledger = false)
    {
        return (bool) $this->row('SELECT id FROM ' . $this->tbl('dv_xp_ledger') . ' WHERE uniq_key = ?', ['combo:' . $staff . ':' . $day]);
    }

    public function xp($staff, $month_only = false)
    {
        $sql = 'SELECT SUM(xp) s FROM ' . $this->tbl('dv_xp_ledger') . ' WHERE staff_id = ?';
        $b   = [(int) $staff];
        if ($month_only) {
            $sql .= ' AND created_at >= ?';
            $b[]  = date('Y-m-01 00:00:00', $this->t());
        }
        return (int) ($this->row($sql, $b)['s'] ?? 0);
    }

    /** الـ Level من الـ XP: الشهري (بيتصفّر أول الشهر) أو Lifetime */
    public function level($staff, $month_only = false)
    {
        $xp     = $this->xp($staff, $month_only);
        $th     = $this->json_setting('level_thresholds');
        $names  = $this->json_setting('level_names');
        $reward = $this->json_setting('level_rewards');
        ksort($th);
        $lvl = 1;
        foreach ($th as $l => $min) {
            if ($xp >= $min) {
                $lvl = (int) $l;
            }
        }
        $next = isset($th[$lvl + 1]) ? (int) $th[$lvl + 1] : null;
        return [
            'level' => $lvl, 'name' => $names[$lvl] ?? '', 'reward' => $reward[$lvl] ?? '', 'xp' => $xp,
            'next_at' => $next, 'to_next' => $next !== null ? $next - $xp : 0, 'next_name' => $next !== null ? ($names[$lvl + 1] ?? '') : '',
            'next_reward' => $reward[$lvl + 1] ?? '',
            'pct' => $next !== null ? (int) round(100 * ($xp - (int) $th[$lvl]) / max(1, $next - (int) $th[$lvl])) : 100,
        ];
    }

    // ================================================================= الحافز (§5)
    private function wins($staff = null, $from = null)
    {
        $sql = 'SELECT COUNT(*) n, SUM(COALESCE(deal_value, 0)) v FROM ' . $this->tbl('dv_doctor_meta') . ' WHERE stage = 9 AND won_at IS NOT NULL';
        $b   = [];
        if ($staff) {
            $sql .= ' AND assigned_staff_id = ?';
            $b[]  = (int) $staff;
        }
        if ($from) {
            $sql .= ' AND won_at >= ?';
            $b[]  = $from;
        }
        $r = $this->row($sql, $b);
        return ['n' => (int) $r['n'], 'value' => (float) $r['v']];
    }

    private function total_visits($staff = null)
    {
        return (int) ($this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dv_visits') . " WHERE outcome NOT IN ('QR_SCAN')" . ($staff ? ' AND staff_id = ' . (int) $staff : ''))['n'] ?? 0);
    }

    private function cold_start()
    {
        return $this->wins()['n'] < (int) $this->setting('cold_start_min_wins', 0, 5);
    }

    public function avg_deal_value()
    {
        $w = $this->wins();
        return !$this->cold_start() && $w['value'] > 0 ? $w['value'] / $w['n'] : (float) $this->setting('avg_deal_value', 0, 7500);
    }

    public function visits_per_close($staff = null)
    {
        if ($this->cold_start()) {
            return (float) $this->setting('default_visits_per_close', 0, 25);
        }
        return max(1, $this->total_visits() / max(1, $this->wins()['n']));
    }

    /** قيمة الزيارة الواحدة = متوسط الصفقة × عدد الـ Won ÷ الزيارات (أو الافتراضي في الـ Cold Start) */
    public function value_per_visit()
    {
        return $this->avg_deal_value() / max(1, $this->visits_per_close());
    }

    private function visits_since_last_win($staff)
    {
        $last = $this->row('SELECT MAX(won_at) d FROM ' . $this->tbl('dv_doctor_meta') . ' WHERE stage = 9 AND assigned_staff_id = ?', [(int) $staff]);
        return (int) ($this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dv_visits') . " WHERE staff_id = ? AND outcome <> 'QR_SCAN' AND visited_at > ?",
            [(int) $staff, $last['d'] ?? '1970-01-01'])['n'] ?? 0);
    }

    /** Median Touches to Win من الدكاترة اللي اتقفلوا (افتراضي 7) */
    public function median_touches_to_win()
    {
        static $cache = [];
        $k = $this->fixed_time . ':' . $this->wins()['n'];
        if (isset($cache[$k])) {
            return $cache[$k];
        }
        $counts = array_map('intval', array_column($this->rows('SELECT COUNT(v.id) n FROM ' . $this->tbl('dv_doctor_meta') . ' m JOIN ' . $this->tbl('dv_visits') . ' v ON v.lead_id = m.lead_id
              WHERE m.stage = 9 GROUP BY m.lead_id'), 'n'));
        if (count($counts) < (int) $this->setting('cold_start_min_wins', 0, 5)) {
            return $cache[$k] = (int) $this->setting('default_touches_to_win', 0, 7);
        }
        sort($counts);
        $mid = intdiv(count($counts), 2);
        return $cache[$k] = (int) round(count($counts) % 2 ? $counts[$mid] : ($counts[$mid - 1] + $counts[$mid]) / 2);
    }

    private function is_off_day($ts, $staff)
    {
        $off = array_map('intval', array_filter(explode(',', (string) $this->setting('off_days', $staff, '5')), 'strlen'));
        return in_array((int) date('w', $ts), $off, true);
    }

    public function field_days_left($staff)
    {
        $n   = 0;
        $end = strtotime(date('Y-m-t', $this->t()));
        for ($d = strtotime($this->today()); $d <= $end; $d += 86400) {
            if (!$this->is_off_day($d, $staff)) {
                $n++;
            }
        }
        return max(1, $n);
    }

    /** هدف النهارده (§5.3) */
    public function daily_target($staff)
    {
        $monthly = (float) $this->setting('monthly_target', $staff, 60000);
        $avg     = max(1, $this->avg_deal_value());
        $won     = $this->wins($staff, date('Y-m-01 00:00:00', $this->t()));
        $won_val = $won['value'] > 0 ? $won['value'] : $won['n'] * $avg;
        $deals   = max(0, ($monthly - $won_val) / $avg);
        return (int) ceil($deals * $this->visits_per_close($staff) / $this->field_days_left($staff));
    }

    public function visits_on($staff, $day)
    {
        return (int) ($this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dv_visits') . " WHERE staff_id = ? AND channel = 'field' AND visited_at >= ? AND visited_at < ?",
            [(int) $staff, $day . ' 00:00:00', date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00'])['n'] ?? 0);
    }

    /** Field Streak: أيام شغل ورا بعض فيها min_daily_for_streak زيارة أو أكتر (أيام الإجازة مبتتحسبش) */
    public function streak($staff)
    {
        $min = (int) $this->setting('min_daily_for_streak', $staff, 5);
        $n   = 0;
        $d   = strtotime($this->today());
        if ($this->visits_on($staff, date('Y-m-d', $d)) < $min) {
            $d -= 86400; // النهارده لسه مخلصش — مبيكسرش الـ Streak
        }
        for ($i = 0; $i < 366; $i++, $d -= 86400) {
            if ($this->is_off_day($d, $staff)) {
                continue;
            }
            if ($this->visits_on($staff, date('Y-m-d', $d)) >= $min) {
                $n++;
            } else {
                break;
            }
        }
        return $n;
    }

    /** Pipeline Value = Σ (التقدم% × متوسط الصفقة) للدكاترة المفتوحين */
    public function pipeline_value($staff = null)
    {
        $sum = 0;
        foreach ($this->rows('SELECT stage FROM ' . $this->tbl('dv_doctor_meta') . " WHERE (lost_reason IS NULL OR lost_reason = '') AND stage < 9" . ($staff ? ' AND assigned_staff_id = ' . (int) $staff : '')) as $r) {
            $sum += dv_progress($r['stage']) / 100;
        }
        return $sum * $this->avg_deal_value();
    }

    /** منطقة التقفيل (6–8) والـ Warm (4–5) + إنذار التبريد */
    public function closing_zone($staff = null)
    {
        $f    = ['open' => 1, 'order' => 'm.stage DESC, m.next_action_at'] + ($staff ? ['staff_id' => $staff] : []);
        $all  = $this->doctors($f);
        $hot  = array_values(array_filter($all, function ($d) { return $d['stage'] >= 6 && $d['stage'] <= 8; }));
        $warm = array_values(array_filter($all, function ($d) { return $d['stage'] >= 4 && $d['stage'] <= 5; }));
        foreach ($hot as &$d) {
            $d['cooling'] = $d['days_since_touch'] === null ? '' : ($d['days_since_touch'] >= 7 ? 'red' : ($d['days_since_touch'] >= 3 ? 'orange' : ''));
        }
        unset($d);
        $avg = $this->avg_deal_value();
        return [
            'hot' => $hot, 'warm' => $warm,
            'hot_value' => array_sum(array_map(function ($d) use ($avg) { return $d['deal_value'] ?: $avg; }, $hot)),
        ];
    }

    /** "أزور مين النهارده" (F4) مترتبة بالأولوية */
    public function queue($staff, $building_id = null)
    {
        $docs  = $this->doctors(['open' => 1, 'staff_id' => $this->can_see_all() ? null : $staff] + ($building_id ? ['building_id' => $building_id] : []));
        $now_h = (int) date('G', $this->t());
        $here  = $building_id ?: (int) $this->setting('current_building', $staff, 0);
        foreach ($docs as &$d) {
            $score = 0;
            $why   = [];
            if ($d['next_action_at'] && strtotime($d['next_action_at']) <= strtotime($this->today() . ' 23:59:59')) {
                $score += 1000 + max(0, (int) floor(($this->t() - strtotime($d['next_action_at'])) / 86400));
                $why[] = strtotime($d['next_action_at']) < strtotime($this->today()) ? 'overdue' : 'due_today';
            }
            if ($d['grade'] === 'A' && (int) $d['stage'] === 0) {
                $score += 500;
                $why[] = 'grade_a';
            }
            if ((int) $d['stage'] === 0 && in_array((int) $d['priority_rank'], [1, 2], true)) { // أولوية الشيت
                $score += (int) $d['priority_rank'] === 1 ? 400 : 150;
                $why[] = 'priority_' . (int) $d['priority_rank'];
            }
            if ($d['best_time'] && preg_match('/(\d{1,2})/', $d['best_time'], $m)) {
                $h = (int) $m[1];
                $h = ($h < 12 && preg_match('/(م|pm|مساء)/iu', $d['best_time'])) ? $h + 12 : $h;
                if (abs($h - $now_h) <= 1) {
                    $score += 300;
                    $why[] = 'best_time';
                }
            }
            if ($here && (int) $d['building_id'] === (int) $here) {
                $score += 200;
                $why[] = 'same_building';
            }
            $score += (int) $d['stage'] * 10;
            $d['priority'] = $score;
            $d['why']      = $why;
        }
        unset($d);
        usort($docs, function ($a, $b) { return $b['priority'] <=> $a['priority'] ?: strcmp($a['name'], $b['name']); });
        return $docs;
    }

    public function followups_due($staff = null)
    {
        return array_values(array_filter($this->doctors(['open' => 1, 'order' => 'm.next_action_at'] + ($staff ? ['staff_id' => $staff] : [])), function ($d) {
            return $d['next_action_at'] && strtotime($d['next_action_at']) <= strtotime($this->today() . ' 23:59:59');
        }));
    }

    /** Daily Quests (§5.7): 3 مهام لليوم + هل خلصت */
    public function quests($staff, $day = null)
    {
        $day   = $day ?: $this->today();
        $from  = $day . ' 00:00:00';
        $to    = date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00';
        $tgt   = max(1, $this->daily_target($staff));
        $done1 = $this->visits_on($staff, $day);
        $done2 = (int) ($this->row("SELECT COUNT(*) n FROM " . $this->tbl('dv_xp_ledger') . " WHERE staff_id = ? AND action = 'WA_SENT' AND xp >= ? AND created_at >= ? AND created_at < ?",
            [(int) $staff, (int) ($this->xp_rules()['WA_SENT'] ?? 15), $from, $to])['n'] ?? 0);
        $done3 = (int) ($this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dv_visits') . ' WHERE staff_id = ? AND stage_before IN (4,5) AND stage_after >= 6 AND visited_at >= ? AND visited_at < ?',
            [(int) $staff, $from, $to])['n'] ?? 0);
        $q = [
            ['key' => 'visits', 'target' => $tgt, 'done' => min($done1, $tgt)],
            ['key' => 'followups', 'target' => 3, 'done' => min($done2, 3)],
            ['key' => 'warm_to_closing', 'target' => 1, 'done' => min($done3, 1)],
        ];
        foreach ($q as &$x) {
            $x['complete'] = $x['done'] >= $x['target'];
        }
        return $q;
    }

    /** بعد كل زيارة: لو المهام التلاتة خلصت ← +100 XP، ومكافأة الـ Streak (3 / 7 أيام) */
    private function check_quests_and_streak($staff)
    {
        $r   = $this->xp_rules();
        $day = $this->today();
        $all = array_reduce($this->quests($staff, $day), function ($c, $x) { return $c && $x['complete']; }, true);
        if ($all && $this->add_xp($staff, null, 'quest', $r['quest'] ?? 100, null, 'quest:' . $staff . ':' . $day)) {
            $this->feedback['quest'] = true;
        }
        $st = $this->streak($staff);
        foreach ([7 => 'streak_7', 3 => 'streak_3'] as $n => $key) {
            if ($st >= $n && $st % $n === 0) {
                if ($this->add_xp($staff, null, 'streak', $r[$key] ?? 0, null, $key . ':' . $staff . ':' . $day)) {
                    $this->feedback['streak'] = $st;
                }
                break;
            }
        }
    }

    /** Milestones (§5.4): أول ظرف، أول إذن واتساب، 10 دكاترة في Stage 5، أول Won، عمارة متغطية 100% */
    private function check_milestones($staff, $lead_id, $outcome, $stage)
    {
        $hit = [];
        if ($outcome === 'ENV') {
            $hit[] = 'first_envelope';
        }
        if ($outcome === 'WA' || $outcome === 'QR_SCAN') {
            $hit[] = 'first_wa';
        }
        if ($outcome === 'WON') {
            $hit[] = 'first_won';
        }
        if ($stage >= 5 && (int) ($this->row('SELECT COUNT(*) n FROM ' . $this->tbl('dv_doctor_meta') . ' WHERE stage >= 5 AND assigned_staff_id = ?', [(int) $staff])['n'] ?? 0) >= 10) {
            $hit[] = 'ten_stage5';
        }
        $b = $this->row('SELECT building_id FROM ' . $this->tbl('dv_doctor_meta') . ' WHERE lead_id = ?', [(int) $lead_id]);
        if ($b && $b['building_id']) {
            foreach ($this->buildings() as $bl) {
                if ((int) $bl['id'] === (int) $b['building_id'] && $bl['coverage'] >= 100) {
                    $hit[] = 'building_covered:' . $bl['id'];
                }
            }
        }
        foreach ($hit as $m) {
            $key = 'ms:' . $staff . ':' . $m;
            if (!$this->row("SELECT id FROM " . $this->tbl('dv_events') . " WHERE type = 'milestone' AND payload = ?", [json_encode(['key' => $key])])) {
                $this->db->insert($this->tbl('dv_events'), ['lead_id' => $lead_id, 'type' => 'milestone', 'payload' => json_encode(['key' => $key]), 'created_at' => $this->now()]);
                $this->feedback['milestones'][] = explode(':', $m)[0];
                $this->notify_staff($staff, 'dv_notify_milestone', [_l('dv_ms_' . explode(':', $m)[0])], 'doctor_visits');
            }
        }
    }

    public function milestones($staff)
    {
        $out = [];
        foreach ($this->rows("SELECT payload, created_at FROM " . $this->tbl('dv_events') . " WHERE type = 'milestone' ORDER BY id") as $e) {
            $k = json_decode($e['payload'], true)['key'] ?? '';
            if (strpos($k, 'ms:' . $staff . ':') === 0) {
                $out[] = ['key' => explode(':', substr($k, strlen('ms:' . $staff . ':')))[0], 'at' => $e['created_at']];
            }
        }
        return $out;
    }

    /** الـ Leaderboard بيظهر بس لو فيه أكتر من مندوب — والترتيب على تقدّم المراحل مش عدد الزيارات */
    public function leaderboard()
    {
        $rows = $this->rows('SELECT v.staff_id, COUNT(*) advances, CONCAT(s.firstname, \' \', s.lastname) AS name FROM ' . $this->tbl('dv_visits') . ' v
              LEFT JOIN ' . $this->tbl('staff') . ' s ON s.staffid = v.staff_id WHERE v.stage_after > v.stage_before AND v.visited_at >= ? GROUP BY v.staff_id, s.firstname, s.lastname ORDER BY advances DESC',
            [date('Y-m-01 00:00:00', $this->t())]);
        return count($rows) > 1 ? $rows : [];
    }

    /** "فاضلك 120 XP للـ Level 4 = ميعاد + متابعتين" — من أقرب حركات في الـ Queue */
    public function xp_path($staff)
    {
        $lvl = $this->level($staff);
        if ($lvl['next_at'] === null) {
            return [];
        }
        $need  = $lvl['to_next'];
        $moves = [];
        foreach (array_slice($this->queue($staff), 0, 30) as $d) {
            if ($d['next_step_xp'] > 0) {
                $moves[] = ['xp' => $d['next_step_xp'], 'label' => $d['next_step'], 'doctor' => $d['name']];
            }
        }
        usort($moves, function ($a, $b) { return $b['xp'] <=> $a['xp']; });
        $path = [];
        foreach ($moves as $m) {
            if ($need <= 0) {
                break;
            }
            $path[] = $m;
            $need  -= $m['xp'];
        }
        return $path;
    }

    // ================================================================= الإشعارات
    public function notify_staff($staff, $lang_key, array $params, $link)
    {
        if (function_exists('add_notification')) {
            add_notification(['description' => $lang_key, 'touserid' => (int) $staff, 'link' => $link, 'additional_data' => serialize($params)]);
            if (function_exists('pusher_trigger_notification')) {
                pusher_trigger_notification([(int) $staff]);
            }
        }
    }

    public function notify_managers($lang_key, array $params, $link)
    {
        foreach ($this->rows('SELECT staffid FROM ' . $this->tbl('staff') . ' WHERE active = 1 AND admin = 1') as $s) {
            $this->notify_staff($s['staffid'], $lang_key, $params, $link);
        }
    }

    // ================================================================= الواتساب (F8)
    public function templates($active_only = true)
    {
        return $this->rows('SELECT * FROM ' . $this->tbl('dv_wa_templates') . ($active_only ? ' WHERE active = 1' : '') . ' ORDER BY sort, id');
    }

    public function save_template(array $d, $id = null)
    {
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($d['key'] ?? '')));
        if ($key === '' || trim((string) ($d['body'] ?? '')) === '') {
            throw new Exception('المفتاح والنص إجباري');
        }
        $row = [
            'key' => $key, 'name' => trim((string) ($d['name'] ?? $key)), 'body' => trim($d['body']),
            'stage_min' => (int) ($d['stage_min'] ?? 0), 'stage_max' => (int) ($d['stage_max'] ?? 9),
            'days_since_touch_min' => max(0, (int) ($d['days_since_touch_min'] ?? 0)), 'sort' => (int) ($d['sort'] ?? 0),
            'active' => empty($d['active']) ? 0 : 1,
        ];
        $dup = $this->row('SELECT id FROM ' . $this->tbl('dv_wa_templates') . ' WHERE `key` = ?', [$key]);
        if ($dup && (int) $dup['id'] !== (int) $id) {
            throw new Exception('فيه قالب بنفس المفتاح');
        }
        if ($id) {
            $this->db->update($this->tbl('dv_wa_templates'), $row, ['id' => (int) $id]);
            return (int) $id;
        }
        $this->db->insert($this->tbl('dv_wa_templates'), $row);
        return (int) $this->db->insert_id();
    }

    /** القالب المقترح: آخر قالب (حسب الترتيب) مرحلته مناسبة وعدّى عليه الأيام المطلوبة من آخر Touch */
    public function suggest_template(array $doc)
    {
        $days = $doc['days_since_touch'] ?? 0;
        $best = null;
        foreach ($this->templates() as $t) {
            if ($doc['stage'] >= $t['stage_min'] && $doc['stage'] <= $t['stage_max'] && $days >= $t['days_since_touch_min']) {
                if (!$best || $t['days_since_touch_min'] > $best['days_since_touch_min'] || ($t['days_since_touch_min'] == $best['days_since_touch_min'] && $t['sort'] > $best['sort'])) {
                    $best = $t;
                }
            }
        }
        return $best;
    }

    /** المتغيرات + الرقم اللي هيتبعتله + الـ Consent */
    public function wa_context($lead_id, $staff = null)
    {
        $doc   = $this->doctor($lead_id);
        $staff = $staff ?: $this->me();
        $days  = [];
        for ($ts = $this->t() + 86400; count($days) < 2 && $ts < $this->t() + 86400 * 14; $ts += 86400) {
            if (!$this->is_off_day($ts, $staff)) {
                $days[] = dv_day_name($ts);
            }
        }
        $vars = [
            'doctor_name' => preg_replace('/^(د\/|د\.|د\s|Dr\.?\s*)\s*/u', '', $doc['name']), 'specialty' => $doc['specialty'], 'building' => $doc['building'],
            'rep_name' => $this->staff_name($staff), 'audit_link' => $doc['audit_link'], 'key_insight' => $doc['key_insight'],
            'day_a' => $days[0] ?? '', 'day_b' => $days[1] ?? '', 'meeting_time' => $doc['meeting_at'] ? dv_date($doc['meeting_at'], true) : '',
            'offer_link' => $doc['offer_link'], 'demo_link' => $doc['demo_link'],
        ];
        $personal = dv_phone($doc['phonenumber']);
        $phone    = $doc['wa_consent'] && $personal ? $personal : ($doc['clinic_whatsapp'] ?: $personal);
        return ['doctor' => $doc, 'vars' => $vars, 'phone' => $phone, 'consent' => (bool) $doc['wa_consent'],
            'suggested' => $this->suggest_template($doc), 'templates' => $this->templates()];
    }

    // ================================================================= الـ QR + API (F6)
    public function qr_link($doc)
    {
        $num  = dv_phone($this->setting('qr_whatsapp_number')) ?: '';
        $text = 'أنا ' . $doc['name'] . ' — شفت التحليل (' . $doc['qr_code'] . ')';
        return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
    }

    /** n8n لقط الكود من رسالة واتساب: بيتسجل QR_SCAN والـ Stage يطلع لـ 5 والمندوب يوصله إشعار */
    public function qr_event($code, $phone = null, $message = null, $received_at = null)
    {
        $code = strtoupper(trim((string) $code));
        if (!preg_match('/^DV-[A-Z0-9]{4}$/', $code)) {
            throw new Exception('invalid code');
        }
        $m = $this->row('SELECT lead_id, assigned_staff_id FROM ' . $this->tbl('dv_doctor_meta') . ' WHERE qr_code = ?', [$code]);
        if (!$m) {
            throw new Exception('unknown code');
        }
        $this->db->insert($this->tbl('dv_events'), ['lead_id' => $m['lead_id'], 'type' => 'qr_scan',
            'payload' => json_encode(['code' => $code, 'phone' => $phone, 'message' => mb_substr((string) $message, 0, 500), 'received_at' => $received_at], JSON_UNESCAPED_UNICODE),
            'created_at' => $this->now()]);
        $p = dv_phone($phone);
        if ($p) {
            $lead = $this->row('SELECT phonenumber FROM ' . $this->tbl('leads') . ' WHERE id = ?', [$m['lead_id']]);
            if ($lead && !dv_phone($lead['phonenumber'])) {
                $this->db->update($this->tbl('leads'), ['phonenumber' => $p], ['id' => $m['lead_id']]);
            }
        }
        $staff = (int) $m['assigned_staff_id'] ?: 0;
        $vid   = $this->log_visit($m['lead_id'], ['outcome' => 'QR_SCAN', 'channel' => 'system', 'note' => $message ? mb_substr($message, 0, 255) : null,
            'visited_at' => $received_at ?: null], $staff ?: 0);
        $doc = $this->doctor($m['lead_id'], false);
        if ($staff) {
            $this->notify_staff($staff, 'dv_notify_qr', [$doc['name']], 'doctor_visits/doctor/' . $m['lead_id']);
        }
        return ['visit_id' => $vid, 'lead_id' => (int) $m['lead_id'], 'stage' => (int) $doc['stage'], 'staff_id' => $staff, 'doctor' => $doc['name']];
    }

    /** "إلغاء" / stop من الدكتور ← الـ Consent يرجع 0 */
    public function opt_out($phone)
    {
        $p = dv_phone($phone);
        if (!$p) {
            throw new Exception('invalid phone');
        }
        $n = 0;
        foreach ($this->rows('SELECT m.lead_id FROM ' . $this->tbl('dv_doctor_meta') . ' m JOIN ' . $this->tbl('leads') . ' l ON l.id = m.lead_id WHERE m.clinic_whatsapp = ? OR l.phonenumber = ?', [$p, $p]) as $r) {
            $this->set_consent($r['lead_id'], false);
            $this->db->insert($this->tbl('dv_events'), ['lead_id' => $r['lead_id'], 'type' => 'opt_out', 'payload' => json_encode(['phone' => $p]), 'created_at' => $this->now()]);
            $n++;
        }
        return $n;
    }

    // ================================================================= التقارير (F7)
    /**
     * Funnel مجمّع حسب building / specialty / staff في فترة (بيعتمد على الزيارات في الفترة)
     */
    public function funnel($group = 'building', $from = null, $to = null)
    {
        $col = ['building' => 'b.name', 'specialty' => 'm.specialty', 'staff' => "CONCAT(s.firstname, ' ', s.lastname)"][$group] ?? 'b.name';
        $from = $from ?: date('Y-m-01', $this->t());
        $to   = $to ?: $this->today();
        $rows = $this->rows('SELECT ' . $col . ' AS grp, v.lead_id, v.outcome, v.stage_after FROM ' . $this->tbl('dv_visits') . ' v
              JOIN ' . $this->tbl('dv_doctor_meta') . ' m ON m.lead_id = v.lead_id LEFT JOIN ' . $this->tbl('dv_buildings') . ' b ON b.id = m.building_id
              LEFT JOIN ' . $this->tbl('staff') . ' s ON s.staffid = v.staff_id
             WHERE v.visited_at >= ? AND v.visited_at < ?' . $this->scope_sql('m'), [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00']);
        $g = [];
        foreach ($rows as $r) {
            $k = $r['grp'] ?: '—';
            $g[$k] = $g[$k] ?? ['visited' => [], 'doc' => [], 'wa' => [], 'booked' => [], 'held' => [], 'offer' => [], 'won' => [], 'visits' => 0];
            $x = &$g[$k];
            if ($r['outcome'] !== 'QR_SCAN' && $r['outcome'] !== 'WA_SENT') {
                $x['visits']++;
                $x['visited'][$r['lead_id']] = 1;
            }
            $st = (int) $r['stage_after'];
            foreach (['doc' => 4, 'wa' => 5, 'booked' => 6, 'held' => 7, 'offer' => 8, 'won' => 9] as $kk => $min) {
                if ($st >= $min) {
                    $x[$kk][$r['lead_id']] = 1;
                }
            }
            unset($x);
        }
        $out = [];
        foreach ($g as $k => $x) {
            $c = array_map(function ($v) { return is_array($v) ? count($v) : $v; }, $x);
            $c['group'] = $k;
            $c['access_rate']  = $c['visited'] ? round(100 * $c['doc'] / $c['visited']) : 0;
            $c['wa_rate']      = $c['doc'] ? round(100 * $c['wa'] / $c['doc']) : 0;
            $c['meeting_rate'] = $c['doc'] ? round(100 * $c['booked'] / $c['doc']) : 0;
            $c['show_rate']    = $c['booked'] ? round(100 * $c['held'] / $c['booked']) : 0;
            $c['close_rate']   = $c['offer'] ? round(100 * $c['won'] / $c['offer']) : 0;
            $c['visits_per_close'] = $c['won'] ? round($c['visits'] / $c['won'], 1) : null;
            $out[] = $c;
        }
        usort($out, function ($a, $b) { return $b['visits'] <=> $a['visits']; });
        return $out;
    }

    public function lost_reasons_report()
    {
        return $this->rows("SELECT lost_reason, COUNT(*) n FROM " . $this->tbl('dv_doctor_meta') . " m WHERE lost_reason IS NOT NULL AND lost_reason <> ''" . $this->scope_sql('m') . ' GROUP BY lost_reason ORDER BY n DESC');
    }

    /** متوسط الـ Sales Cycle بالأيام (أول زيارة ← Won) */
    public function sales_cycle()
    {
        $r = $this->rows('SELECT first_visit_at, won_at FROM ' . $this->tbl('dv_doctor_meta') . ' m WHERE stage = 9 AND won_at IS NOT NULL AND first_visit_at IS NOT NULL' . $this->scope_sql('m'));
        if (!$r) {
            return null;
        }
        $days = array_map(function ($x) { return (strtotime($x['won_at']) - strtotime($x['first_visit_at'])) / 86400; }, $r);
        return round(array_sum($days) / count($days), 1);
    }

    /** أنهي ممرضة بتوصلنا للدكتور */
    public function gatekeepers_report()
    {
        return $this->rows("SELECT m.gatekeeper_name AS name, b.name AS building, COUNT(*) doctors, SUM(CASE WHEN m.stage >= 4 THEN 1 ELSE 0 END) reached
              FROM " . $this->tbl('dv_doctor_meta') . ' m LEFT JOIN ' . $this->tbl('dv_buildings') . " b ON b.id = m.building_id
             WHERE m.gatekeeper_name IS NOT NULL AND m.gatekeeper_name <> ''" . $this->scope_sql('m') . ' GROUP BY m.gatekeeper_name, b.name ORDER BY reached DESC, doctors DESC');
    }

    /** CSV: زيارة لكل سطر */
    public function visits_export($from, $to)
    {
        return $this->rows('SELECT v.visited_at, v.logged_at, l.name AS doctor, m.specialty, b.name AS building, CONCAT(s.firstname, \' \', s.lastname) AS staff,
                   v.outcome, v.channel, v.stage_before, v.stage_after, v.lost_reason, v.next_action, v.next_action_at, v.note
              FROM ' . $this->tbl('dv_visits') . ' v JOIN ' . $this->tbl('leads') . ' l ON l.id = v.lead_id
              JOIN ' . $this->tbl('dv_doctor_meta') . ' m ON m.lead_id = v.lead_id LEFT JOIN ' . $this->tbl('dv_buildings') . ' b ON b.id = m.building_id
              LEFT JOIN ' . $this->tbl('staff') . ' s ON s.staffid = v.staff_id
             WHERE v.visited_at >= ? AND v.visited_at < ? ORDER BY v.visited_at', [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00']);
    }

    // ================================================================= الداشبورد
    public function dashboard($staff)
    {
        $won = $this->wins($staff, date('Y-m-01 00:00:00', $this->t()));
        return [
            'value_per_visit' => $this->value_per_visit(),
            'cold_start'      => $this->cold_start(),
            'streak'          => $this->streak($staff),
            'pipeline'        => $this->pipeline_value($this->can_see_all() ? null : $staff),
            'won_month'       => $won,
            'monthly_target'  => (float) $this->setting('monthly_target', $staff, 60000),
            'won_value_month' => $won['value'] > 0 ? $won['value'] : $won['n'] * $this->avg_deal_value(),
            'today_target'    => $this->daily_target($staff),
            'today_done'      => $this->visits_on($staff, $this->today()),
            'level'           => $this->level($staff),
            'level_month'     => $this->level($staff, true),
            'xp_path'         => $this->xp_path($staff),
            'zone'            => $this->closing_zone($this->can_see_all() ? null : $staff),
            'quests'          => $this->quests($staff),
            'funnel'          => $this->funnel('staff'),
            'milestones'      => $this->milestones($staff),
            'leaderboard'     => $this->leaderboard(),
            'visits_per_close' => $this->visits_per_close($staff),
        ];
    }
}
