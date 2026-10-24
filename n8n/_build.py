import json, sys, uuid
out = sys.argv[1]
PG = {"postgres": {"id": "SUPABASE_PG", "name": "Supabase bond (Postgres)"}}
SETTINGS = {"timezone": "Africa/Cairo", "executionOrder": "v1", "saveDataSuccessExecution": "none"}

def node(name, type_, params, pos, ver=1, creds=None, **extra):
    n = {"id": str(uuid.uuid5(uuid.NAMESPACE_URL, name + type_)), "name": name, "type": type_, "typeVersion": ver,
         "position": pos, "parameters": params}
    if creds: n["credentials"] = creds
    n.update(extra)
    return n

def chain(*names):
    c = {}
    for a, b in zip(names, names[1:]):
        c[a] = {"main": [[{"node": b, "type": "main", "index": 0}]]}
    return c

def pg(name, query, pos, replacement=None):
    p = {"operation": "executeQuery", "query": query, "options": {}}
    if replacement: p["options"]["queryReplacement"] = replacement
    return node(name, "n8n-nodes-base.postgres", p, pos, 2.5, PG)

def sched(name, rule, pos):
    return node(name, "n8n-nodes-base.scheduleTrigger", {"rule": {"interval": [rule]}}, pos, 1.2)

CONFIG_CODE = r"""// ⚙️ عدّل القيم دي مرة واحدة
return [{ json: {
  evoUrl: 'https://evolutoin-api-evolution-api.4tqpgc.easypanel.host',   // من غير / في الآخر
  instanceClients: 'dse-clients',   // رقم الرسايل للعملاء
  instanceTeam: 'dse-team',         // رقم منفصل للفريق
  fromEmail: 'Digital Solution <notifications@digitalsolutionegy.com>',   // لازم يبقى نفس إيميل الـ SMTP
  appUrl: 'https://bond-chi-tawny.vercel.app',   // رابط السيستم (لينك التقييم القصير + إيميلات الفريق)
}}];"""

def save(fn, wf):
    with open(f"{out}/{fn}", "w", encoding="utf-8") as f:
        json.dump(wf, f, ensure_ascii=False, indent=2)

# 1) Daily
save("01_daily.json", {"name": "DSE · Daily (run_daily)", "settings": SETTINGS, "nodes": [
    sched("كل يوم 00:05", {"field": "cronExpression", "expression": "5 0 * * *"}, [0, 0]),
    pg("run_daily", "select run_daily() as result;", [260, 0]),
], "connections": chain("كل يوم 00:05", "run_daily")})

# 2) Hourly
save("02_hourly.json", {"name": "DSE · Hourly (run_hourly)", "settings": SETTINGS, "nodes": [
    sched("كل ساعة", {"field": "cronExpression", "expression": "2 * * * *"}, [0, 0]),
    pg("run_hourly", "select run_hourly() as result;", [260, 0]),
], "connections": chain("كل ساعة", "run_hourly")})

# 3) Outbox
OUTBOX_SQL = """select o.*,
       (select string_agg(b.name_ar || ' ' || trim_scale(b.remaining)::text, ' · ')
          from v_cycle_balance b where b.cycle_id = o.cycle_id and b.unit_kind in ('count','hours')) as balance_text,
       (select end_date from cycles where id = o.cycle_id) as cycle_end,
       (select short_code from deliverables where id = o.deliverable_id) as feedback_code
  from v_alert_outbox o
 limit 50;"""

