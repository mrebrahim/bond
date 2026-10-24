-- =====================================================================
-- Digital Solution Egypt — نظام العملاء والباقات والاستوديو
-- Migration 001: الجداول + قواعد البيزنس + التنبيهات + الـ KPIs
-- Postgres 15+ / Supabase
-- =====================================================================

create extension if not exists btree_gist;
create extension if not exists pgcrypto;

-- ---------------------------------------------------------------------
-- 0) أدوات مساعدة
-- ---------------------------------------------------------------------
-- كل التواريخ بتوقيت القاهرة (Supabase شغال UTC)
create or replace function cairo_today() returns date
language sql stable as $$ select (now() at time zone 'Africa/Cairo')::date $$;

-- ---------------------------------------------------------------------
-- 1) الإعدادات (كلها بتتعدل من لوحة التحكم)
-- ---------------------------------------------------------------------
create table settings (
  key text primary key,
  value jsonb not null,
  description_ar text
);

insert into settings (key, value, description_ar) values
  ('minutes_per_reel',          '20',   'متوسط دقائق تصوير الريل (للتقارير)'),
  ('minutes_per_reel_max',      '25',   'أقصى مدة للريل — بيتحسب بيها طول الحجز علشان الجلسات متدخلش في بعض'),
  ('setup_minutes',             '15',   'وقت التجهيز بين كل جلسة والتانية'),
  ('editing_days',              '2',    'أيام المونتاج من التصوير لحد ما الريل يبقى جاهز'),
  ('reels_per_outfit',          '3',    'لبس جديد كل كام ريل'),
  ('reels_per_location',        '3',    'لوكيشن جديد كل كام ريل'),
  ('min_outfits_multi',         '2',    'أقل عدد لبسات لو بيصور أكتر من ريل'),
  ('hours_rounding_minutes',    '15',   'تقريب ساعات تصوير المدربين لأقرب كام دقيقة'),
  ('min_session_hours',         '1',    'أقل مدة تتحسب لجلسة الساعات'),
  ('late_cancel_hours',         '24',   'الإلغاء قبل الميعاد بأقل من كده = إلغاء متأخر بيتخصم'),
  ('no_show_penalty_reels',     '1',    'خصم الغياب أو الإلغاء المتأخر (ريلز)'),
  ('no_show_penalty_hours',     '1',    'خصم الغياب أو الإلغاء المتأخر (ساعات)'),
  ('activation_paid_pct',       '100',  'نسبة الدفع المطلوبة لتفعيل الباقة'),
  ('renewal_lead_days',         '3',    'فاتورة التجديد بتطلع قبل نهاية الباقة بكام يوم'),
  ('usage_high_pct',            '80',   'تنبيه الاستهلاك العالي'),
  ('hours_low_pct',             '20',   'تنبيه السيلز لما ساعات المدربين توصل للنسبة دي'),
  ('low_usage_mid_pct',         '25',   'نص الدورة عدّى والاستهلاك تحت النسبة دي = خطر churn'),
  ('payment_overdue_days',      '3',    'تذكير الدفع بعد الاستحقاق بكام يوم'),
  ('runway_alert_days',         '7',    'نبّه لو أول ميعاد نشر فاضي جاي خلال الأيام دي'),
  ('low_feedback_score',        '3',    'أي تقييم أقل من أو يساوي الرقم ده = تنبيه'),
  ('support_wa',                '""',   'رقم واتساب الدعم الفني (بصيغة 20XXXXXXXXXX)');

create or replace function setting_num(k text) returns numeric
language sql stable as $$ select (value #>> '{}')::numeric from settings where key = k $$;

create or replace function setting_text(k text) returns text
language sql stable as $$ select value #>> '{}' from settings where key = k $$;

-- ---------------------------------------------------------------------
-- 2) الفريق
-- ---------------------------------------------------------------------
create table staff (
  id uuid primary key references auth.users(id) on delete cascade,
  name text not null,
  phone_wa text,
  roles text[] not null check (cardinality(roles) > 0 and roles <@ array[
    'admin','account_manager','sales','support','finance',
    'studio','editor','designer','social_media','scriptwriter','ads','media_lead']::text[]),
  active boolean not null default true,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- 3) العملاء + بروفايل المحتوى (أسئلة الـ onboarding)
-- ---------------------------------------------------------------------
create table clients (
  id uuid primary key default gen_random_uuid(),
  code text unique,                         -- بيتحط في لينك التقييم
  name text not null,
  business_name text,
  phone_wa text not null,
  contact_person text,
  industry text,
  account_manager_id uuid references staff(id),
  status text not null default 'active' check (status in ('lead','active','paused','churned')),
  acquired_at date not null default cairo_today(),
  churned_at date,
  churn_reason text,
  -- بروفايل المحتوى
  platforms text[] not null default '{}',   -- {facebook,instagram,tiktok,youtube}
  buffer_weeks numeric(3,1) not null default 1,
  shoot_frequency text check (shoot_frequency in ('weekly','biweekly','monthly','on_demand')),
  preferred_shoot_day int check (preferred_shoot_day between 0 and 6),   -- 0 = الحد
  reels_per_session_avg int,
  needs_approval boolean not null default true,
  approval_sla_hours int not null default 48,
  caption_tone text,
  brand_assets_url text,
  notes text,
  perfex_client_id int,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- 4) أنواع الخدمات
-- ---------------------------------------------------------------------
create table deliverable_types (
  code text primary key,
  name_ar text not null,
  unit_kind text not null check (unit_kind in ('count','hours','flat')),
  consume_at_stage text,          -- المرحلة اللي بيتخصم عندها (الريل والساعات بيتخصموا من الجلسة)
  publishable boolean not null default false,
  stages text[] not null default '{}',
  sort int not null default 0
);

insert into deliverable_types (code, name_ar, unit_kind, consume_at_stage, publishable, stages, sort) values
  ('reel',              'ريل',                   'count', null,     true,  '{filmed,editing,review,approved,published}', 1),
  ('design',            'تصميم',                 'count', 'review', true,  '{brief,designing,review,approved,published}', 2),
  ('trainer_hours',     'ساعات تصوير المدربين',  'hours', null,     false, '{}', 3),
  ('trainer_video_edit','مونتاج فيديو مدرب',      'count', 'review', false, '{filmed,editing,review,approved,delivered}', 4),
  ('content_mgmt',      'إدارة محتوى',           'flat',  null,     false, '{}', 5),
  ('ads_mgmt',          'إدارة إعلانات',         'flat',  null,     false, '{}', 6),
  ('gmb',               'Google Maps / Business','flat',  null,     false, '{}', 7),
  ('website',           'موقع',                  'flat',  null,     false, '{}', 8),
  ('wa_bot',            'رد آلي على الواتساب',    'flat',  null,     false, '{}', 9);

-- ---------------------------------------------------------------------
-- 5) قوالب الباقات (اختيارية — للتسريع بس)
-- ---------------------------------------------------------------------
create table plans (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  base_price numeric(12,2) not null,
  validity_days int not null default 30,
  tier int,
  active boolean not null default true
);

create table plan_items (
  plan_id uuid references plans(id) on delete cascade,
  deliverable_type text references deliverable_types(code),
  quantity numeric(8,2) not null,
  primary key (plan_id, deliverable_type)
);

-- ---------------------------------------------------------------------
-- 6) الاشتراك = الاتفاق مع العميل (بنوده وسعره ومدته خاصين بيه)
-- ---------------------------------------------------------------------
create table subscriptions (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references clients(id),
  plan_id uuid references plans(id),         -- القالب اللي اتبنى منه لو فيه
  name text not null,
  price numeric(12,2) not null,
  validity_days int not null default 30 check (validity_days > 0),
  overage_rate numeric(10,2),                -- سعر الساعة الزيادة (باقات المدربين)
  auto_renew boolean not null default true,
  status text not null default 'active' check (status in ('active','paused','cancelled')),
  started_at date not null,
  cancelled_at date,
  created_at timestamptz not null default now()
);

create table subscription_items (
  subscription_id uuid references subscriptions(id) on delete cascade,
  deliverable_type text references deliverable_types(code),
  quantity numeric(8,2) not null check (quantity >= 0),
  primary key (subscription_id, deliverable_type)
);

-- ---------------------------------------------------------------------
-- 7) الدورات + الأرصدة (قلب النظام)
-- ---------------------------------------------------------------------
create table cycles (
  id uuid primary key default gen_random_uuid(),
  subscription_id uuid not null references subscriptions(id),
  client_id uuid not null references clients(id),
  cycle_no int not null,
  start_date date not null,
  end_date date not null,
  price numeric(12,2) not null,
  monthly_value numeric(12,2) generated always as (round(price * 30.0 / (end_date - start_date + 1), 2)) stored,
  status text not null default 'pending_payment'
    check (status in ('pending_payment','active','expired','cancelled')),
  activated_at timestamptz,
  closed_at timestamptz,
  created_at timestamptz not null default now(),
  unique (subscription_id, cycle_no),
  check (end_date >= start_date),
  constraint no_cycle_overlap exclude using gist
    (subscription_id with =, daterange(start_date, end_date, '[]') with &&)
    where (status <> 'cancelled')
);

create table cycle_entitlements (
  cycle_id uuid references cycles(id) on delete cascade,
  deliverable_type text references deliverable_types(code),
  granted numeric(8,2) not null default 0,
  bonus numeric(8,2) not null default 0,
  used numeric(8,2) not null default 0,
  forfeited numeric(8,2) not null default 0,
  primary key (cycle_id, deliverable_type)
);

-- ---------------------------------------------------------------------
-- 8) الاستوديو والسكربتات والجلسات
-- ---------------------------------------------------------------------
create table studios (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  active boolean not null default true
);

