// اختبارات الداتابيز على Postgres حقيقي (PGlite) — من غير Supabase
// node tests/run.mjs
import { PGlite } from '@electric-sql/pglite';
import { btree_gist } from '@electric-sql/pglite/contrib/btree_gist';
import { pgcrypto } from '@electric-sql/pglite/contrib/pgcrypto';
import { readFileSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const migDir = join(root, 'supabase/migrations');

// ---- بيئة شبه Supabase: auth schema + الأدوار + الصلاحيات الافتراضية ----
const SUPABASE_STUB = `
create role anon nologin; create role authenticated nologin; create role service_role nologin bypassrls;
create schema auth;
create table auth.users (id uuid primary key default gen_random_uuid(), email text unique);
create function auth.uid() returns uuid language sql stable as
  $$ select nullif(current_setting('request.jwt.claim.sub', true), '')::uuid $$;
create function auth.role() returns text language sql stable as
  $$ select nullif(current_setting('request.jwt.claim.role', true), '') $$;
grant usage on schema auth, public to anon, authenticated, service_role;
grant execute on all functions in schema auth to anon, authenticated, service_role;
alter default privileges in schema public grant all on tables to anon, authenticated, service_role;
alter default privileges in schema public grant all on sequences to anon, authenticated, service_role;
alter default privileges in schema public grant execute on functions to anon, authenticated, service_role;
`;

const db = new PGlite({ extensions: { btree_gist, pgcrypto } });
await db.exec(SUPABASE_STUB);

let passed = 0, failed = 0;
async function test(name, fn) {
  try { await fn(); passed++; console.log('  ✔', name); }
  catch (e) { failed++; console.log('  ✘', name, '\n     ', e.message); }
}
function assert(cond, msg) { if (!cond) throw new Error(msg || 'assertion failed'); }
async function one(sql, params) { return (await db.query(sql, params)).rows[0]; }
async function rows(sql, params) { return (await db.query(sql, params)).rows; }
async function val(sql, params) { const r = await one(sql, params); return r ? Object.values(r)[0] : undefined; }
async function throws(sql, params, includes) {
  try { await db.query(sql, params); } catch (e) {
    if (includes && !e.message.includes(includes)) throw new Error(`error غلط: ${e.message}`);
    return e.message;
  }
  throw new Error('كان المفروض يرمي error');
}
// تشغيل كموظف (JWT) أو كـ service (من غير claims)
async function as(staffId) {
  if (staffId === null) {
    await db.exec(`reset role; select set_config('request.jwt.claim.sub', '', false), set_config('request.jwt.claim.role', '', false);`);
  } else if (staffId === 'anon') {
    await db.exec(`select set_config('request.jwt.claim.sub', '', false), set_config('request.jwt.claim.role', 'anon', false); set role anon;`);
  } else {
    await db.exec(`select set_config('request.jwt.claim.sub', '${staffId}', false), set_config('request.jwt.claim.role', 'authenticated', false); set role authenticated;`);
  }
}

console.log('Migrations:');
for (const f of readdirSync(migDir).filter((f) => f.endsWith('.sql')).sort()) {
  await test(f, async () => { await db.exec(readFileSync(join(migDir, f), 'utf8')); });
}
if (failed) { console.log(`\n${failed} migration(s) failed`); process.exit(1); }

// ---- الفريق ----
const team = {};
for (const [key, email, name, roles] of [
  ['admin', 'ibrahim@x.com', 'إبراهيم', '{admin}'],
  ['sales', 'mariam@x.com', 'مريم', '{sales}'],
  ['am', 'engy@x.com', 'إنجي', '{support,account_manager}'],
  ['social', 'madonna@x.com', 'مادونا', '{social_media}'],
  ['studio', 'andrew@x.com', 'أندرو', '{studio,editor,designer}'],
  ['lead', 'mina@x.com', 'مينا', '{studio,editor,designer,media_lead}'],
]) {
  await db.query('insert into auth.users (email) values ($1)', [email]);
  team[key] = await val('select add_staff($1, $2, $3::text[], null)', [email, name, roles]);
}
await db.query('insert into auth.users (email) values ($1)', ['stranger@x.com']);
const stranger = await val(`select id from auth.users where email = 'stranger@x.com'`);
const studioId = await val('select id from studios limit 1');
const studio2 = await val(`insert into studios (name) values ('استوديو 2') returning id`);

// تواريخ نسبية بتوقيت القاهرة
const at = (days, hour) =>
  val(`select ((cairo_today() + $1::int)::timestamp + make_interval(hours => $2::int)) at time zone 'Africa/Cairo'`, [days, hour]);

console.log('\nالعملاء والاشتراكات:');
let client, sub, cycle, invoice;
await test('كود العميل بيتعمل لوحده C001 والـ AM الافتراضي إنجي', async () => {
  const r = await one(`insert into clients (name, phone_wa) values ('مطعم الشيف', '201012345678') returning *`);
  client = r.id;
  assert(r.code === 'C001', r.code);
  assert(r.account_manager_id === team.am, 'AM');
});
await test('رقم واتساب غلط بيترفض', async () => {
  await throws(`insert into clients (name, phone_wa) values ('x', '01012345678')`, [], 'رقم الواتساب');
});
await test('create_subscription: اشتراك + دورة pending + فاتورة', async () => {
  await as(team.sales);
  sub = await val(`select create_subscription($1, 'Growth', 15000,
    '[{"type":"reel","qty":10},{"type":"design","qty":10},{"type":"content_mgmt","qty":1}]', null, 30)`, [client]);
  await as(null);
  const c = await one('select * from cycles where subscription_id = $1', [sub]);
  cycle = c.id;
  assert(c.status === 'pending_payment', c.status);
  invoice = await val(`select id from invoices where cycle_id = $1 and kind = 'subscription'`, [cycle]);
  assert(invoice);
});
await test('الحجز قبل الدفع بيترفض', async () => {
  await throws(`insert into studio_sessions (client_id, studio_id, booked_start, planned_qty) values ($1, $2, $3, 4)`,
    [client, studioId, await at(1, 12)], 'متفعلتش');
});
await test('الدفع بيفعّل الباقة', async () => {
  await as(team.admin);
  await db.query(`insert into payments (invoice_id, amount, method) values ($1, 15000, 'instapay')`, [invoice]);
  await as(null);
  assert((await val('select status from cycles where id = $1', [cycle])) === 'active');
  assert((await val('select status from invoices where id = $1', [invoice])) === 'paid');
});
await test('موظف استوديو مينفعش يسجل دفع (RLS)', async () => {
  await as(team.studio);
  await throws(`insert into payments (invoice_id, amount, method) values ($1, 1, 'cash')`, [invoice], 'row-level security');
  await as(null);
});

console.log('\nالحجز والجلسات:');
let session;
await test('الحجز بيحسب المدة واللبسات واللوكيشنز', async () => {
  await as(team.am);
  const r = await one(`insert into studio_sessions (client_id, studio_id, photographer_id, booked_start, planned_qty, created_by)
    values ($1, $2, $3, $4, 6, $5) returning *, extract(epoch from booked_end - booked_start)/60 as mins`,
    [client, studioId, team.studio, await at(1, 12), team.am]);
  await as(null);
  session = r.id;
  assert(Number(r.mins) === 6 * 25 + 15, r.mins);
  assert(r.outfits_required === 2 && r.locations_required === 2, `${r.outfits_required}/${r.locations_required}`);
});
await test('تعارض نفس الاستوديو بيترفض', async () => {
  await throws(`insert into studio_sessions (client_id, studio_id, booked_start, planned_qty) values ($1, $2, $3, 1)`,
    [client, studioId, await at(1, 13)], 'no_studio_overlap');
});
await test('تعارض نفس المصور في استوديو تاني بيترفض', async () => {
  await throws(`insert into studio_sessions (client_id, studio_id, photographer_id, booked_start, planned_qty) values ($1, $2, $3, $4, 1)`,
    [client, studio2, team.studio, await at(1, 13)], 'no_photographer_overlap');
});
await test('الحجز أكتر من الرصيد (بعد الحجوزات التانية) بيترفض', async () => {
  await throws(`insert into studio_sessions (client_id, studio_id, booked_start, planned_qty) values ($1, $2, $3, 5)`,
    [client, studio2, await at(2, 12)], 'الرصيد مش كفاية');
});
await test('v_cycle_balance بيبيّن المحجوز', async () => {
  const b = await one(`select * from v_cycle_balance where cycle_id = $1 and deliverable_type = 'reel'`, [cycle]);
  assert(Number(b.reserved) === 6 && Number(b.remaining) === 10, JSON.stringify(b));
});
await test('السيلز مينفعش يحجز (RLS)', async () => {
  await as(team.sales);
  await throws(`insert into studio_sessions (client_id, studio_id, booked_start, planned_qty) values ($1, $2, $3, 1)`,
    [client, studio2, await at(3, 12)], 'row-level security');
  await as(null);
});
await test('check_in_session', async () => {
  await as(team.studio);
  await db.query('select check_in_session($1)', [session]);
  await as(null);
  assert((await val('select status from studio_sessions where id = $1', [session])) === 'checked_in');
});
await test('خلصت الجلسة بتترفض لو المانوال الإجباري مخلصش', async () => {
  await as(team.studio);
  await throws('select complete_session($1, 6)', [session], 'لازم تخلص البنود الإجبارية');
  await as(null);
});
await test('بعد المانوال: complete_session بيخصم وبيعمل الريلز', async () => {
  await as(team.studio);
  await db.query(`insert into checklist_runs (item_id, session_id, done_by)
    select i.id, $1, $2 from checklist_template_items i join checklist_templates t on t.id = i.template_id
     where i.is_required and t.stage in ('onboarding','pre_shoot','shoot')`, [session, team.studio]);
  const q = await val('select complete_session($1, 6, null, null, $2)', [session, team.studio]);
  await as(null);
  assert(Number(q) === 6);
  assert(Number(await val(`select count(*) from deliverables where session_id = $1`, [session])) === 6);
  const b = await one(`select * from v_cycle_balance where cycle_id = $1 and deliverable_type = 'reel'`, [cycle]);
  assert(Number(b.used) === 6 && Number(b.remaining) === 4 && Number(b.reserved) === 0, JSON.stringify(b));
});
await test('المصور خد XP على الجلسة', async () => {
  assert(Number(await val(`select sum(points) from xp_ledger where staff_id = $1 and rule_code = 'session_done'`, [team.studio])) === 20);
});
await test('مجاش: بيخصم ريل', async () => {
  const s = await val(`insert into studio_sessions (client_id, studio_id, booked_start, planned_qty) values ($1, $2, $3, 1) returning id`,
    [client, studio2, await at(-0, 1)]);
  await as(team.am);
  const pen = await val('select mark_no_show($1)', [s]);
  await as(null);
  assert(Number(pen) === 1);
  assert(Number(await val(`select used from cycle_entitlements where cycle_id = $1 and deliverable_type = 'reel'`, [cycle])) === 7);
});
await test('الإلغاء المتأخر بيرجع late=true وبيخصم', async () => {
  const s = await val(`insert into studio_sessions (client_id, studio_id, booked_start, planned_qty) values ($1, $2, now() + interval '3 hours', 1) returning id`,
    [client, studio2]);
  await as(team.am);
  const late = await val(`select cancel_session($1, 'ظرف')`, [s]);
  await as(null);
  assert(late === true);
  assert(Number(await val(`select used from cycle_entitlements where cycle_id = $1 and deliverable_type = 'reel'`, [cycle])) === 8);
});
await test('الإلغاء بدري مش بيخصم', async () => {
  const s = await val(`insert into studio_sessions (client_id, studio_id, booked_start, planned_qty) values ($1, $2, $3, 1) returning id`,
    [client, studio2, await at(5, 12)]);
  const late = await val(`select cancel_session($1, 'اتغير الميعاد')`, [s]);
  assert(late === false);
  assert(Number(await val(`select used from cycle_entitlements where cycle_id = $1 and deliverable_type = 'reel'`, [cycle])) === 8);
});

console.log('\nالإنتاج والنشر:');
let reel;
await test('الموافقة بتترفض لو مانوال المونتاج مخلصش', async () => {
  reel = await val(`select id from deliverables where session_id = $1 order by title limit 1`, [session]);
  await as(team.studio);
  await db.query(`update deliverables set stage = 'editing', assignee_id = $2, updated_by = $2 where id = $1`, [reel, team.studio]);
  await db.query(`update deliverables set stage = 'review', updated_by = $2 where id = $1`, [reel, team.studio]);
  await throws(`update deliverables set stage = 'approved' where id = $1`, [reel], 'لازم تخلص البنود الإجبارية');
  await as(null);
});
await test('بعد المانوال: اتوافق والمونتير خد XP', async () => {
  await as(team.studio);
  await db.query(`insert into checklist_runs (item_id, deliverable_id, done_by)
    select i.id, $1, $2 from checklist_template_items i join checklist_templates t on t.id = i.template_id
     where i.is_required and t.stage = 'editing'`, [reel, team.studio]);
  await db.query(`update deliverables set stage = 'approved', updated_by = $2 where id = $1`, [reel, team.studio]);
  await as(null);
  assert(Number(await val(`select count(*) from xp_ledger where rule_code = 'deliverable_approved' and staff_id = $1`, [team.studio])) === 1);
});
await test('النشر من غير لينك بيترفض', async () => {
  await as(team.social);
  await db.query(`insert into checklist_runs (item_id, deliverable_id, done_by)
    select i.id, $1, $2 from checklist_template_items i join checklist_templates t on t.id = i.template_id
     where i.is_required and t.stage = 'publishing'`, [reel, team.social]);
  await throws(`update deliverables set stage = 'published' where id = $1`, [reel], 'لينك البوست');
  await as(null);
});
await test('النشر باللينك بيطلّع رسالة published للعميل', async () => {
  await as(team.social);
  await db.query(`update deliverables set stage = 'published', post_url = 'https://instagram.com/p/x', updated_by = $2 where id = $1`, [reel, team.social]);
  await as(null);
  const a = await one(`select * from alerts where alert_key = 'published' and deliverable_id = $1`, [reel]);
  assert(a && a.payload.client_code === 'C001', JSON.stringify(a));
});
await test('الرجوع للمونتاج بعد المراجعة بيزوّد التعديلات', async () => {
  const d = await val(`select id from deliverables where session_id = $1 and stage = 'filmed' limit 1`, [session]);
  await db.query(`update deliverables set stage = 'review' where id = $1`, [d]);
  await db.query(`update deliverables set stage = 'editing' where id = $1`, [d]);
  assert(Number(await val('select revision_count from deliverables where id = $1', [d])) === 1);
});
await test('v_deliverable_credits: مين صوّر ومين عمل مونتاج ومين نشر', async () => {
  const c = await one('select * from v_deliverable_credits where deliverable_id = $1', [reel]);
  assert(c.photographer === 'أندرو' && c.editor === 'أندرو' && c.publisher === 'مادونا', JSON.stringify(c));
});

console.log('\nالتقييمات والشكاوى:');
let complaintId;
await test('تقييم وحش: شكوى عاجلة لمريم + خصم XP + location_score', async () => {
  await db.query(`insert into feedback (deliverable_id, filming_score, editing_score, location_score, comment, source_ref)
    values ($1, 2, 4, 3, 'الإضاءة وحشة', 'row-2')`, [reel]);
  const a = await rows(`select * from alerts where alert_key = 'client_unhappy'`);
  assert(a.length === 3 && a.every((x) => x.priority === 'urgent'), a.length);
  assert(a[0].payload.location === 3 && a[0].payload.photographer === 'أندرو');
  assert(Number(await val(`select points from xp_ledger where rule_code = 'feedback_low_filming'`)) === -10);
});
await test('نفس صف الشيت مش بيتسجل مرتين', async () => {
  await throws(`insert into feedback (deliverable_id, filming_score, source_ref) values ($1, 5, 'row-2')`, [reel], 'duplicate');
});
await test('مريم بتشوف الشكوى في v_complaints، والمصور لأ', async () => {
  await as(team.sales);
  const c = await rows('select * from v_complaints');
  await as(team.social);
  const none = await rows('select * from v_complaints');
  await as(null);
  assert(c.length === 1 && c[0].is_open && c[0].comment === 'الإضاءة وحشة', JSON.stringify(c));
  assert(none.length === 0, none.length);
  complaintId = c[0].alert_id;
});
await test('resolve_alert من غير "حصل إيه" بيترفض', async () => {
  await as(team.sales);
  await throws(`select resolve_alert($1, '  ')`, [complaintId], 'اكتب حصل إيه');
  await as(null);
});
await test('الشكوى اللي عدّى عليها ساعتين بتتصعّد لإبراهيم', async () => {
  await db.query(`update alerts set created_at = now() - interval '3 hours' where alert_key = 'client_unhappy'`);
  const r = await val('select run_hourly()');
  assert(r.escalated === 1, JSON.stringify(r));
  assert(Number(await val(`select count(*) from alerts where alert_key = 'complaint_escalated' and audience = 'admin'`)) === 1);
});
await test('resolve_alert بيقفل كل النسخ والتصعيد', async () => {
  await as(team.sales);
  await db.query(`select resolve_alert($1, 'كلمته ووعدناه بإعادة تصوير')`, [complaintId]);
  await as(null);
  assert(Number(await val(`select count(*) from alerts where handled_at is null and (alert_key = 'client_unhappy' or alert_key = 'complaint_escalated')`)) === 0);
  const c = await one('select * from v_complaints');
  assert(!c.is_open && c.handled_by_name === 'مريم' && !c.within_sla);
});
await test('تقييم 5: XP للمصور والمونتير', async () => {
  const d = await val(`select id from deliverables where session_id = $1 and stage = 'filmed' limit 1`, [session]);
  await db.query(`insert into feedback (deliverable_id, filming_score, editing_score, source_ref) values ($1, 5, 5, 'row-3')`, [d]);
  assert(Number(await val(`select count(*) from xp_ledger where rule_code = 'feedback_top_filming'`)) === 1);
});

console.log('\nالباقات:');
await test('add_topup بيزوّد الرصيد فورًا + فاتورة', async () => {
  await as(team.am);
  await db.query(`select add_topup($1, 'reel', 3, 3000)`, [cycle]);
  await as(null);
  const b = await one(`select * from v_cycle_balance where cycle_id = $1 and deliverable_type = 'reel'`, [cycle]);
  assert(Number(b.total) === 13, b.total);
  assert(Number(await val(`select count(*) from invoices where cycle_id = $1 and kind = 'topup'`, [cycle])) === 1);
});
await test('change_subscription: upgrade بيسجل mrr_delta', async () => {
  await as(team.sales);
  await db.query(`select change_subscription($1, 'upgrade', 20000, '[{"type":"reel","qty":15},{"type":"design","qty":10}]')`, [sub]);
  await as(null);
  assert(Number(await val(`select mrr_delta from subscription_events where subscription_id = $1 and event_type = 'upgrade'`, [sub])) === 5000);
});
await test('المصور مينفعش يعمل upgrade', async () => {
  await as(team.studio);
  await throws(`select change_subscription($1, 'upgrade', 1, null)`, [sub], 'مش متاحة لدورك');
  await as(null);
});
await test('v_client_overview فيه health score والرصيد', async () => {
  const o = await one('select * from v_client_overview where client_id = $1', [client]);
  assert(o.health_score !== null && Array.isArray(o.balances) && o.code === 'C001', JSON.stringify(o));
});

console.log('\nالصلاحيات:');
await test('حساب مش موظف مش بيشوف أي عميل', async () => {
  await as(stranger);
  const r = await rows('select * from clients');
  await as(null);
  assert(r.length === 0);
});
await test('anon مالوش صلاحية على الجداول', async () => {
  await as('anon');
  await throws('select * from clients', [], 'permission denied');
  await as(null);
});
await test('anon مينفعش ينادي queue_alert', async () => {
  await as('anon');
  await throws(`select queue_alert('x','x','{client}', null)`, [], 'permission denied');
  await as(null);
});
await test('المصور مينفعش يضيف عميل، والسيلز يقدر', async () => {
  await as(team.studio);
  await throws(`insert into clients (name, phone_wa) values ('x', '201011111111')`, [], 'row-level security');
  await as(team.sales);
  await db.query(`insert into clients (name, phone_wa) values ('جيم الأبطال', '201022222222')`);
  await as(null);
});
await test('التنبيهات: كل واحد يشوف اللي يخصه', async () => {
  await as(team.social);
  const mine = await rows('select * from alerts');
  await as(team.admin);
  const all = await rows('select * from alerts');
  await as(null);
  assert(mine.length === 0 && all.length > 0, `${mine.length}/${all.length}`);
});
await test('add_staff للأدمن بس', async () => {
  await as(team.sales);
  await throws(`select add_staff('stranger@x.com', 'x', '{sales}')`, [], 'مش متاحة لدورك');
  await as(null);
});

console.log('\nالكهرباء:');
await test('record_meter_reading بيحسب الصرف اليومي وهيخلص إمتى', async () => {
  const m = await val('select id from utility_meters limit 1');
  await db.query(`insert into meter_readings (meter_id, balance, read_at) values ($1, 500, now() - interval '2 days')`, [m]);
  await db.query(`insert into meter_recharges (meter_id, amount, recharged_at) values ($1, 100, now() - interval '1 day')`, [m]);
  await as(team.studio);
  const s = await val('select record_meter_reading($1, 400, 3)', [m]);
  await as(null);
  // 500 + 100 − 400 = 200 في يومين = 100/يوم → باقي 4 أيام
  assert(Number(s.daily_burn) === 100 && Math.abs(Number(s.days_left) - 4) < 0.1, JSON.stringify(s));
});
await test('رصيد قليل بيطلّع تنبيه power_low', async () => {
  const m = await val('select id from utility_meters limit 1');
  await as(team.studio);
  await db.query('select record_meter_reading($1, 120, 3)', [m]);
  await as(null);
  assert(Number(await val(`select count(*) from alerts where alert_key = 'power_low'`)) >= 1);
});

console.log('\nالتحديات والبونص:');
await test('التحدي بيخلص لوحده ويدّي المكافأة', async () => {
  await db.query(`insert into challenges (name_ar, metric, target, reward_xp, roles) values ('صوّر جلستين', 'sessions_done', 2, 50, '{studio}')`);
  const s = await val(`insert into studio_sessions (client_id, studio_id, photographer_id, booked_start, planned_qty) values ($1, $2, $3, $4, 1) returning id`,
    [client, studioId, team.studio, await at(3, 10)]);
  await db.query(`insert into checklist_runs (item_id, session_id, done_by)
    select i.id, $1, $2 from checklist_template_items i join checklist_templates t on t.id = i.template_id
     where i.is_required and t.stage in ('pre_shoot','shoot')`, [s, team.studio]);
  await db.query('select complete_session($1, 1)', [s]);
  const p = await one(`select * from v_challenge_progress where staff_id = $1`, [team.studio]);
  assert(p.completed && Number(p.progress) === 2, JSON.stringify(p));
  assert(Number(await val(`select points from xp_ledger where rule_code = 'challenge_reward' and staff_id = $1`, [team.studio])) === 50);
});
await test('v_leaderboard بالترتيب والرتبة والبونص المتوقع', async () => {
  const l = await rows('select * from v_leaderboard order by position');
  assert(l[0].staff_id === team.studio && l[0].rank_icon === '🥉' && Number(l[0].expected_bonus) > 0, JSON.stringify(l[0]));
});
await test('prepare_monthly_bonuses + اعتماد الأدمن', async () => {
  const n = await val(`select prepare_monthly_bonuses(cairo_today())`);
  assert(Number(n) > 0);
  await as(team.admin);
  await db.query(`update bonus_payouts set status = 'approved' where staff_id = $1`, [team.studio]);
  await as(null);
  const b = await one('select * from bonus_payouts where staff_id = $1', [team.studio]);
  assert(b.status === 'approved' && b.decided_by === team.admin);
});

console.log('\nالـ Jobs:');
await test('run_daily شغال', async () => {
  await db.query(`insert into publishing_schedule (client_id, deliverable_type, weekday) select $1, 'reel', d from generate_series(0, 6) d`, [client]);
  const r = await val('select run_daily()');
  assert(r.slots > 0, JSON.stringify(r));
  assert((await rows('select * from v_content_runway')).length === 1);
  assert((await rows('select * from v_schedule_package_check')).length === 1);
});
console.log('\nدوال الواجهة (008):');
await test('preview_booking: المدة واللبسات والرصيد زي التريجر', async () => {
  await as(team.am);
  const p = await val(`select preview_booking($1, 'reel', 6, $2)`, [client, await at(4, 12)]);
  await as(null);
  assert(p.duration_minutes === 165 && p.outfits === 2 && p.locations === 2, JSON.stringify(p));
  assert(p.cycle_id === cycle && typeof p.enough === 'boolean' && p.covers_until, JSON.stringify(p));
});
await test('schedule_package_diff: "الجدول محتاج 13 والباقة 10"', async () => {
  const d = await val(`select schedule_package_diff('{"reel":3}', '[{"type":"reel","qty":10}]', 30)`);
  assert(d.length === 1 && Number(d[0].needed) === 12.9, JSON.stringify(d));
  const ok = await val(`select schedule_package_diff('{"reel":2}', '[{"type":"reel","qty":9}]', 30)`);
  assert(ok.length === 0, JSON.stringify(ok));
});
await test('v_dashboard_kpis و v_slot_board', async () => {
  const k = await one('select * from v_dashboard_kpis');
  assert(Number(k.mrr) > 0 && Number(k.active_clients) === 1, JSON.stringify(k));
  const s = await rows('select board_status from v_slot_board');
  assert(s.length > 0 && s.some((x) => x.board_status === 'in_pipeline'), JSON.stringify(s.slice(0, 5)));
});

console.log('\nn8n (الـ SQL اللي في الـ workflows):');
const n8nSql = (file, nodeName) => JSON.parse(readFileSync(join(root, 'n8n', file), 'utf8')).nodes.find((n) => n.name === nodeName).parameters.query;
await test('Outbox: v_alert_outbox + الرصيد + علّم sent', async () => {
  await db.query(`update clients set phone_wa = phone_wa`);
  const r = await rows(n8nSql('03_outbox.json', 'v_alert_outbox'));
  assert(r.length > 0 && r.some((x) => x.alert_key === 'published' && x.balance_text), JSON.stringify(r[0]));
  await db.query(n8nSql('03_outbox.json', 'علّم sent / failed'), ['sent', '', r[0].alert_id]);
  assert((await val('select status from alerts where id = $1', [r[0].alert_id])) === 'sent');
});
await test('Feedback: insert من الشيت مرة واحدة بس', async () => {
  const q = n8nSql('04_feedback.json', 'insert feedback');
  const args = [reel, '4', '5', '', 'false', 'إضاءة|صوت', 'حلو', 'false', 'sheet-row-9'];
  await db.query(q, args);
  await db.query(q, args);
  assert(Number(await val(`select count(*) from feedback where source_ref = 'sheet-row-9'`)) === 1);
});
await test('Inbound WhatsApp: "1" بيأكد و "2" بيطلب تغيير + تنبيه', async () => {
  const s = await val(`insert into studio_sessions (client_id, studio_id, booked_start, planned_qty) values ($1, $2, now() + interval '20 hours', 1) returning id`, [client, studio2]);
  const q = n8nSql('05_inbound_whatsapp.json', 'تأكيد / تغيير الحجز');
  const a = await one(q, ['201012345678', '1']);
  assert(a.id === s && a.confirmation === 'confirmed', JSON.stringify(a));
  const b = await one(q, ['201012345678', '2']);
  assert(b.confirmation === 'reschedule_requested' && b.queued === 2, JSON.stringify(b));
  assert(!(await one(q, ['201000000000', '1'])), 'رقم مش عميل');
});

await test('انتهاء الدورة بيحسب الضايع', async () => {
  await db.query(`update cycles set start_date = start_date - 40, end_date = cairo_today() - 1 where id = $1`, [cycle]);
  await db.query(`update subscriptions set auto_renew = false where id = $1`, [sub]);
  await db.query('select close_expired_cycles()');
  const e = await one(`select * from cycle_entitlements where cycle_id = $1 and deliverable_type = 'reel'`, [cycle]);
  assert(Number(e.forfeited) === Number(e.granted) + Number(e.bonus) - Number(e.used), JSON.stringify(e));
  assert((await val('select status from clients where id = $1', [client])) === 'churned');
});
await test('الـ views كلها بتتقري', async () => {
  for (const v of ['v_mrr_monthly', 'v_retention_monthly', 'v_utilization_monthly', 'v_feedback_by_staff',
                   'v_today_sessions', 'v_meter_status', 'v_alert_outbox']) await rows(`select * from ${v}`);
});

console.log(`\n${passed} passed · ${failed} failed`);
process.exit(failed ? 1 : 0);
