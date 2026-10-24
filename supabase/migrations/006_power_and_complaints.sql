-- =====================================================================
-- Migration 006: كارت الكهرباء + متابعة شكاوى العملاء + run_hourly + Realtime
-- =====================================================================

insert into settings (key, value, description_ar) values
  ('power_low_days', '2', 'نبّه لو رصيد الكهرباء هيخلص خلال الأيام دي')
on conflict (key) do nothing;

-- ---------------------------------------------------------------------
-- 1) الكهرباء
-- ---------------------------------------------------------------------
create table utility_meters (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  studio_id uuid references studios(id),
  active boolean not null default true
);

create table meter_readings (
  id bigserial primary key,
  meter_id uuid not null references utility_meters(id) on delete cascade,
  balance numeric(10,2) not null check (balance >= 0),   -- الرصيد بالجنيه على العداد
  tier int,                                              -- الشريحة
  kwh numeric(10,2),                                     -- قراية الكيلووات (اختياري)
  photo_path text,                                       -- Supabase Storage: meter-photos/...
  read_at timestamptz not null default now(),
  read_by uuid references staff(id) default auth.uid()
);
create index on meter_readings (meter_id, read_at);

create table meter_recharges (
  id bigserial primary key,
  meter_id uuid not null references utility_meters(id) on delete cascade,
  amount numeric(10,2) not null check (amount > 0),
  recharged_at timestamptz not null default now(),
  recorded_by uuid references staff(id) default auth.uid(),
  note text
);
create index on meter_recharges (meter_id, recharged_at);

insert into studios (name) select 'الاستوديو' where not exists (select 1 from studios);
insert into utility_meters (name, studio_id)
select 'عداد الاستوديو', (select id from studios order by name limit 1)
 where not exists (select 1 from utility_meters);

-- الاستهلاك بين كل قرايتين = الرصيد اللي فات + الشحن اللي بينهم − الرصيد الحالي
create or replace view v_meter_status with (security_invoker = on) as
with pairs as (
  select r.meter_id, r.read_at, r.balance,
         lag(r.read_at) over w as prev_at, lag(r.balance) over w as prev_balance
    from meter_readings r
   where r.read_at > now() - interval '14 days'
  window w as (partition by r.meter_id order by r.read_at)),
burn as (
  select p.meter_id,
         sum(p.prev_balance + coalesce((select sum(c.amount) from meter_recharges c
                                         where c.meter_id = p.meter_id and c.recharged_at > p.prev_at
                                           and c.recharged_at <= p.read_at), 0) - p.balance) as consumed,
         extract(epoch from (max(p.read_at) - min(p.prev_at))) / 86400.0 as days
    from pairs p where p.prev_at is not null
   group by p.meter_id),
last as (
  select distinct on (meter_id) * from meter_readings order by meter_id, read_at desc),
calc as (
  select m.id as meter_id, m.name, m.studio_id,
         l.balance as last_balance, l.tier, l.read_at as last_read_at, l.photo_path,
         l.balance + coalesce((select sum(c.amount) from meter_recharges c
                               where c.meter_id = m.id and c.recharged_at > l.read_at), 0) as balance_after_recharges,
         case when b.days > 0.2 and b.consumed > 0 then round(b.consumed / b.days, 2) end as daily_burn
    from utility_meters m
    left join last l on l.meter_id = m.id
    left join burn b on b.meter_id = m.id
   where m.active)
select c.*,
       greatest(round(c.balance_after_recharges
                - coalesce(c.daily_burn, 0) * extract(epoch from (now() - c.last_read_at)) / 86400.0, 2), 0) as balance_now_est,
       case when c.daily_burn > 0
            then c.last_read_at + make_interval(secs => (c.balance_after_recharges / c.daily_burn * 86400)::double precision)
       end as runs_out_at,
       case when c.daily_burn > 0
            then round((c.balance_after_recharges / c.daily_burn
                        - extract(epoch from (now() - c.last_read_at)) / 86400.0)::numeric, 1)
       end as days_left,
       ns.next_session,
       (c.daily_burn > 0 and ns.next_session is not null
        and c.last_read_at + make_interval(secs => (c.balance_after_recharges / c.daily_burn * 86400)::double precision)
            < ns.next_session) as runs_out_before_next_session
  from calc c
  left join lateral (select min(s.booked_start) as next_session from studio_sessions s
                      where s.status = 'booked' and s.booked_start > now()
                        and (c.studio_id is null or s.studio_id = c.studio_id)) ns on true;