create table scripts (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references clients(id),
  title text not null,
  body text,
  status text not null default 'draft'
    check (status in ('draft','internal_review','sent_to_client','changes_requested','approved')),
  revision_count int not null default 0,
  sent_at timestamptz,
  approved_at timestamptz,
  approved_via text,                 -- whatsapp / in_person
  created_by uuid references staff(id),
  created_at timestamptz not null default now()
);

create table studio_sessions (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references clients(id),
  cycle_id uuid references cycles(id),
  deliverable_type text not null default 'reel' references deliverable_types(code),  -- reel أو trainer_hours
  studio_id uuid not null references studios(id),
  photographer_id uuid references staff(id),
  booked_start timestamptz not null,
  booked_end timestamptz,
  planned_qty numeric(6,2) not null check (planned_qty > 0),   -- عدد ريلز أو ساعات
  outfits_required int,
  locations_required int,
  status text not null default 'booked'
    check (status in ('booked','checked_in','done','no_show','cancelled')),
  confirmation text not null default 'pending'
    check (confirmation in ('pending','confirmed','reschedule_requested')),
  checked_in_at timestamptz,
  actual_start timestamptz,
  actual_end timestamptz,
  actual_qty numeric(6,2),
  cancelled_at timestamptz,
  late_cancel boolean not null default false,
  notes text,
  created_by uuid references staff(id),
  created_at timestamptz not null default now(),
  slot tstzrange generated always as (tstzrange(booked_start, booked_end)) stored
);

-- مفيش تعارض: نفس الاستوديو أو نفس المصور مينفعش يتحجز في نفس الوقت
alter table studio_sessions add constraint no_studio_overlap
  exclude using gist (studio_id with =, slot with &&) where (status not in ('cancelled','no_show'));
alter table studio_sessions add constraint no_photographer_overlap
  exclude using gist (photographer_id with =, slot with &&) where (status not in ('cancelled','no_show'));

-- ---------------------------------------------------------------------
-- 9) الـ deliverables ومراحلها
-- ---------------------------------------------------------------------
create table deliverables (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references clients(id),
  cycle_id uuid not null references cycles(id),
  session_id uuid references studio_sessions(id),
  script_id uuid references scripts(id),
  deliverable_type text not null references deliverable_types(code),
  title text,
  stage text not null,
  assignee_id uuid references staff(id),          -- الإيديتور أو الديزاينر
  due_at timestamptz,
  approved_at timestamptz,
  published_at timestamptz,
  platforms text[],
  post_url text,
  revision_count int not null default 0,
  stage_changed_at timestamptz not null default now(),
  updated_by uuid references staff(id),
  created_at timestamptz not null default now()
);

create table deliverable_stage_history (
  id bigserial primary key,
  deliverable_id uuid not null references deliverables(id) on delete cascade,
  from_stage text,
  to_stage text not null,
  changed_by uuid references staff(id),
  changed_at timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- 10) سجل الاستهلاك (append-only = مصدر الحقيقة)
-- ---------------------------------------------------------------------
create table consumption_log (
  id bigserial primary key,
  cycle_id uuid not null references cycles(id),
  deliverable_type text not null references deliverable_types(code),
  qty numeric(8,2) not null check (qty <> 0),
  reason text not null check (reason in
    ('consumed','no_show','late_cancel','overage','swap_out','swap_in','reversal','manual_adjust')),
  deliverable_id uuid references deliverables(id),
  session_id uuid references studio_sessions(id),
  note text,
  created_by uuid references staff(id),
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- 11) الفلوس
-- ---------------------------------------------------------------------
create table invoices (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references clients(id),
  cycle_id uuid references cycles(id),
  kind text not null check (kind in ('subscription','topup','overage','one_off','proration')),
  description text,
  amount numeric(12,2) not null check (amount >= 0),
  paid_amount numeric(12,2) not null default 0,
  due_date date not null,
  status text not null default 'unpaid' check (status in ('unpaid','partial','paid','void')),
  perfex_invoice_id int,
  created_at timestamptz not null default now()
);

create table payments (
  id uuid primary key default gen_random_uuid(),
  invoice_id uuid not null references invoices(id),
  client_id uuid references clients(id),
  amount numeric(12,2) not null check (amount > 0),
  method text not null check (method in ('cash','instapay','vodafone_cash','bank_transfer','card','other')),
  paid_at timestamptz not null default now(),
  reference text,
  received_by uuid references staff(id)
);

-- ---------------------------------------------------------------------
-- 12) أحداث الاشتراك (upgrade / downgrade / offer / cancel ...)
-- ---------------------------------------------------------------------
create table subscription_events (
  id bigserial primary key,
  subscription_id uuid not null references subscriptions(id),
  client_id uuid not null references clients(id),
  event_type text not null check (event_type in
    ('new','renewal','upgrade','downgrade','topup','pause','resume','cancel','reactivate','special_offer')),
  mrr_delta numeric(12,2) not null default 0,
  effective_date date not null,
  details jsonb,
  note text,
  created_by uuid references staff(id),
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- 13) جدول النشر وبنك المحتوى
-- ---------------------------------------------------------------------
create table publishing_schedule (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references clients(id) on delete cascade,
  deliverable_type text not null references deliverable_types(code),
  weekday int not null check (weekday between 0 and 6),     -- 0 = الحد
  publish_time time,
  platforms text[],
  active boolean not null default true,
  unique (client_id, deliverable_type, weekday)
);

create table content_slots (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references clients(id) on delete cascade,
  deliverable_type text not null references deliverable_types(code),
  slot_date date not null,
  publish_time time,
  deliverable_id uuid references deliverables(id),
  status text not null default 'empty'
    check (status in ('empty','reserved','published','missed_no_content','missed_internal')),
  unique (client_id, deliverable_type, slot_date)
);

-- ---------------------------------------------------------------------
-- 14) المانوال (Checklists)
-- ---------------------------------------------------------------------
create table checklist_templates (
  id uuid primary key default gen_random_uuid(),
  stage text not null check (stage in
    ('onboarding','script','pre_shoot','shoot','post_shoot','editing','design','publishing')),
  applies_to text[],            -- null = كل الأنواع
  name text not null,
  active boolean not null default true
);

create table checklist_template_items (
  id uuid primary key default gen_random_uuid(),
  template_id uuid not null references checklist_templates(id) on delete cascade,
  sort int not null default 0,
  text_ar text not null,
  is_required boolean not null default false,
  client_facing boolean not null default false,
  role text
);

create table checklist_runs (
  id uuid primary key default gen_random_uuid(),
  item_id uuid not null references checklist_template_items(id),
  session_id uuid references studio_sessions(id),
  deliverable_id uuid references deliverables(id),
  done boolean not null default true,
  done_by uuid references staff(id),
  client_confirmed_at timestamptz,
  note text,
  done_at timestamptz not null default now(),
  check (session_id is not null or deliverable_id is not null)
);
create unique index checklist_runs_session_uq on checklist_runs(item_id, session_id) where session_id is not null;
create unique index checklist_runs_deliv_uq on checklist_runs(item_id, deliverable_id) where deliverable_id is not null;

-- ---------------------------------------------------------------------
-- 15) تقييمات العملاء (من Google Form عن طريق n8n)
-- ---------------------------------------------------------------------
create table feedback (
  id uuid primary key default gen_random_uuid(),
  client_id uuid references clients(id),
  deliverable_id uuid references deliverables(id),
  session_id uuid references studio_sessions(id),
  filming_score int check (filming_score between 1 and 5),
  editing_score int check (editing_score between 1 and 5),
  wants_changes boolean not null default false,
  issues text[],
  comment text,
  revision_requested boolean not null default false,
  source_ref text unique,          -- رقم الصف في شيت الردود علشان ميتسجلش مرتين
  submitted_at timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- 16) التنبيهات (n8n بيقرا منها ويبعت)
-- ---------------------------------------------------------------------
create table alerts (
  id bigserial primary key,
  alert_key text not null,
  dedupe_key text not null,
  audience text not null check (audience in
    ('client','account_manager','sales','support','admin','finance','media_lead','staff')),
  client_id uuid references clients(id),
  cycle_id uuid references cycles(id),
  session_id uuid references studio_sessions(id),
  deliverable_id uuid references deliverables(id),
  recipient_staff_id uuid references staff(id),
  payload jsonb not null default '{}',
  status text not null default 'queued' check (status in ('queued','sent','failed','skipped')),
  priority text not null default 'normal' check (priority in ('normal','urgent')),
  handled_at timestamptz,               -- اتكلم مع العميل / اتحلت
  handled_by uuid references staff(id),
  resolution text,
  error text,
  created_at timestamptz not null default now(),
  sent_at timestamptz,
  unique (dedupe_key, audience)
);

create or replace function queue_alert(
  p_key text, p_dedupe text, p_audiences text[], p_client uuid,
  p_payload jsonb default '{}', p_cycle uuid default null, p_session uuid default null,
  p_deliverable uuid default null, p_staff uuid default null, p_priority text default 'normal'
) returns int language plpgsql security definer set search_path = public as $$
declare a text; n int := 0; c int;
begin
  foreach a in array p_audiences loop
    insert into alerts (alert_key, dedupe_key, audience, client_id, cycle_id, session_id,
                        deliverable_id, recipient_staff_id, payload, priority)
    values (p_key, p_dedupe, a, p_client, p_cycle, p_session, p_deliverable,
            case when a = 'staff' then p_staff end, p_payload, p_priority)
    on conflict (dedupe_key, audience) do nothing;
    get diagnostics c = row_count; n := n + c;
  end loop;
  return n;