BUILD_CODE = r"""// بيبني نص الرسالة لكل alert_key (utility — من غير عروض)
const cfg = $('Config').first().json;
const tz = 'Africa/Cairo';
const d = (v) => v ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { weekday: 'long', day: '2-digit', month: '2-digit', timeZone: tz }).format(new Date(String(v).length === 10 ? v + 'T12:00:00Z' : v)) : '';
const t = (v) => v ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { hour: 'numeric', minute: '2-digit', timeZone: tz }).format(new Date(v)) : '';
const items = (arr) => (arr || []).filter(x => Number(x.remaining) > 0).map(x => `${x.name} ${x.remaining}`).join(' · ');

const out = [];
for (const { json: a } of $input.all()) {
  const p = a.payload || {};
  const bal = a.balance_text || '';
  const end = d(a.cycle_end || p.end_date);
  const link = `${cfg.appUrl}/f/${a.feedback_code}`;   // لينك قصير → فورم التقييم متملي
  let text = null;
  switch (a.alert_key) {
    // ---- للعميل ----
    case 'published': text = `الريل بتاعك نزل 🎬\n${p.post_url}\n\nرأيك في 10 ثواني: ${link}${bal ? `\n\nفاضلك ${bal} لحد ${end}` : ''}`; break;
    case 'session_done': text = `صورت النهارده ${p.qty} ${p.unit === 'hours' ? 'ساعة' : 'ريل'} ✅\nباقيلك ${p.remaining} لحد ${end}`; break;
    case 'session_24h': text = `تصويرك بكرة ${d(p.start)} الساعة ${t(p.start)} 📸\n${p.qty} ${p.type === 'trainer_hours' ? 'ساعة' : 'ريلز'}${p.locations ? ` في ${p.locations} لوكيشن` : ''}${p.outfits ? ` · هات ${p.outfits} لبس` : ''}\n\nاكتب 1 للتأكيد أو 2 لو عايز تغيّر الميعاد`; break;
    case 'session_2h': text = `تذكير: معادك النهارده الساعة ${t(p.start)} 📍`; break;
    case 'expiry_7d': case 'expiry_3d': case 'expiry_1d': text = `باقيلك ${items(p.items)} والباقة بتخلص ${d(p.end_date)}\nاحجز معادك قبلها علشان متضيعش 🙏`; break;
    case 'usage_high': text = `استهلكت 80% من ${p.name} (${p.used} من ${p.total})`; break;
    case 'content_runway_low': text = `معانا محتوى لحد ${d(p.first_gap_date)} بس\nمحتاجين تصوير قبل ${d(p.shoot_deadline)}`; break;
    case 'slot_missed': text = `النهارده كان ميعاد نشر ومكانش عندنا محتوى جاهز — كلّمنا نحجز تصوير`; break;
    case 'expired_summary': text = `الباقة خلصت ${d(p.end_date)}\nاستلمت: ${(p.items || []).map(x => `${x.name} ${x.used}/${x.total}`).join(' · ')}\nالرصيد اللي ماتستخدمش انتهى حسب العقد`; break;
    case 'renewal_invoice': text = `فاتورة التجديد: ${p.amount} ج.م — الباقة الجديدة تبدأ ${d(p.start_date)}\nالدفع: InstaPay / فودافون كاش / تحويل`; break;
    case 'payment_overdue': text = `تذكير بالفاتورة: ${p.description} — ${p.amount} ج.م\nالدفع: InstaPay / فودافون كاش / تحويل`; break;
    case 'script_sent': text = `سكربت جديد للمراجعة 📝\n*${p.title}*\n\n${p.body}\n\nرد بـ "موافق" أو ابعتلنا التعديلات`; break;
    case 'no_show': text = a.audience === 'client' ? `ماقدرناش نشوفك النهارده 😔 اتخصم ${p.penalty} ${p.unit === 'hours' ? 'ساعة' : 'ريل'} علشان الغياب حسب العقد` : `⚠️ ${a.client_name} مجاش الجلسة`; break;
    // ---- للفريق ----
    case 'client_unhappy': text = `🔴 عميل متضايق: ${p.client_name} (${p.client_phone})\nتصوير ${p.filming ?? '-'} · مونتاج ${p.editing ?? '-'} · لوكيشن ${p.location ?? '-'}\n${p.comment ? `"${p.comment}"\n` : ''}المصور: ${p.photographer ?? '-'} · المونتير: ${p.editor ?? '-'}\n${p.post_url ?? ''}\nكلمه دلوقتي واقفل الشكوى من السيستم`; break;
    case 'complaint_escalated': text = `🚨 ${p.client_name} مستني من ${p.wait_minutes} دقيقة ومحدش كلمه\n${p.comment ?? ''}`; break;
    case 'feedback_low': text = `تقييم وحش على شغلك عند ${p.client_name}: ${p.comment ?? ''}`; break;
    case 'revision_requested': text = `${a.client_name} طالب تعديل: ${p.comment ?? ''}`; break;
    case 'editing_late': text = `⏱ "${p.title}" واقف في المونتاج من ${d(p.since)}`; break;
    case 'usage_full_early': text = `فرصة upgrade: ${a.client_name} خلّص ${p.name} وباقي ${p.days_left} يوم`; break;
    case 'hours_low': text = `ساعات المدربين عند ${a.client_name} قربت تخلص: باقي ${p.remaining} من ${p.total}`; break;
    case 'low_usage_mid': text = `خطر churn: ${a.client_name} استهلك ${p.used_pct}% بس ونص الدورة عدّى`; break;
    case 'session_unconfirmed': text = `${a.client_name} لسه مأكدش جلسة ${d(p.start)} ${t(p.start)} — كلمه`; break;
    case 'slot_missed_internal': text = `ميعاد نشر ${a.client_name} فات والمحتوى كان جاهز!`; break;
    case 'power_low': text = `⚡ كارت الكهرباء: الرصيد ${p.balance} ج.م — هيخلص ${d(p.runs_out_at)} ${t(p.runs_out_at)}`; break;
    case 'reschedule_requested': text = `${a.client_name} عايز يغيّر ميعاد الجلسة — كلمه`; break;
  }
  const email = a.channel === 'email';
  out.push({ json: {
    alert_id: a.alert_id, channel: a.channel, number: a.phone, email: a.email,
    text: text && email ? `${text}\n\n—\nافتح السيستم: ${cfg.appUrl}${a.client_id ? `/clients/${a.client_id}` : '/inbox'}` : text,
    subject: text ? `${a.priority === 'urgent' ? '🔴 ' : ''}${text.split('\n')[0].slice(0, 90)}` : null,
    from: cfg.fromEmail,
    instance: email ? cfg.instanceTeam : cfg.instanceClients,
    evoUrl: cfg.evoUrl,
  }});
}
return out;"""