create or replace function check_power() returns int
language plpgsql security definer set search_path = public as $$
declare r record; n int := 0;
begin
  for r in select * from v_meter_status
            where days_left is not null and (days_left <= setting_num('power_low_days') or runs_out_before_next_session)
  loop
    n := n + queue_alert('power_low', 'power_low:' || r.meter_id || ':' || cairo_today(), array['admin','support'], null,
      jsonb_build_object('meter', r.name, 'balance', r.balance_now_est, 'daily_burn', r.daily_burn,
                         'runs_out_at', r.runs_out_at, 'next_session', r.next_session), null, null, null, null,
      case when r.runs_out_before_next_session then 'urgent' else 'normal' end);
  end loop;
  return n;
end $$;

create or replace function record_meter_reading(
  p_meter uuid, p_balance numeric, p_tier int default null, p_kwh numeric default null, p_photo text default null
) returns jsonb language plpgsql security definer set search_path = public as $$
declare v jsonb;
begin
  perform require_role(array['support','studio','media_lead']);
  if p_balance is null or p_balance < 0 then raise exception 'اكتب رصيد العداد'; end if;
  insert into meter_readings (meter_id, balance, tier, kwh, photo_path, read_by)
  values (p_meter, p_balance, p_tier, p_kwh, p_photo, auth.uid());
  perform check_power();
  select to_jsonb(s) into v from v_meter_status s where s.meter_id = p_meter;
  return v;
end $$;

-- ---------------------------------------------------------------------
-- 2) الشكاوى (العميل المتضايق)
-- ---------------------------------------------------------------------
-- تنبيه client_unhappy بيتعمل لأكتر من جمهور بنفس الـ dedupe_key — الشكوى = المجموعة دي
create or replace view v_complaints with (security_invoker = on) as
with grp as (
  select distinct on (a.dedupe_key) a.*
    from alerts a where a.alert_key = 'client_unhappy'
   order by a.dedupe_key, a.handled_at nulls last, a.id)
select g.id as alert_id, g.dedupe_key, g.client_id, cl.name as client_name, cl.code as client_code,
       cl.phone_wa as client_phone, g.deliverable_id,
       (g.payload->>'filming')::int as filming_score, (g.payload->>'editing')::int as editing_score,
       (g.payload->>'location')::int as location_score,
       g.payload->'issues' as issues, g.payload->>'comment' as comment, g.payload->>'post_url' as post_url,
       g.payload->>'photographer' as photographer, g.payload->>'editor' as editor,
       g.created_at, g.handled_at, g.handled_by, hb.name as handled_by_name, g.resolution,
       g.handled_at is null as is_open,
       round(extract(epoch from (coalesce(g.handled_at, now()) - g.created_at)) / 60)::int as wait_minutes,
       exists (select 1 from alerts e where e.dedupe_key = 'escalated:' || g.dedupe_key) as escalated,
       (extract(epoch from (coalesce(g.handled_at, now()) - g.created_at)) / 60
         <= setting_num('complaint_sla_minutes')) as within_sla
  from grp g
  left join clients cl on cl.id = g.client_id
  left join staff hb on hb.id = g.handled_by;