end $$;

-- =====================================================================
-- قواعد البيزنس (Triggers)
-- =====================================================================

-- ---- الاستهلاك: بيحدّث الرصيد وبيمنع السالب ----
create or replace function trg_consumption_apply() returns trigger
language plpgsql security definer set search_path = public as $$
declare v_status text; e cycle_entitlements;
begin
  select status into v_status from cycles where id = new.cycle_id;
  if v_status <> 'active' and new.reason not in ('reversal','manual_adjust') then
    raise exception 'الباقة مش نشطة (الحالة: %)', v_status;
  end if;
  if new.reason = 'overage' then return new; end if;   -- الزيادة بتتسجل وبتتفوتر بس، مش بتلمس الرصيد

  update cycle_entitlements set used = used + new.qty
   where cycle_id = new.cycle_id and deliverable_type = new.deliverable_type
   returning * into e;
  if not found then
    raise exception 'الخدمة "%" مش ضمن الباقة دي', new.deliverable_type;
  end if;
  if e.used > e.granted + e.bonus and new.reason <> 'manual_adjust' then
    raise exception 'الرصيد مش كفاية: هيبقى المستخدم % من %', e.used, e.granted + e.bonus;
  end if;
  if e.used < 0 then raise exception 'الرصيد المستخدم مينفعش يبقى بالسالب'; end if;
  return new;
end $$;

create trigger consumption_apply after insert on consumption_log
  for each row execute function trg_consumption_apply();

create or replace function trg_block_mutation() returns trigger language plpgsql as $$
begin
  raise exception 'السجل ده append-only — للتصحيح سجّل حركة reversal';
end $$;

create trigger consumption_no_update before update or delete on consumption_log
  for each row execute function trg_block_mutation();

-- ---- المدفوعات: بتحدّث الفاتورة وبتفعّل الباقة ----
create or replace function trg_payment_before() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  select client_id into new.client_id from invoices where id = new.invoice_id;
  return new;
end $$;

create or replace function trg_payment_apply() returns trigger
language plpgsql security definer set search_path = public as $$
declare inv invoices; v_paid numeric;
begin
  select * into inv from invoices where id = new.invoice_id;
  select coalesce(sum(amount), 0) into v_paid from payments where invoice_id = inv.id;
  update invoices
     set paid_amount = v_paid,
         status = case when v_paid >= inv.amount then 'paid' when v_paid > 0 then 'partial' else 'unpaid' end
   where id = inv.id;
  if inv.kind = 'subscription' and inv.cycle_id is not null
     and v_paid >= inv.amount * setting_num('activation_paid_pct') / 100 then
    update cycles set status = 'active', activated_at = now()
     where id = inv.cycle_id and status = 'pending_payment';
  end if;
  return new;
end $$;

create trigger payment_before before insert on payments for each row execute function trg_payment_before();
create trigger payment_apply after insert on payments for each row execute function trg_payment_apply();

-- ---- المانوال: البنود الإجبارية اللي لسه متعملتش ----
create or replace function checklist_missing(p_stages text[], p_type text, p_session uuid, p_deliverable uuid)
returns text[] language sql stable as $$
  select coalesce(array_agg(i.text_ar order by t.stage, i.sort), '{}')
  from checklist_templates t
  join checklist_template_items i on i.template_id = t.id
  where t.active and i.is_required
    and t.stage = any(p_stages)
    and (t.applies_to is null or p_type = any(t.applies_to))
    and not exists (
      select 1 from checklist_runs r
      where r.item_id = i.id and r.done
        and ((p_session is not null and r.session_id = p_session)
          or (p_deliverable is not null and r.deliverable_id = p_deliverable)))
$$;

-- ---- الحجز: الرصيد + التفعيل + المدة + اللبس واللوكيشن ----
create or replace function trg_session_before() returns trigger
language plpgsql security definer set search_path = public as $$
declare v_cycle cycles; v_unit text; v_avail numeric; v_reserved numeric; v_minutes numeric; v_day date;
begin
  if tg_op = 'UPDATE' then
    -- تغييرات الحالة (وصل/خلص/غاب/اتلغى) مش محتاجة إعادة فحص
    if new.booked_start is not distinct from old.booked_start
       and new.planned_qty is not distinct from old.planned_qty
       and new.booked_end is not distinct from old.booked_end then
      return new;
    end if;
    if old.status <> 'booked' then raise exception 'مينفعش تعدل ميعاد جلسة حالتها %', old.status; end if;
    if new.booked_start is distinct from old.booked_start and new.booked_end is not distinct from old.booked_end then
      new.booked_end := null;   -- يتحسب تاني
    end if;
  end if;

  select unit_kind into v_unit from deliverable_types where code = new.deliverable_type;
  if v_unit not in ('count','hours') then raise exception 'النوع "%" مش بيتحجز له استوديو', new.deliverable_type; end if;

  v_day := (new.booked_start at time zone 'Africa/Cairo')::date;
  if new.cycle_id is null then
    select c.* into v_cycle from cycles c
      join cycle_entitlements e on e.cycle_id = c.id and e.deliverable_type = new.deliverable_type
     where c.client_id = new.client_id and c.status in ('active','pending_payment')
       and v_day between c.start_date and c.end_date
     order by (c.status = 'active') desc limit 1;
    new.cycle_id := v_cycle.id;
  else
    select * into v_cycle from cycles where id = new.cycle_id;
  end if;

  if v_cycle.id is null then raise exception 'مفيش باقة للعميل فيها "%" تغطي يوم %', new.deliverable_type, v_day; end if;
  if v_cycle.status <> 'active' then raise exception 'الباقة لسه متفعلتش — لازم الدفع الأول'; end if;
  if v_day not between v_cycle.start_date and v_cycle.end_date then
    raise exception 'يوم الحجز % برّه مدة الباقة (% → %)', v_day, v_cycle.start_date, v_cycle.end_date;
  end if;

  select greatest(granted + bonus - used, 0) into v_avail
    from cycle_entitlements where cycle_id = v_cycle.id and deliverable_type = new.deliverable_type;
  select coalesce(sum(planned_qty), 0) into v_reserved
    from studio_sessions
   where cycle_id = v_cycle.id and deliverable_type = new.deliverable_type
     and status in ('booked','checked_in') and id <> new.id;

  if new.planned_qty > v_avail - v_reserved then
    if not (v_unit = 'hours' and (select overage_rate from subscriptions where id = v_cycle.subscription_id) is not null) then
      raise exception 'الرصيد مش كفاية: المتاح % (بعد الحجوزات التانية)، والمطلوب %',
        v_avail - v_reserved, new.planned_qty;
    end if;
  end if;

  if new.booked_end is null then
    v_minutes := case when v_unit = 'count'
                      then new.planned_qty * setting_num('minutes_per_reel_max') + setting_num('setup_minutes')
                      else new.planned_qty * 60 + setting_num('setup_minutes') end;
    new.booked_end := new.booked_start + make_interval(mins => ceil(v_minutes)::int);
  end if;

  if v_unit = 'count' then
    new.outfits_required := case when new.planned_qty <= 1 then 1
      else greatest(setting_num('min_outfits_multi'), ceil(new.planned_qty / setting_num('reels_per_outfit')))::int end;
    new.locations_required := case when new.planned_qty <= 1 then 1
      else greatest(2, ceil(new.planned_qty / setting_num('reels_per_location')))::int end;
  end if;
  return new;
end $$;

create trigger session_before before insert or update on studio_sessions
  for each row execute function trg_session_before();

-- ---- مراحل الـ deliverable: المانوال بيقفل المرحلة + الخصم + التاريخ ----
create or replace function trg_deliverable_before() returns trigger
language plpgsql security definer set search_path = public as $$
declare v_missing text[]; v_stages text[];
begin
  if tg_op = 'INSERT' then
    if new.stage is null then
      new.stage := (select stages[1] from deliverable_types where code = new.deliverable_type);
    end if;
    return new;
  end if;
  if new.stage is not distinct from old.stage then return new; end if;

  v_stages := case new.stage
    when 'approved'  then array['editing','design']
    when 'delivered' then array['editing']
    when 'published' then array['publishing']
    else null end;
  if v_stages is not null then
    v_missing := checklist_missing(v_stages, new.deliverable_type, null, new.id);
    if cardinality(v_missing) > 0 then
      raise exception 'لازم تخلص البنود الإجبارية الأول: %', array_to_string(v_missing, ' | ');
    end if;
  end if;

  if new.stage = 'approved' then new.approved_at := coalesce(new.approved_at, now()); end if;
  if new.stage = 'published' then
    if new.post_url is null then raise exception 'حط لينك البوست قبل ما تعلّم إنه اتنشر'; end if;
    new.published_at := coalesce(new.published_at, now());
  end if;
  if old.stage in ('review','approved','published') and new.stage in ('editing','designing') then
    new.revision_count := old.revision_count + 1;
  end if;
  new.stage_changed_at := now();
  return new;
end $$;