nodes = [
    sched("كل دقيقتين", {"field": "minutes", "minutesInterval": 2}, [0, 0]),
    node("Config", "n8n-nodes-base.code", {"jsCode": CONFIG_CODE}, [220, 0], 2),
    pg("v_alert_outbox", OUTBOX_SQL, [440, 0]),
    node("بناء الرسالة", "n8n-nodes-base.code", {"jsCode": BUILD_CODE}, [660, 0], 2),
    node("فيه نص؟", "n8n-nodes-base.if", {"conditions": {"options": {"caseSensitive": True, "typeValidation": "loose"},
        "conditions": [{"leftValue": "={{ $json.text }}", "rightValue": "", "operator": {"type": "string", "operation": "notEmpty", "singleValue": True}}],
        "combinator": "and"}, "options": {}}, [880, 0], 2.2),
    node("Evolution · sendText", "n8n-nodes-base.httpRequest", {
        "method": "POST", "url": "={{ $json.evoUrl }}/message/sendText/{{ $json.instance }}",
        "authentication": "genericCredentialType", "genericAuthType": "httpHeaderAuth",
        "sendBody": True, "specifyBody": "json",
        "jsonBody": "={{ JSON.stringify({ number: $json.number, text: $json.text }) }}",
        "options": {"batching": {"batch": {"batchSize": 1, "batchInterval": 1500}}}},
        [1100, -100], 4.2, {"httpHeaderAuth": {"id": "EVO_KEY", "name": "Evolution API key (header: apikey)"}},
        onError="continueRegularOutput"),
    node("إيميل؟", "n8n-nodes-base.if", {"conditions": {"options": {"caseSensitive": True, "typeValidation": "loose"},
        "conditions": [{"leftValue": "={{ $json.channel }}", "rightValue": "email", "operator": {"type": "string", "operation": "equals"}}],
        "combinator": "and"}, "options": {}}, [1100, -100], 2.2),
    node("إيميل للفريق", "n8n-nodes-base.emailSend", {
        "fromEmail": "={{ $json.from }}", "toEmail": "={{ $json.email }}", "subject": "={{ $json.subject }}",
        "emailFormat": "text", "text": "={{ $json.text }}", "options": {"appendAttribution": False}},
        [1320, -200], 2.1, {"smtp": {"id": "SMTP", "name": "SMTP (digitalsolutionegy.com)"}}, onError="continueRegularOutput"),
    pg("علّم sent / failed",
       "update alerts set status = $1, sent_at = case when $1 = 'sent' then now() end, error = nullif($2, '') where id = $3::bigint;",
       [1560, -100], "={{ $json.error ? 'failed' : 'sent' }},={{ $json.error ? JSON.stringify($json.error).slice(0, 500) : '' }},={{ $('بناء الرسالة').item.json.alert_id }}"),
    pg("علّم skipped", "update alerts set status = 'skipped' where id = $1::bigint;", [1100, 100], "={{ $json.alert_id }}"),
]
conns = chain("كل دقيقتين", "Config", "v_alert_outbox", "بناء الرسالة", "فيه نص؟")
conns["فيه نص؟"] = {"main": [[{"node": "إيميل؟", "type": "main", "index": 0}], [{"node": "علّم skipped", "type": "main", "index": 0}]]}
conns["إيميل؟"] = {"main": [[{"node": "إيميل للفريق", "type": "main", "index": 0}], [{"node": "Evolution · sendText", "type": "main", "index": 0}]]}
conns["إيميل للفريق"] = {"main": [[{"node": "علّم sent / failed", "type": "main", "index": 0}]]}
conns["Evolution · sendText"] = {"main": [[{"node": "علّم sent / failed", "type": "main", "index": 0}]]}
save("03_outbox.json", {"name": "DSE · Outbox (واتساب للعملاء · إيميل للفريق)", "settings": SETTINGS, "nodes": nodes, "connections": conns})