create or replace function resolve_alert(p_alert bigint, p_resolution text, p_by uuid default null)
returns void language plpgsql security definer set search_path = public as $$
declare a alerts; v_by uuid := coalesce(p_by, auth.uid()); v_mins numeric;
begin
  select * into a from alerts where id = p_alert;
  if a.id is null then raise exception 'التنبيه مش موجود'; end if;
  if a.alert_key in ('client_unhappy','complaint_escalated') then
    perform require_role(array['sales','support','account_manager','media_lead']);
  elsif auth.uid() is not null and not is_staff() then
    raise exception 'غير مصرح';
  end if;
  if p_resolution is null or btrim(p_resolution) = '' then
    raise exception 'اكتب حصل إيه مع العميل الأول';
  end if;
  if a.handled_at is not null then raise exception 'التنبيه ده اتقفل قبل كده'; end if;

  -- اقفل كل نسخ التنبيه (لكل الجماهير) + التصعيد بتاعه
  update alerts set handled_at = now(), handled_by = v_by, resolution = btrim(p_resolution)
   where (dedupe_key = a.dedupe_key or dedupe_key = 'escalated:' || a.dedupe_key) and handled_at is null;

  if a.alert_key = 'client_unhappy' then
    v_mins := extract(epoch from (now() - a.created_at)) / 60;
    if v_mins <= setting_num('complaint_sla_minutes') then
      perform award_xp(v_by, 'complaint_fast', 'complaint:' || a.dedupe_key, a.client_id, a.deliverable_id);
    end if;
  end if;
end $$;

create or replace function escalate_complaints() returns int
language plpgsql security definer set search_path = public as $$
declare r record; n int := 0;
begin
  for r in select * from v_complaints
            where is_open and wait_minutes > setting_num('complaint_sla_minutes') and not escalated
  loop
    n := n + queue_alert('complaint_escalated', 'escalated:' || r.dedupe_key, array['admin'], r.client_id,
      jsonb_build_object('client_name', r.client_name, 'client_phone', r.client_phone, 'comment', r.comment,
                         'wait_minutes', r.wait_minutes, 'complaint_alert_id', r.alert_id),
      null, null, r.deliverable_id, null, 'urgent');
  end loop;
  return n;
end $$;

-- ---- Job كل ساعة ----
create or replace function run_hourly() returns jsonb
language plpgsql security definer set search_path = public as $$
declare r jsonb := '{}';
begin
  perform require_role(array[]::text[]);
  r := r || jsonb_build_object('reminders',  generate_session_reminders());
  r := r || jsonb_build_object('escalated',  escalate_complaints());
  r := r || jsonb_build_object('power',      check_power());
  r := r || jsonb_build_object('challenges', check_challenges());
  return r;
end $$;

-- ---------------------------------------------------------------------
-- 3) الصلاحيات
-- ---------------------------------------------------------------------
alter table utility_meters enable row level security;
alter table meter_readings enable row level security;
alter table meter_recharges enable row level security;

create policy staff_read on utility_meters for select to authenticated using (is_staff());
create policy staff_read on meter_readings for select to authenticated using (is_staff());
create policy staff_read on meter_recharges for select to authenticated using (is_staff());
create policy admin_write on utility_meters for all to authenticated using (has_role('{}')) with check (has_role('{}'));
create policy recharges_insert on meter_recharges for insert to authenticated
  with check (has_role('{support,studio,media_lead}'));

revoke all on utility_meters, meter_readings, meter_recharges from anon;
revoke all on all sequences in schema public from anon;
revoke execute on function check_power(), escalate_complaints(), run_hourly() from anon, public, authenticated;
revoke execute on function record_meter_reading(uuid, numeric, int, numeric, text),
                           resolve_alert(bigint, text, uuid) from anon, public;
grant execute on function record_meter_reading(uuid, numeric, int, numeric, text),
                          resolve_alert(bigint, text, uuid) to authenticated;

-- ---------------------------------------------------------------------
-- 4) Storage (صور العداد) + Realtime — موجودين في Supabase بس
-- ---------------------------------------------------------------------
do $$
begin
  if exists (select 1 from information_schema.tables where table_schema = 'storage' and table_name = 'buckets') then
    insert into storage.buckets (id, name, public) values ('meter-photos', 'meter-photos', false)
    on conflict (id) do nothing;
    execute $p$create policy meter_photos_read on storage.objects for select to authenticated
              using (bucket_id = 'meter-photos' and public.is_staff())$p$;
    execute $p$create policy meter_photos_insert on storage.objects for insert to authenticated
              with check (bucket_id = 'meter-photos' and public.has_role('{support,studio,media_lead}'))$p$;
  end if;
  if exists (select 1 from pg_publication where pubname = 'supabase_realtime') then
    alter publication supabase_realtime add table alerts, deliverables, xp_ledger, studio_sessions;
  end if;
end $$;