create or replace function trg_deliverable_after() returns trigger
language plpgsql security definer set search_path = public as $$
declare v_consume text;
begin
  if tg_op = 'UPDATE' and new.stage is not distinct from old.stage then return new; end if;

  insert into deliverable_stage_history (deliverable_id, from_stage, to_stage, changed_by)
  values (new.id, case when tg_op = 'UPDATE' then old.stage end, new.stage, new.updated_by);

  select consume_at_stage into v_consume from deliverable_types where code = new.deliverable_type;
  if v_consume = new.stage and not exists (
      select 1 from consumption_log where deliverable_id = new.id and reason = 'consumed') then
    insert into consumption_log (cycle_id, deliverable_type, qty, reason, deliverable_id, created_by)
    values (new.cycle_id, new.deliverable_type, 1, 'consumed', new.id, new.updated_by);
  end if;

  if new.stage = 'published' then
    -- اربطه بميعاد النشر بتاعه، أو بميعاد النهارده الفاضي
    update content_slots set status = 'published', deliverable_id = new.id
     where id = coalesce(
       (select id from content_slots where deliverable_id = new.id limit 1),
       (select id from content_slots where client_id = new.client_id and deliverable_type = new.deliverable_type
          and slot_date = cairo_today() and status = 'empty' limit 1));
    perform queue_alert('published', 'published:' || new.id, array['client'], new.client_id,
      jsonb_build_object('post_url', new.post_url, 'deliverable_id', new.id, 'type', new.deliverable_type,
                         'client_code', (select code from clients where id = new.client_id)),
      new.cycle_id, null, new.id);
  end if;
  return new;
end $$;

create trigger deliverable_before before insert or update on deliverables
  for each row execute function trg_deliverable_before();
create trigger deliverable_after after insert or update on deliverables
  for each row execute function trg_deliverable_after();

-- ---- التقييمات: تنبيه فوري لو التقييم وحش ----
create or replace function trg_feedback_before() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.client_id is null and new.deliverable_id is not null then
    select client_id, session_id into new.client_id, new.session_id
      from deliverables where id = new.deliverable_id;
  end if;
  return new;
end $$;

create or replace function trg_feedback_after() returns trigger
language plpgsql security definer set search_path = public as $$
declare d deliverables; v_photographer uuid; v_payload jsonb;
begin
  select * into d from deliverables where id = new.deliverable_id;
  select photographer_id into v_photographer from studio_sessions where id = coalesce(new.session_id, d.session_id);
  v_payload := jsonb_build_object('filming', new.filming_score, 'editing', new.editing_score,
                                  'location', new.location_score,
                                  'issues', new.issues, 'comment', new.comment, 'post_url', d.post_url,
                                  'client_name', (select name from clients where id = new.client_id),
                                  'client_phone', (select phone_wa from clients where id = new.client_id),
                                  'photographer', (select name from staff where id = v_photographer),
                                  'editor', (select name from staff where id = d.assignee_id));

  -- العميل متضايق (تقييم قليل أو كتب إن فيه حاجة مضايقاه) → مريم تكلمه فورًا
  if least(coalesce(new.filming_score, 5), coalesce(new.editing_score, 5)) <= setting_num('low_feedback_score')
     or new.wants_changes or new.revision_requested then
    perform queue_alert('client_unhappy', 'unhappy:' || new.id, array['sales','account_manager','media_lead'],
                        new.client_id, v_payload, d.cycle_id, null, d.id, null, 'urgent');
  end if;

  if least(coalesce(new.filming_score, 5), coalesce(new.editing_score, 5)) <= setting_num('low_feedback_score') then
    if coalesce(new.editing_score, 5) <= setting_num('low_feedback_score') and d.assignee_id is not null then
      perform queue_alert('feedback_low', 'feedback_low:' || new.id || ':editor', array['staff'],
                          new.client_id, v_payload, d.cycle_id, null, d.id, d.assignee_id);
    end if;
    if coalesce(new.filming_score, 5) <= setting_num('low_feedback_score') and v_photographer is not null then
      perform queue_alert('feedback_low', 'feedback_low:' || new.id || ':photographer', array['staff'],
                          new.client_id, v_payload, d.cycle_id, null, d.id, v_photographer);
    end if;
  end if;

  if new.revision_requested then
    perform queue_alert('revision_requested', 'revision:' || new.id, array['account_manager'],
                        new.client_id, v_payload, d.cycle_id, null, d.id);
  end if;
  return new;
end $$;

create trigger feedback_before before insert on feedback for each row execute function trg_feedback_before();
create trigger feedback_after after insert on feedback for each row execute function trg_feedback_after();

-- =====================================================================
-- العمليات (الواجهة و n8n بينادوا دول)
-- =====================================================================

-- ---- الصلاحيات: مين يقدر ينادي كل عملية ----
-- n8n و pg_cron (اتصال مباشر أو service_role) مسموح لهم دايمًا. الـ admin مسموح له كل حاجة.
create or replace function require_role(p_roles text[]) returns void
language plpgsql stable security definer set search_path = public as $$
begin
  if auth.uid() is null then
    if auth.role() is null or auth.role() = 'service_role' then return; end if;
    raise exception 'غير مصرح';
  end if;
  if not exists (select 1 from staff where id = auth.uid() and active
                   and ('admin' = any(roles) or roles && p_roles)) then
    raise exception 'العملية دي مش متاحة لدورك';
  end if;
end $$;

-- ---- إنشاء دورة جديدة لاشتراك ----
create or replace function create_cycle(p_sub uuid, p_start date) returns uuid
language plpgsql security definer set search_path = public as $$
declare s subscriptions; v_id uuid; v_no int;
begin
  select * into s from subscriptions where id = p_sub;
  select coalesce(max(cycle_no), 0) + 1 into v_no from cycles where subscription_id = p_sub;

  insert into cycles (subscription_id, client_id, cycle_no, start_date, end_date, price)
  values (p_sub, s.client_id, v_no, p_start, p_start + s.validity_days - 1, s.price)
  returning id into v_id;

  insert into cycle_entitlements (cycle_id, deliverable_type, granted)
  select v_id, deliverable_type, quantity from subscription_items where subscription_id = p_sub;

  insert into invoices (client_id, cycle_id, kind, description, amount, due_date)
  values (s.client_id, v_id, 'subscription', s.name || ' — دورة ' || v_no, s.price, p_start);
  return v_id;
end $$;

-- ---- اشتراك جديد (باقة مفصّلة أو من قالب) ----
-- p_items: [{"type":"reel","qty":10},{"type":"design","qty":10},{"type":"content_mgmt","qty":1}]
create or replace function create_subscription(
  p_client uuid, p_name text, p_price numeric, p_items jsonb,
  p_start date default null, p_validity_days int default 30,
  p_overage_rate numeric default null, p_plan uuid default null, p_by uuid default null
) returns uuid language plpgsql security definer set search_path = public as $$
declare v_sub uuid; v_start date := coalesce(p_start, cairo_today()); v_was_churned boolean;
begin
  perform require_role(array['account_manager','sales','finance']);
  select status = 'churned' into v_was_churned from clients where id = p_client;

  insert into subscriptions (client_id, plan_id, name, price, validity_days, overage_rate, started_at)
  values (p_client, p_plan, p_name, p_price, p_validity_days, p_overage_rate, v_start)
  returning id into v_sub;

  insert into subscription_items (subscription_id, deliverable_type, quantity)
  select v_sub, x.type, x.qty from jsonb_to_recordset(p_items) as x(type text, qty numeric);

  perform create_cycle(v_sub, v_start);

  insert into subscription_events (subscription_id, client_id, event_type, mrr_delta, effective_date, details, created_by)
  values (v_sub, p_client, case when v_was_churned then 'reactivate' else 'new' end,
          round(p_price * 30.0 / p_validity_days, 2), v_start, p_items, p_by);

  update clients set status = 'active', churned_at = null where id = p_client and status <> 'active';
  return v_sub;
end $$;

-- ---- top-up فوري (وحدات زيادة في الدورة الحالية) ----
create or replace function add_topup(p_cycle uuid, p_type text, p_qty numeric, p_price numeric, p_by uuid default null)
returns uuid language plpgsql security definer set search_path = public as $$
declare c cycles; v_inv uuid;
begin
  perform require_role(array['account_manager','sales']);
  select * into c from cycles where id = p_cycle;
  if c.status <> 'active' then raise exception 'الدورة مش نشطة'; end if;
  insert into cycle_entitlements (cycle_id, deliverable_type, bonus) values (p_cycle, p_type, p_qty)
  on conflict (cycle_id, deliverable_type) do update set bonus = cycle_entitlements.bonus + excluded.bonus;
  insert into invoices (client_id, cycle_id, kind, description, amount, due_date)
  values (c.client_id, p_cycle, 'topup', 'Top-up: ' || p_qty || ' ' || p_type, p_price, cairo_today())
  returning id into v_inv;
  insert into subscription_events (subscription_id, client_id, event_type, effective_date, details, created_by)
  values (c.subscription_id, c.client_id, 'topup', cairo_today(),
          jsonb_build_object('type', p_type, 'qty', p_qty, 'price', p_price), p_by);
  return v_inv;
end $$;