# 4) Feedback (Google Sheets → feedback)
MAP_CODE = r"""// ⚙️ عدّل أسماء الأعمدة حسب شيت ردود الفورم
const COL = {
  deliverable: 'رقم الريل',               // بيتملى لوحده من اللينك (pre-filled)
  client: 'كود العميل',                    // بيتملى لوحده من اللينك
  filming: 'قيّم التصوير',
  editing: 'قيّم المونتاج',
  location: 'قيّم اللوكيشن',
  wants: 'في حاجة عايز نعدلها؟',
  issues: 'إيه اللي مضايقك',
  comment: 'ملاحظتك',
  revision: 'عايز نعدّل الريل؟',
};
const yes = (v) => /أيوه|اه|نعم|yes/i.test(String(v || ''));
const n = (v) => { const x = parseInt(String(v || '').trim(), 10); return x >= 1 && x <= 5 ? x : null; };
return $input.all().map(({ json: r }) => ({ json: {
  deliverable_id: String(r[COL.deliverable] || '').trim(),
  filming: n(r[COL.filming]), editing: n(r[COL.editing]), location: n(r[COL.location]),
  wants: yes(r[COL.wants]),
  issues: String(r[COL.issues] || '').split(',').map(s => s.trim()).filter(Boolean),
  comment: String(r[COL.comment] || '').trim() || null,
  revision: yes(r[COL.revision]),
  source_ref: 'sheet-row-' + (r.row_number ?? r.rowNumber),
}}));"""
FB_SQL = """insert into feedback (deliverable_id, filming_score, editing_score, location_score, wants_changes, issues, comment, revision_requested, source_ref)
values ($1::uuid, nullif($2,'')::int, nullif($3,'')::int, nullif($4,'')::int, $5::boolean, string_to_array(nullif($6,''), '|'), nullif($7,''), $8::boolean, $9)
on conflict (source_ref) do nothing;"""
save("04_feedback.json", {"name": "DSE · Feedback (Google Sheets)", "settings": SETTINGS, "nodes": [
    node("صف جديد في الشيت", "n8n-nodes-base.googleSheetsTrigger", {
        "pollTimes": {"item": [{"mode": "everyMinute"}]},
        "documentId": {"__rl": True, "mode": "url", "value": "PASTE_SHEET_URL"},
        "sheetName": {"__rl": True, "mode": "list", "value": "gid=0"},
        "event": "rowAdded", "options": {}}, [0, 0], 1,
        {"googleSheetsTriggerOAuth2Api": {"id": "GSHEETS", "name": "Google Sheets"}}),
    node("Map", "n8n-nodes-base.code", {"jsCode": MAP_CODE}, [240, 0], 2),
    pg("insert feedback", FB_SQL, [480, 0],
       "={{ $json.deliverable_id }},={{ $json.filming ?? '' }},={{ $json.editing ?? '' }},={{ $json.location ?? '' }},={{ $json.wants }},={{ $json.issues.join('|') }},={{ $json.comment ?? '' }},={{ $json.revision }},={{ $json.source_ref }}"),
], "connections": chain("صف جديد في الشيت", "Map", "insert feedback")})