-- ---- upgrade / downgrade / عرض خاص (بيبدأ من الدورة الجاية) ----
create or replace function change_subscription(
  p_sub uuid, p_event text, p_new_price numeric, p_new_items jsonb default null,
  p_new_validity int default null, p_note text default null, p_by uuid default null
) returns void language plpgsql security definer set search_path = public as $$
declare s subscriptions; v_old_mv numeric; v_new_mv numeric; v_next date; v_pending cycles;
begin
  perform require_role(array['account_manager','sales']);
  if p_event not in ('upgrade','downgrade','special_offer') then raise exception 'نوع تغيير غير معروف'; end if;
  select * into s from subscriptions where id = p_sub for update;
  v_old_mv := s.price * 30.0 / s.validity_days;

  update subscriptions set price = p_new_price, validity_days = coalesce(p_new_validity, validity_days)
   where id = p_sub returning * into s;
  v_new_mv := s.price * 30.0 / s.validity_days;

  if p_new_items is not null then
    delete from subscription_items where subscription_id = p_sub;
    insert into subscription_items (subscription_id, deliverable_type, quantity)
    select p_sub, x.type, x.qty from jsonb_to_recordset(p_new_items) as x(type text, qty numeric);
  end if;

  -- لو دورة التجديد اتعملت ولسه متدفعتش، حدّثها بالباقة الجديدة
  select * into v_pending from cycles
   where subscription_id = p_sub and status = 'pending_payment' and start_date > cairo_today()
   order by start_date limit 1;
  if v_pending.id is not null then
    delete from cycle_entitlements where cycle_id = v_pending.id;
    insert into cycle_entitlements (cycle_id, deliverable_type, granted)
    select v_pending.id, deliverable_type, quantity from subscription_items where subscription_id = p_sub;
    update cycles set price = s.price, end_date = start_date + s.validity_days - 1 where id = v_pending.id;
    update invoices set amount = s.price where cycle_id = v_pending.id and kind = 'subscription' and status = 'unpaid';
  end if;

  select coalesce(max(end_date) filter (where status in ('active','expired')), cairo_today()) + 1
    into v_next from cycles where subscription_id = p_sub;

  insert into subscription_events (subscription_id, client_id, event_type, mrr_delta, effective_date, details, note, created_by)
  values (p_sub, s.client_id, p_event, round(v_new_mv - v_old_mv, 2), v_next,
          jsonb_build_object('price', p_new_price, 'items', p_new_items), p_note, p_by);
end $$;

-- ---- إلغاء (العميل مش هيجدد) ----
create or replace function cancel_subscription(p_sub uuid, p_reason text default null, p_by uuid default null)
returns void language plpgsql security definer set search_path = public as $$
declare s subscriptions; v_last date;
begin
  perform require_role(array['account_manager']);
  update subscriptions set auto_renew = false where id = p_sub returning * into s;
  -- الدورات اللي لسه متدفعتش بتتلغي
  update invoices set status = 'void'
   where cycle_id in (select id from cycles where subscription_id = p_sub and status = 'pending_payment')
     and status = 'unpaid';
  update cycles set status = 'cancelled' where subscription_id = p_sub and status = 'pending_payment';
  select max(end_date) into v_last from cycles where subscription_id = p_sub and status in ('active','expired');
  insert into subscription_events (subscription_id, client_id, event_type, mrr_delta, effective_date, note, created_by)
  values (p_sub, s.client_id, 'cancel', -round(s.price * 30.0 / s.validity_days, 2),
          coalesce(v_last, cairo_today()) + 1, p_reason, p_by);
  update clients set churn_reason = p_reason where id = s.client_id;
end $$;

-- ---- الاستوديو: وصل ----
create or replace function check_in_session(p_session uuid) returns void
language plpgsql security definer set search_path = public as $$
begin
  update studio_sessions set status = 'checked_in', checked_in_at = now(),
         actual_start = coalesce(actual_start, now())
   where id = p_session and status = 'booked';
  if not found then raise exception 'الجلسة مش في حالة "محجوز"'; end if;
end $$;

-- ---- الاستوديو: خلصت الجلسة (المانوال + الخصم + إنشاء الريلز) ----
create or replace function complete_session(
  p_session uuid, p_actual_qty numeric default null,
  p_actual_start timestamptz default null, p_actual_end timestamptz default null, p_by uuid default null
) returns numeric language plpgsql security definer set search_path = public as $$
declare s studio_sessions; v_unit text; v_missing text[]; v_stages text[]; v_qty numeric;
        v_avail numeric; v_use numeric; v_over numeric; v_rate numeric; v_minutes numeric;
        v_round numeric; v_del uuid; v_start timestamptz; v_end timestamptz; v_remaining numeric;
begin
  perform require_role(array['studio','support','account_manager','media_lead']);
  perform require_role(array['studio','support','account_manager','media_lead']);
  select * into s from studio_sessions where id = p_session for update;
  if s.status not in ('booked','checked_in') then raise exception 'الجلسة مش مفتوحة (الحالة: %)', s.status; end if;

  v_stages := array['pre_shoot','shoot'];
  if not exists (select 1 from studio_sessions where client_id = s.client_id and status = 'done') then
    v_stages := v_stages || 'onboarding'::text;           -- أول جلسة للعميل
  end if;
  v_missing := checklist_missing(v_stages, s.deliverable_type, p_session, null);
  if cardinality(v_missing) > 0 then
    raise exception 'لازم تخلص البنود الإجبارية الأول: %', array_to_string(v_missing, ' | ');
  end if;

  select unit_kind into v_unit from deliverable_types where code = s.deliverable_type;
  v_start := coalesce(p_actual_start, s.actual_start, s.booked_start);
  v_end := coalesce(p_actual_end, now());

  if v_unit = 'hours' then
    v_minutes := extract(epoch from (v_end - v_start)) / 60;
    v_round := setting_num('hours_rounding_minutes');
    v_qty := coalesce(p_actual_qty,
               greatest(setting_num('min_session_hours'), round(v_minutes / v_round) * v_round / 60.0));
  else
    v_qty := coalesce(p_actual_qty, s.planned_qty);
  end if;

  select greatest(granted + bonus - used, 0) into v_avail
    from cycle_entitlements where cycle_id = s.cycle_id and deliverable_type = s.deliverable_type;

  if v_unit = 'count' then
    if v_qty > v_avail then
      raise exception 'اتصور % ريل والرصيد % بس — اعمل top-up الأول', v_qty, v_avail;
    end if;
    for i in 1 .. v_qty::int loop
      insert into deliverables (client_id, cycle_id, session_id, deliverable_type, stage, title, updated_by)
      values (s.client_id, s.cycle_id, s.id, 'reel', 'filmed',
              'ريل ' || i || ' — ' || to_char(s.booked_start at time zone 'Africa/Cairo', 'DD/MM'), p_by)
      returning id into v_del;
      insert into consumption_log (cycle_id, deliverable_type, qty, reason, deliverable_id, session_id, created_by)
      values (s.cycle_id, 'reel', 1, 'consumed', v_del, s.id, p_by);
    end loop;
  else
    v_use := least(v_qty, v_avail);
    v_over := v_qty - v_use;
    if v_use > 0 then
      insert into consumption_log (cycle_id, deliverable_type, qty, reason, session_id, created_by)
      values (s.cycle_id, s.deliverable_type, v_use, 'consumed', s.id, p_by);
    end if;
    if v_over > 0 then
      select overage_rate into v_rate from subscriptions sub join cycles c on c.subscription_id = sub.id
       where c.id = s.cycle_id;
      if v_rate is null then
        raise exception 'الجلسة عدّت الرصيد بـ % ساعة ومفيش سعر متفق عليه للساعة الزيادة', v_over;
      end if;
      insert into consumption_log (cycle_id, deliverable_type, qty, reason, session_id, note, created_by)
      values (s.cycle_id, s.deliverable_type, v_over, 'overage', s.id, 'ساعات زيادة عن الباقة', p_by);
      insert into invoices (client_id, cycle_id, kind, description, amount, due_date)
      values (s.client_id, s.cycle_id, 'overage', v_over || ' ساعة زيادة', v_over * v_rate, cairo_today());
    end if;
  end if;

  update studio_sessions set status = 'done', actual_start = v_start, actual_end = v_end, actual_qty = v_qty
   where id = p_session;

  select greatest(granted + bonus - used, 0) into v_remaining
    from cycle_entitlements where cycle_id = s.cycle_id and deliverable_type = s.deliverable_type;
  perform queue_alert('session_done', 'session_done:' || s.id, array['client'], s.client_id,
    jsonb_build_object('qty', v_qty, 'unit', v_unit, 'remaining', v_remaining,
                       'end_date', (select end_date from cycles where id = s.cycle_id)),
    s.cycle_id, s.id);
  return v_qty;
end $$;

-- ---- الاستوديو: مجاش ----
create or replace function mark_no_show(p_session uuid, p_by uuid default null) returns numeric
language plpgsql security definer set search_path = public as $$
declare s studio_sessions; v_unit text; v_pen numeric; v_avail numeric;
begin
  perform require_role(array['studio','support','account_manager','media_lead']);
  select * into s from studio_sessions where id = p_session for update;
  if s.status <> 'booked' then raise exception 'الجلسة مش في حالة "محجوز"'; end if;
  select unit_kind into v_unit from deliverable_types where code = s.deliverable_type;
  v_pen := case v_unit when 'count' then setting_num('no_show_penalty_reels') else setting_num('no_show_penalty_hours') end;
  select greatest(granted + bonus - used, 0) into v_avail
    from cycle_entitlements where cycle_id = s.cycle_id and deliverable_type = s.deliverable_type;
  v_pen := least(v_pen, v_avail);

  update studio_sessions set status = 'no_show' where id = p_session;
  if v_pen > 0 then
    insert into consumption_log (cycle_id, deliverable_type, qty, reason, session_id, created_by)
    values (s.cycle_id, s.deliverable_type, v_pen, 'no_show', s.id, p_by);
  end if;
  perform queue_alert('no_show', 'no_show:' || s.id, array['client','account_manager'], s.client_id,
    jsonb_build_object('penalty', v_pen, 'unit', v_unit), s.cycle_id, s.id);
  return v_pen;
end $$;