# 5) Inbound WhatsApp (Evolution webhook → تأكيد الحجز)
PARSE_CODE = r"""const b = $json.body || $json;
const data = b.data || {};
if ((b.event || '').toLowerCase().replace('_', '.') !== 'messages.upsert' || data.key?.fromMe) return [];
const jid = data.key?.remoteJid || '';
if (jid.endsWith('@g.us')) return [];
const text = (data.message?.conversation || data.message?.extendedTextMessage?.text || '').trim()
  .replace(/[٠-٩]/g, (c) => '٠١٢٣٤٥٦٧٨٩'.indexOf(c));
if (text !== '1' && text !== '2') return [];
return [{ json: { phone: jid.split('@')[0], answer: text, instance: b.instance } }];"""
CONFIRM_SQL = """with s as (
  select ss.id, ss.client_id, ss.cycle_id
    from studio_sessions ss join clients c on c.id = ss.client_id
   where c.phone_wa = $1 and ss.status = 'booked' and ss.booked_start between now() and now() + interval '48 hours'
   order by ss.booked_start limit 1),
u as (
  update studio_sessions set confirmation = case when $2 = '1' then 'confirmed' else 'reschedule_requested' end
   where id in (select id from s) returning id, client_id, cycle_id, confirmation)
select u.id, u.confirmation,
       case when u.confirmation = 'reschedule_requested'
            then queue_alert('reschedule_requested', 'reschedule:' || u.id, '{support,account_manager}', u.client_id, '{}'::jsonb, u.cycle_id, u.id) end as queued
  from u;"""
REPLY_CODE = r"""const cfg = $('Config').first().json;
const inb = $('Parse').first().json;
if (!$json.id) return [];   // مفيش جلسة قريبة
return [{ json: { evoUrl: cfg.evoUrl, instance: cfg.instanceClients, number: inb.phone,
  text: $json.confirmation === 'confirmed' ? 'تمام ✅ الميعاد اتأكد — مستنيينك' : 'تمام، هنكلمك دلوقتي نظبط ميعاد تاني 🙏' } }];"""
nodes = [
    node("Evolution Webhook", "n8n-nodes-base.webhook", {"httpMethod": "POST", "path": "dse-evolution-inbound", "options": {}}, [0, 0], 2, webhookId=str(uuid.uuid4())),
    node("Config", "n8n-nodes-base.code", {"jsCode": CONFIG_CODE}, [220, 0], 2),
    node("Parse", "n8n-nodes-base.code", {"jsCode": "return $('Evolution Webhook').all().flatMap((it) => { const $json = it.json; " + PARSE_CODE.replace("return [{", "return [{").replace("\n", " ") + " });"}, [440, 0], 2),
    pg("تأكيد / تغيير الحجز", CONFIRM_SQL, [660, 0], "={{ $json.phone }},={{ $json.answer }}"),
    node("الرد", "n8n-nodes-base.code", {"jsCode": REPLY_CODE}, [880, 0], 2),
    node("Evolution · reply", "n8n-nodes-base.httpRequest", {
        "method": "POST", "url": "={{ $json.evoUrl }}/message/sendText/{{ $json.instance }}",
        "authentication": "genericCredentialType", "genericAuthType": "httpHeaderAuth",
        "sendBody": True, "specifyBody": "json", "jsonBody": "={{ JSON.stringify({ number: $json.number, text: $json.text }) }}", "options": {}},
        [1100, 0], 4.2, {"httpHeaderAuth": {"id": "EVO_KEY", "name": "Evolution API key (header: apikey)"}}, onError="continueRegularOutput"),
]
save("05_inbound_whatsapp.json", {"name": "DSE · Inbound WhatsApp (تأكيد الحجز)", "settings": SETTINGS, "nodes": nodes,
    "connections": chain("Evolution Webhook", "Config", "Parse", "تأكيد / تغيير الحجز", "الرد", "Evolution · reply")})
print("ok")