-- ---- إلغاء جلسة (المتأخر بيتخصم) ----
create or replace function cancel_session(p_session uuid, p_reason text default null, p_by uuid default null)
returns boolean language plpgsql security definer set search_path = public as $$
declare s studio_sessions; v_late boolean; v_unit text; v_pen numeric; v_avail numeric;
begin
  perform require_role(array['studio','support','account_manager','media_lead']);
  select * into s from studio_sessions where id = p_session for update;
  if s.status <> 'booked' then raise exception 'الجلسة مش في حالة "محجوز"'; end if;
  v_late := now() > s.booked_start - make_interval(hours => setting_num('late_cancel_hours')::int);

  update studio_sessions set status = 'cancelled', cancelled_at = now(), late_cancel = v_late,
         notes = concat_ws(' | ', notes, p_reason)
   where id = p_session;

  if v_late then
    select unit_kind into v_unit from deliverable_types where code = s.deliverable_type;
    v_pen := case v_unit when 'count' then setting_num('no_show_penalty_reels') else setting_num('no_show_penalty_hours') end;
    select greatest(granted + bonus - used, 0) into v_avail
      from cycle_entitlements where cycle_id = s.cycle_id and deliverable_type = s.deliverable_type;
    v_pen := least(v_pen, v_avail);
    if v_pen > 0 then
      insert into consumption_log (cycle_id, deliverable_type, qty, reason, session_id, note, created_by)
      values (s.cycle_id, s.deliverable_type, v_pen, 'late_cancel', s.id, p_reason, p_by);
    end if;
  end if;
  return v_late;
end $$;

-- =====================================================================
-- الـ Jobs اليومية
-- =====================================================================

-- ---- قفل الدورات المنتهية + حساب الرصيد الضايع + الـ churn ----
create or replace function close_expired_cycles() returns int
language plpgsql security definer set search_path = public as $$
declare r record; n int := 0; v_payload jsonb;
begin
  perform require_role(array[]::text[]);
  -- دورات تجديد عدّت من غير دفع
  update cycles set status = 'cancelled', closed_at = now()
   where status = 'pending_payment' and end_date < cairo_today();

  for r in select * from cycles where status = 'active' and end_date < cairo_today() for update loop
    update cycle_entitlements e set forfeited = greatest(e.granted + e.bonus - e.used, 0)
      from deliverable_types dt
     where e.cycle_id = r.id and dt.code = e.deliverable_type and dt.unit_kind in ('count','hours');
    update cycles set status = 'expired', closed_at = now() where id = r.id;

    select jsonb_agg(jsonb_build_object('type', e.deliverable_type, 'name', dt.name_ar,
             'total', e.granted + e.bonus, 'used', e.used, 'forfeited', e.forfeited))
      into v_payload
      from cycle_entitlements e join deliverable_types dt on dt.code = e.deliverable_type
     where e.cycle_id = r.id and dt.unit_kind in ('count','hours');
    perform queue_alert('expired_summary', 'expired:' || r.id, array['client','account_manager'],
                        r.client_id, jsonb_build_object('items', v_payload, 'end_date', r.end_date), r.id);
    n := n + 1;
  end loop;

  -- اشتراكات ملغية خلصت دوراتها
  update subscriptions s set status = 'cancelled', cancelled_at = cairo_today()
   where s.status = 'active' and not s.auto_renew
     and not exists (select 1 from cycles c where c.subscription_id = s.id
                       and c.status in ('active','pending_payment'));

  -- عميل مالوش أي دورة نشطة أو جاية = churned
  update clients cl set status = 'churned', churned_at = cairo_today()
   where cl.status = 'active'
     and exists (select 1 from cycles c where c.client_id = cl.id)
     and not exists (select 1 from cycles c where c.client_id = cl.id
                       and c.status in ('active','pending_payment'));
  return n;
end $$;

-- ---- التجديد التلقائي (فاتورة + دورة جديدة pending_payment) ----
create or replace function renew_due_subscriptions() returns int
language plpgsql security definer set search_path = public as $$
declare r record; v_cycle uuid; n int := 0;
begin
  perform require_role(array[]::text[]);
  for r in
    select s.id, s.client_id, s.price, lc.last_end
      from subscriptions s
      cross join lateral (select max(end_date) last_end from cycles c
                           where c.subscription_id = s.id and c.status <> 'cancelled') lc
     where s.status = 'active' and s.auto_renew and lc.last_end is not null
       and lc.last_end - cairo_today() <= setting_num('renewal_lead_days')
       and lc.last_end >= cairo_today() - 1
  loop
    v_cycle := create_cycle(r.id, r.last_end + 1);
    insert into subscription_events (subscription_id, client_id, event_type, effective_date)
    values (r.id, r.client_id, 'renewal', r.last_end + 1);
    perform queue_alert('renewal_invoice', 'renewal:' || v_cycle, array['client'], r.client_id,
      jsonb_build_object('amount', r.price, 'start_date', r.last_end + 1), v_cycle);
    n := n + 1;
  end loop;
  return n;
end $$;

-- ---- مواعيد النشر من الجدول المتفق عليه ----
create or replace function generate_content_slots(p_days int default 28) returns int
language plpgsql security definer set search_path = public as $$
declare n int;
begin
  insert into content_slots (client_id, deliverable_type, slot_date, publish_time)
  select ps.client_id, ps.deliverable_type, d::date, ps.publish_time
    from publishing_schedule ps
    join clients c on c.id = ps.client_id and c.status = 'active'
   cross join generate_series(cairo_today(), cairo_today() + p_days, interval '1 day') d
   where ps.active and extract(dow from d)::int = ps.weekday
  on conflict do nothing;
  get diagnostics n = row_count;
  return n;
end $$;

-- ---- توزيع المحتوى الجاهز على المواعيد الفاضية ----
create or replace function assign_ready_content() returns int
language plpgsql security definer set search_path = public as $$
declare n int;
begin
  with ready as (
    select d.id, d.client_id, d.deliverable_type,
           row_number() over (partition by d.client_id, d.deliverable_type order by d.approved_at, d.created_at) rn
      from deliverables d
     where d.stage = 'approved'
       and not exists (select 1 from content_slots s where s.deliverable_id = d.id)),
  empty as (
    select s.id, s.client_id, s.deliverable_type,
           row_number() over (partition by s.client_id, s.deliverable_type order by s.slot_date) rn
      from content_slots s
     where s.status = 'empty' and s.slot_date >= cairo_today())
  update content_slots cs set deliverable_id = r.id, status = 'reserved'
    from empty e join ready r using (client_id, deliverable_type, rn)
   where cs.id = e.id;
  get diagnostics n = row_count;
  return n;
end $$;

-- ---- مواعيد عدّت من غير نشر ----
create or replace function mark_missed_slots() returns int
language plpgsql security definer set search_path = public as $$
declare r record; n int := 0;
begin
  for r in
    update content_slots
       set status = case when deliverable_id is null then 'missed_no_content' else 'missed_internal' end
     where slot_date < cairo_today() and status in ('empty','reserved')
    returning id, client_id, deliverable_type, slot_date, status
  loop
    n := n + 1;
    if r.status = 'missed_no_content' then
      perform queue_alert('slot_missed', 'slot_missed:' || r.id, array['client','support'], r.client_id,
        jsonb_build_object('type', r.deliverable_type, 'date', r.slot_date));
    else
      perform queue_alert('slot_missed_internal', 'slot_missed:' || r.id, array['media_lead'], r.client_id,
        jsonb_build_object('type', r.deliverable_type, 'date', r.slot_date));
    end if;
  end loop;
  return n;
end $$;

-- =====================================================================
-- Views
-- =====================================================================

-- ---- الرصيد (بروفايل العميل) ----
create or replace view v_cycle_balance as
select c.client_id, cl.name as client_name, c.id as cycle_id, c.subscription_id,
       c.start_date, c.end_date, c.status as cycle_status,
       e.deliverable_type, dt.name_ar, dt.unit_kind,
       e.granted + e.bonus as total, e.used,
       greatest(e.granted + e.bonus - e.used, 0) as remaining,
       coalesce((select sum(s.planned_qty) from studio_sessions s
                  where s.cycle_id = c.id and s.deliverable_type = e.deliverable_type
                    and s.status in ('booked','checked_in')), 0) as reserved,
       round(100.0 * e.used / nullif(e.granted + e.bonus, 0), 1) as used_pct,
       (c.end_date - cairo_today()) as days_left,
       round(100.0 * (cairo_today() - c.start_date + 1) / (c.end_date - c.start_date + 1), 1) as time_elapsed_pct
  from cycles c
  join clients cl on cl.id = c.client_id
  join cycle_entitlements e on e.cycle_id = c.id
  join deliverable_types dt on dt.code = e.deliverable_type
 where c.status in ('active','pending_payment');

-- ---- بنك المحتوى ----
create or replace view v_content_runway as
with stock as (
  select client_id, deliverable_type,
         count(*) filter (where stage = 'approved') as ready,
         count(*) filter (where stage in ('filmed','editing','review','brief','designing')) as in_pipeline
    from deliverables
   group by 1, 2),
upcoming as (
  select s.client_id, s.deliverable_type, s.slot_date,
         row_number() over (partition by s.client_id, s.deliverable_type order by s.slot_date) as rn
    from content_slots s
   where s.slot_date >= cairo_today() and s.status in ('empty','reserved')),
sched as (select distinct client_id, deliverable_type from publishing_schedule where active)
select sc.client_id, cl.name as client_name, cl.code as client_code, sc.deliverable_type,
       coalesce(st.ready, 0) as ready,
       coalesce(st.in_pipeline, 0) as in_pipeline,
       (select count(*) from upcoming u where u.client_id = sc.client_id
           and u.deliverable_type = sc.deliverable_type and u.slot_date < cairo_today() + 7) as slots_next_7d,
       g.slot_date as first_gap_date,
       g.slot_date - setting_num('editing_days')::int - 1 as shoot_deadline,
       (select min(ss.booked_start) from studio_sessions ss
         where ss.client_id = sc.client_id and ss.status in ('booked','checked_in')
           and ss.booked_start >= now()) as next_booked_session
  from sched sc
  join clients cl on cl.id = sc.client_id and cl.status = 'active'
  left join stock st on st.client_id = sc.client_id and st.deliverable_type = sc.deliverable_type
  left join upcoming g on g.client_id = sc.client_id and g.deliverable_type = sc.deliverable_type
                      and g.rn = coalesce(st.ready, 0) + coalesce(st.in_pipeline, 0) + 1;

-- ---- جدول النشر مش متطابق مع الباقة ----
create or replace view v_schedule_package_check as
with weekly as (
  select client_id, deliverable_type, count(*) as per_week
    from publishing_schedule where active group by 1, 2),
cur as (
  select c.client_id, e.deliverable_type, e.granted + e.bonus as total, (c.end_date - c.start_date + 1) as days
    from cycles c join cycle_entitlements e on e.cycle_id = c.id
   where c.status = 'active')
select w.client_id, cl.name as client_name, w.deliverable_type, w.per_week,
       cur.total as package_qty,
       round(w.per_week * cur.days / 7.0, 1) as needed_by_schedule,
       round(cur.total - w.per_week * cur.days / 7.0, 1) as diff
  from weekly w
  join cur on cur.client_id = w.client_id and cur.deliverable_type = w.deliverable_type
  join clients cl on cl.id = w.client_id
 where abs(cur.total - w.per_week * cur.days / 7.0) >= 1;

-- ---- جلسات النهارده (شاشة الاستوديو + رسالة المصورين الصبح) ----
create or replace view v_today_sessions as
select s.id as session_id, s.booked_start, s.booked_end, s.status, s.confirmation,
       cl.name as client_name, cl.phone_wa as client_phone, st.name as studio,
       ph.name as photographer, ph.phone_wa as photographer_phone,
       s.deliverable_type, s.planned_qty, s.outfits_required, s.locations_required,
       (select count(*) from scripts sc where sc.client_id = s.client_id and sc.status = 'approved'
           and not exists (select 1 from deliverables d where d.script_id = sc.id)) as approved_scripts_unused,
       not exists (select 1 from studio_sessions p where p.client_id = s.client_id and p.status = 'done') as first_session
  from studio_sessions s
  join clients cl on cl.id = s.client_id
  join studios st on st.id = s.studio_id
  left join staff ph on ph.id = s.photographer_id
 where (s.booked_start at time zone 'Africa/Cairo')::date = cairo_today()
   and s.status <> 'cancelled';

-- ---- MRR شهري (لقطة يوم 1 من كل شهر) ----
create or replace view v_mrr_monthly as
select m::date as month,
       coalesce(sum(c.monthly_value), 0) as mrr,
       count(distinct c.client_id) as active_clients,
       round(coalesce(sum(c.monthly_value), 0) / nullif(count(distinct c.client_id), 0), 2) as arpa
  from generate_series(date_trunc('month', cairo_today()) - interval '11 months',
                       date_trunc('month', cairo_today()), interval '1 month') m
  left join cycles c on c.status in ('active','expired') and m::date between c.start_date and c.end_date
 group by 1 order by 1;

-- ---- Churn و Retention و NRR و GRR شهريًا ----
create or replace view v_retention_monthly as
with months as (
  select m::date as month
    from generate_series(date_trunc('month', cairo_today()) - interval '12 months',
                         date_trunc('month', cairo_today()) - interval '1 month', interval '1 month') m),
at_start as (
  select mo.month, c.client_id, sum(c.monthly_value) as mv
    from months mo join cycles c on c.status in ('active','expired') and mo.month between c.start_date and c.end_date
   group by 1, 2),
at_next as (
  select mo.month, c.client_id, sum(c.monthly_value) as mv
    from months mo join cycles c on c.status in ('active','expired')
                                 and (mo.month + interval '1 month')::date between c.start_date and c.end_date
   group by 1, 2)
select a.month,
       count(*) as clients_start,
       count(*) filter (where n.client_id is null) as churned_clients,
       round(100.0 * count(*) filter (where n.client_id is null) / count(*), 1) as logo_churn_pct,
       sum(a.mv) as mrr_start,
       round(100.0 * sum(least(coalesce(n.mv, 0), a.mv)) / nullif(sum(a.mv), 0), 1) as grr_pct,
       round(100.0 * sum(coalesce(n.mv, 0)) / nullif(sum(a.mv), 0), 1) as nrr_pct
  from at_start a
  left join at_next n on n.month = a.month and n.client_id = a.client_id
 group by a.month order by a.month;

-- ---- الاستهلاك والرصيد الضايع (الدورات المقفولة) ----
create or replace view v_utilization_monthly as
select date_trunc('month', c.end_date)::date as month, e.deliverable_type,
       sum(e.granted + e.bonus) as total, sum(e.used) as used, sum(e.forfeited) as forfeited,
       round(100.0 * sum(e.used) / nullif(sum(e.granted + e.bonus), 0), 1) as utilization_pct,
       round(100.0 * sum(e.forfeited) / nullif(sum(e.granted + e.bonus), 0), 1) as forfeiture_pct
  from cycles c
  join cycle_entitlements e on e.cycle_id = c.id
  join deliverable_types dt on dt.code = e.deliverable_type and dt.unit_kind in ('count','hours')
 where c.status = 'expired'
 group by 1, 2;

-- ---- التقييمات لكل مصور وإيديتور ----
create or replace view v_feedback_by_staff as
select date_trunc('month', f.submitted_at)::date as month, st.id as staff_id, st.name, st.roles,
       round(avg(f.filming_score) filter (where st.id = ss.photographer_id), 2) as avg_filming,
       round(avg(f.editing_score) filter (where st.id = d.assignee_id), 2) as avg_editing,
       count(*) as responses
  from feedback f
  join deliverables d on d.id = f.deliverable_id
  left join studio_sessions ss on ss.id = d.session_id
  join staff st on st.id = ss.photographer_id or st.id = d.assignee_id
 group by 1, 2, 3, 4;

-- ---- نظرة عامة على العميل + health score ----
create or replace view v_client_overview as
with base as (
  select cl.id as client_id, cl.name, cl.code, cl.status, am.name as account_manager,
         ac.id as cycle_id, ac.start_date, ac.end_date, ac.end_date - cairo_today() as days_left,
         (select coalesce(sum(amount), 0) from payments p where p.client_id = cl.id) as total_paid,
         (select coalesce(sum(i.amount - i.paid_amount), 0) from invoices i
           where i.client_id = cl.id and i.status in ('unpaid','partial')) as outstanding,
         (select count(*) from invoices i where i.client_id = cl.id and i.status in ('unpaid','partial')
             and i.due_date < cairo_today() - setting_num('payment_overdue_days')::int) as overdue_invoices,
         (select round(avg((coalesce(f.filming_score, f.editing_score) + coalesce(f.editing_score, f.filming_score)) / 2.0), 2)
            from feedback f where f.client_id = cl.id and f.submitted_at > now() - interval '60 days') as satisfaction_60d,
         (select count(*) from content_slots s where s.client_id = cl.id and s.status = 'missed_no_content'
             and s.slot_date > cairo_today() - 30) as missed_slots_30d,
         (select sum(b.used) / nullif(sum(b.total), 0) from v_cycle_balance b
           where b.cycle_id = ac.id and b.unit_kind in ('count','hours')) as usage_ratio,
         (select max(b.time_elapsed_pct) / 100.0 from v_cycle_balance b where b.cycle_id = ac.id) as time_ratio,
         (select jsonb_agg(jsonb_build_object('type', b.deliverable_type, 'name', b.name_ar, 'unit', b.unit_kind,
                   'total', b.total, 'used', b.used, 'remaining', b.remaining, 'reserved', b.reserved)
                   order by b.deliverable_type)
            from v_cycle_balance b where b.cycle_id = ac.id) as balances
    from clients cl
    left join staff am on am.id = cl.account_manager_id
    left join lateral (select * from cycles c where c.client_id = cl.id and c.status = 'active'
                        order by c.end_date limit 1) ac on true)
select b.*,
       round(
         40 * least(1, coalesce(b.usage_ratio / nullif(b.time_ratio, 0), 1))
       + 20 * case when b.overdue_invoices > 0 then 0 else 1 end
       + 20 * coalesce(b.satisfaction_60d / 5.0, 1)
       + 20 * case when b.missed_slots_30d = 0 then 1 when b.missed_slots_30d = 1 then 0.5 else 0 end
       ) as health_score
  from base b;

-- ---- صندوق الرسايل لـ n8n (بيجيب رقم كل مستلم) ----
create or replace view v_alert_outbox as
select a.id as alert_id, a.alert_key, a.audience, a.priority, a.payload, a.created_at,
       a.client_id, cl.name as client_name, cl.code as client_code, cl.phone_wa as client_phone,
       a.cycle_id, a.session_id, a.deliverable_id,
       r.phone, r.recipient_name
  from alerts a
  left join clients cl on cl.id = a.client_id
  cross join lateral (
    select cl.phone_wa as phone, cl.name as recipient_name where a.audience = 'client'
    union all
    select st.phone_wa, st.name from staff st
     where a.audience = 'account_manager' and st.id = cl.account_manager_id
    union all
    -- عميل مالوش Account Manager → الدعم الفني
    select setting_text('support_wa'), 'الدعم الفني'
     where a.audience = 'account_manager' and cl.account_manager_id is null
    union all
    select st.phone_wa, st.name from staff st
     where a.audience in ('sales','admin','finance','media_lead') and a.audience = any(st.roles) and st.active
    union all
    select st.phone_wa, st.name from staff st
     where a.audience = 'staff' and st.id = a.recipient_staff_id
    union all
    select setting_text('support_wa'), 'الدعم الفني' where a.audience = 'support'
  ) r
 where a.status = 'queued' and r.phone is not null and r.phone <> ''
 order by (a.priority = 'urgent') desc, a.id;

-- =====================================================================
-- توليد التنبيهات (يومي) + تذكيرات الجلسات (كل ساعة)
-- =====================================================================
create or replace function generate_alerts() returns int
language plpgsql security definer set search_path = public as $$
declare r record; n int := 0;
begin
  -- 1) قرب الانتهاء: 7 / 3 / 1 يوم وفيه رصيد
  for r in
    select cycle_id, client_id, min(days_left) as days_left, min(end_date) as end_date,
           jsonb_agg(jsonb_build_object('type', deliverable_type, 'name', name_ar, 'remaining', remaining,
                                        'total', total, 'unit', unit_kind)) as items
      from v_cycle_balance
     where cycle_status = 'active' and days_left in (7, 3, 1) and unit_kind in ('count','hours') and remaining > 0
     group by cycle_id, client_id
  loop
    n := n + queue_alert('expiry_' || r.days_left || 'd', 'expiry_' || r.days_left || ':' || r.cycle_id,
                         array['client','account_manager'], r.client_id,
                         jsonb_build_object('days_left', r.days_left, 'end_date', r.end_date, 'items', r.items),
                         r.cycle_id);
  end loop;

  -- 2) استهلاك عالي (80%)
  for r in
    select * from v_cycle_balance
     where cycle_status = 'active' and unit_kind in ('count','hours') and remaining > 0
       and used_pct >= setting_num('usage_high_pct') and days_left > 3
  loop
    n := n + queue_alert('usage_high', 'usage_high:' || r.cycle_id || ':' || r.deliverable_type,
                         array['client','account_manager'], r.client_id,
                         jsonb_build_object('type', r.deliverable_type, 'name', r.name_ar, 'used', r.used,
                                            'total', r.total, 'remaining', r.remaining, 'end_date', r.end_date),
                         r.cycle_id);
  end loop;

  -- 3) الرصيد خلص بدري = فرصة upgrade
  for r in
    select * from v_cycle_balance
     where cycle_status = 'active' and unit_kind in ('count','hours') and remaining = 0 and days_left >= 5
  loop
    n := n + queue_alert('usage_full_early', 'usage_full:' || r.cycle_id || ':' || r.deliverable_type,
                         array['account_manager','sales'], r.client_id,
                         jsonb_build_object('type', r.deliverable_type, 'name', r.name_ar, 'days_left', r.days_left),
                         r.cycle_id);
  end loop;

  -- 4) ساعات المدربين وصلت 20% → السيلز
  for r in
    select b.*, round(b.used / greatest((cairo_today() - b.start_date + 1) / 7.0, 1), 2) as weekly_avg
      from v_cycle_balance b
     where b.cycle_status = 'active' and b.unit_kind = 'hours' and b.remaining > 0
       and 100.0 * b.remaining / nullif(b.total, 0) <= setting_num('hours_low_pct')
  loop
    n := n + queue_alert('hours_low', 'hours_low:' || r.cycle_id || ':' || r.deliverable_type,
                         array['sales'], r.client_id,
                         jsonb_build_object('remaining', r.remaining, 'total', r.total, 'end_date', r.end_date,
                                            'weekly_avg', r.weekly_avg,
                                            'weeks_to_finish', round(r.remaining / nullif(r.weekly_avg, 0), 1),
                                            'last_session', (select max(actual_end) from studio_sessions s
                                                              where s.cycle_id = r.cycle_id and s.status = 'done')),
                         r.cycle_id);
  end loop;

  -- 5) استهلاك ضعيف في نص الدورة = خطر churn
  for r in
    select cycle_id, client_id, round(100.0 * sum(used) / nullif(sum(total), 0), 1) as used_pct,
           max(time_elapsed_pct) as elapsed
      from v_cycle_balance
     where cycle_status = 'active' and unit_kind in ('count','hours')
     group by cycle_id, client_id
    having max(time_elapsed_pct) >= 50
       and 100.0 * sum(used) / nullif(sum(total), 0) < setting_num('low_usage_mid_pct')
  loop
    n := n + queue_alert('low_usage_mid', 'low_usage_mid:' || r.cycle_id, array['account_manager'], r.client_id,
                         jsonb_build_object('used_pct', r.used_pct, 'elapsed_pct', r.elapsed), r.cycle_id);
  end loop;

  -- 6) بنك المحتوى قرب يخلص ومفيش تصوير محجوز قبل الديدلاين
  for r in
    select * from v_content_runway
     where first_gap_date is not null
       and first_gap_date <= cairo_today() + setting_num('runway_alert_days')::int
       and (deliverable_type <> 'reel'
            or next_booked_session is null
            or (next_booked_session at time zone 'Africa/Cairo')::date > shoot_deadline)
  loop
    n := n + queue_alert('content_runway_low',
                         'runway:' || r.client_id || ':' || r.deliverable_type || ':' || r.first_gap_date,
                         array['client','support'], r.client_id,
                         jsonb_build_object('type', r.deliverable_type, 'ready', r.ready, 'in_pipeline', r.in_pipeline,
                                            'first_gap_date', r.first_gap_date, 'shoot_deadline', r.shoot_deadline));
  end loop;

  -- 7) فواتير متأخرة
  for r in
    select * from invoices
     where status in ('unpaid','partial')
       and due_date < cairo_today() - setting_num('payment_overdue_days')::int
  loop
    n := n + queue_alert('payment_overdue', 'overdue:' || r.id, array['client','finance'], r.client_id,
                         jsonb_build_object('invoice_id', r.id, 'amount', r.amount - r.paid_amount,
                                            'due_date', r.due_date, 'description', r.description),
                         r.cycle_id);
  end loop;

  -- 8) ريل واقف في المونتاج أكتر من المعتاد
  for r in
    select * from deliverables
     where stage = 'editing'
       and stage_changed_at < now() - make_interval(days => setting_num('editing_days')::int + 1)
  loop
    n := n + queue_alert('editing_late', 'editing_late:' || r.id || ':' || r.stage_changed_at::date,
                         array['media_lead'], r.client_id,
                         jsonb_build_object('deliverable_id', r.id, 'title', r.title, 'since', r.stage_changed_at),
                         r.cycle_id, null, r.id);
    if r.assignee_id is not null then
      n := n + queue_alert('editing_late', 'editing_late:' || r.id || ':' || r.stage_changed_at::date || ':s',
                           array['staff'], r.client_id,
                           jsonb_build_object('deliverable_id', r.id, 'title', r.title),
                           r.cycle_id, null, r.id, r.assignee_id);
    end if;
  end loop;

  return n;
end $$;

create or replace function generate_session_reminders() returns int
language plpgsql security definer set search_path = public as $$
declare r record; n int := 0; v_payload jsonb;
begin
  for r in
    select s.*, cl.name as client_name
      from studio_sessions s join clients cl on cl.id = s.client_id
     where s.status = 'booked' and s.booked_start between now() and now() + interval '25 hours'
  loop
    v_payload := jsonb_build_object(
      'start', r.booked_start, 'qty', r.planned_qty, 'type', r.deliverable_type,
      'outfits', r.outfits_required, 'locations', r.locations_required,
      'multi_reels', r.deliverable_type = 'reel' and r.planned_qty > 1);

    if r.booked_start <= now() + interval '25 hours' and r.booked_start > now() + interval '20 hours' then
      n := n + queue_alert('session_24h', 'session_24h:' || r.id, array['client'], r.client_id,
                           v_payload, r.cycle_id, r.id);
    end if;
    if r.booked_start <= now() + interval '2 hours 30 minutes' and r.booked_start > now() + interval '1 hour' then
      n := n + queue_alert('session_2h', 'session_2h:' || r.id, array['client'], r.client_id,
                           v_payload, r.cycle_id, r.id);
    end if;
    -- لسه مأكدش والجلسة بكرة → الدعم الفني يتصل
    if r.confirmation = 'pending' and r.booked_start <= now() + interval '18 hours' then
      n := n + queue_alert('session_unconfirmed', 'session_unconfirmed:' || r.id, array['support'], r.client_id,
                           v_payload, r.cycle_id, r.id);
    end if;
  end loop;
  return n;
end $$;

-- ---- Job يومي واحد (n8n بيناديه الساعة 00:05 بتوقيت القاهرة) ----
create or replace function run_daily() returns jsonb
language plpgsql security definer set search_path = public as $$
declare r jsonb := '{}';
begin
  perform require_role(array[]::text[]);
  r := r || jsonb_build_object('expired',   close_expired_cycles());
  r := r || jsonb_build_object('renewed',   renew_due_subscriptions());
  r := r || jsonb_build_object('slots',     generate_content_slots(28));
  r := r || jsonb_build_object('missed',    mark_missed_slots());
  r := r || jsonb_build_object('assigned',  assign_ready_content());
  r := r || jsonb_build_object('alerts',    generate_alerts());
  return r;
end $$;

-- =====================================================================
-- Indexes
-- =====================================================================
create index on cycles (client_id, status);
create index on cycles (status, end_date);
create index on consumption_log (cycle_id, deliverable_type);
create index on consumption_log (deliverable_id);
create index on studio_sessions (client_id, status);
create index on studio_sessions (booked_start);
create index on deliverables (client_id, deliverable_type, stage);
create index on deliverables (cycle_id);
create index on content_slots (client_id, deliverable_type, slot_date);
create index on invoices (client_id, status);
create index on payments (client_id);
create index on alerts (status, created_at);
create index on feedback (client_id, submitted_at);
