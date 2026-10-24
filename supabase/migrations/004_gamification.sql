-- =====================================================================
-- Migration 004: الـ XP والتحديات والرتب والبونص الشهري
-- كل نقطة بتتسجل في xp_ledger (append-only، ومتتكررش بالـ dedupe_key).
-- البونص = XP الشهر × قيمة النقطة، بحد أقصى bonus_cap — والأدمن بيعتمد.
-- =====================================================================

insert into settings (key, value, description_ar) values
  ('xp_point_value', '1',    'قيمة نقطة الـ XP بالجنيه في البونص'),
  ('bonus_cap',      '3000', 'أقصى بونص شهري للموظف (جنيه)'),
  ('complaint_sla_minutes', '120', 'لازم حد يكلم العميل المتضايق خلال الدقايق دي وإلا يتصعّد للأدمن')
on conflict (key) do nothing;

-- ---- قواعد النقط ----
create table xp_rules (
  code text primary key,
  name_ar text not null,
  points int not null,
  active boolean not null default true
);

insert into xp_rules (code, name_ar, points) values
  ('session_done',          'جلسة تصوير خلصت (المصور)',              20),
  ('deliverable_approved',  'ريل / تصميم اتوافق عليه (المسؤول)',      10),
  ('deliverable_published', 'نشر بوست في ميعاده',                     5),
  ('feedback_top_filming',  'تقييم 5 للتصوير',                        15),
  ('feedback_top_editing',  'تقييم 5 للمونتاج',                       15),
  ('feedback_low_filming',  'تقييم وحش للتصوير',                     -10),
  ('feedback_low_editing',  'تقييم وحش للمونتاج',                    -10),
  ('script_approved',       'سكربت العميل وافق عليه',                  5),
  ('complaint_fast',        'كلّمت العميل المتضايق في الوقت',          10),
  ('challenge_reward',      'مكافأة تحدي',                              0);

-- ---- الرتب (بمجموع الـ XP الكلي) ----
create table ranks (
  code text primary key,
  name_ar text not null,
  icon text not null,
  min_xp int not null unique
);

insert into ranks (code, name_ar, icon, min_xp) values
  ('bronze',  'برونزي', '🥉', 0),
  ('silver',  'فضي',    '🥈', 500),
  ('gold',    'ذهبي',   '🥇', 1500),
  ('diamond', 'ماسي',   '💎', 3000),
  ('legend',  'أسطورة', '🏆', 6000);

-- ---- سجل النقط ----
create table xp_ledger (
  id bigserial primary key,
  staff_id uuid not null references staff(id) on delete cascade,
  rule_code text not null references xp_rules(code),
  points int not null,
  dedupe_key text not null unique,
  client_id uuid references clients(id),
  deliverable_id uuid references deliverables(id),
  session_id uuid references studio_sessions(id),
  feedback_id uuid references feedback(id),
  challenge_id uuid,
  note text,
  created_at timestamptz not null default now()
);
create index on xp_ledger (staff_id, created_at);

create trigger xp_ledger_no_update before update or delete on xp_ledger
  for each row execute function trg_block_mutation();

create or replace function award_xp(
  p_staff uuid, p_rule text, p_dedupe text, p_client uuid default null, p_deliverable uuid default null,
  p_session uuid default null, p_feedback uuid default null, p_points int default null, p_note text default null,
  p_challenge uuid default null
) returns int language plpgsql security definer set search_path = public as $$
declare v_pts int; c int;
begin
  if p_staff is null then return 0; end if;
  select coalesce(p_points, points) into v_pts from xp_rules where code = p_rule and active;
  if v_pts is null or v_pts = 0 then return 0; end if;
  insert into xp_ledger (staff_id, rule_code, points, dedupe_key, client_id, deliverable_id, session_id,
                         feedback_id, note, challenge_id)
  values (p_staff, p_rule, v_pts, p_dedupe, p_client, p_deliverable, p_session, p_feedback, p_note, p_challenge)
  on conflict (dedupe_key) do nothing;
  get diagnostics c = row_count;
  return case when c > 0 then v_pts else 0 end;
end $$;

-- ---- مصادر النقط ----
create or replace function trg_xp_session() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.status = 'done' and old.status <> 'done' then
    perform award_xp(new.photographer_id, 'session_done', 'session_done:' || new.id,
                     new.client_id, null, new.id);
  end if;
  return new;
end $$;
create trigger xp_session after update of status on studio_sessions
  for each row execute function trg_xp_session();

create or replace function trg_xp_deliverable() returns trigger
language plpgsql security definer set search_path = public as $$
declare v_slot content_slots;
begin
  if new.stage is not distinct from old.stage then return new; end if;
  if new.stage = 'approved' then
    perform award_xp(new.assignee_id, 'deliverable_approved', 'approved:' || new.id, new.client_id, new.id);
  elsif new.stage = 'published' then
    -- في ميعاده = اتنشر يوم الـ slot بتاعه (أو مالوش slot)
    select * into v_slot from content_slots where deliverable_id = new.id limit 1;
    if v_slot.id is null or v_slot.slot_date >= cairo_today() then
      perform award_xp(new.updated_by, 'deliverable_published', 'published:' || new.id, new.client_id, new.id);
    end if;
  end if;
  return new;
end $$;
create trigger xp_deliverable after update of stage on deliverables
  for each row execute function trg_xp_deliverable();

create or replace function trg_xp_feedback() returns trigger
language plpgsql security definer set search_path = public as $$
declare d deliverables; v_ph uuid; v_low numeric := setting_num('low_feedback_score');
begin
  select * into d from deliverables where id = new.deliverable_id;
  if d.id is null then return new; end if;
  select photographer_id into v_ph from studio_sessions where id = coalesce(new.session_id, d.session_id);

  if new.filming_score = 5 then
    perform award_xp(v_ph, 'feedback_top_filming', 'fb_film:' || new.id, new.client_id, d.id, null, new.id);
  elsif new.filming_score <= v_low then
    perform award_xp(v_ph, 'feedback_low_filming', 'fb_film:' || new.id, new.client_id, d.id, null, new.id);
  end if;
  if new.editing_score = 5 then
    perform award_xp(d.assignee_id, 'feedback_top_editing', 'fb_edit:' || new.id, new.client_id, d.id, null, new.id);
  elsif new.editing_score <= v_low then
    perform award_xp(d.assignee_id, 'feedback_low_editing', 'fb_edit:' || new.id, new.client_id, d.id, null, new.id);
  end if;
  return new;
end $$;
create trigger xp_feedback after insert on feedback
  for each row execute function trg_xp_feedback();

create or replace function trg_xp_script() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.status = 'approved' and (tg_op = 'INSERT' or old.status <> 'approved') then
    perform award_xp(new.created_by, 'script_approved', 'script:' || new.id || ':' || new.revision_count, new.client_id);
  end if;
  return new;
end $$;
create trigger xp_script after insert or update of status on scripts
  for each row execute function trg_xp_script();

-- ---- التحديات ----
-- metric: sessions_done · deliverables_approved · deliverables_published · top_feedback · scripts_approved · xp
create table challenges (
  id uuid primary key default gen_random_uuid(),
  name_ar text not null,
  description_ar text,
  metric text not null check (metric in
    ('sessions_done','deliverables_approved','deliverables_published','top_feedback','scripts_approved','xp')),
  target numeric not null check (target > 0),
  reward_xp int not null default 0,
  roles text[],                 -- null = الكل
  starts_on date not null default date_trunc('month', cairo_today())::date,
  ends_on date not null default (date_trunc('month', cairo_today()) + interval '1 month - 1 day')::date,
  active boolean not null default true,
  created_at timestamptz not null default now(),
  check (ends_on >= starts_on)
);

create table challenge_completions (
  challenge_id uuid references challenges(id) on delete cascade,
  staff_id uuid references staff(id) on delete cascade,
  completed_at timestamptz not null default now(),
  primary key (challenge_id, staff_id)
);

create or replace function challenge_metric(p_metric text, p_staff uuid, p_from date, p_to date)
returns numeric language sql stable security definer set search_path = public as $$
  select case p_metric
    when 'sessions_done' then (select count(*) from xp_ledger where staff_id = p_staff and rule_code = 'session_done'
                                 and (created_at at time zone 'Africa/Cairo')::date between p_from and p_to)
    when 'deliverables_approved' then (select count(*) from xp_ledger where staff_id = p_staff and rule_code = 'deliverable_approved'
                                 and (created_at at time zone 'Africa/Cairo')::date between p_from and p_to)
    when 'deliverables_published' then (select count(*) from xp_ledger where staff_id = p_staff and rule_code = 'deliverable_published'
                                 and (created_at at time zone 'Africa/Cairo')::date between p_from and p_to)
    when 'top_feedback' then (select count(*) from xp_ledger where staff_id = p_staff
                                 and rule_code in ('feedback_top_filming','feedback_top_editing')
                                 and (created_at at time zone 'Africa/Cairo')::date between p_from and p_to)
    when 'scripts_approved' then (select count(*) from xp_ledger where staff_id = p_staff and rule_code = 'script_approved'
                                 and (created_at at time zone 'Africa/Cairo')::date between p_from and p_to)
    when 'xp' then (select coalesce(sum(points), 0) from xp_ledger where staff_id = p_staff and rule_code <> 'challenge_reward'
                                 and (created_at at time zone 'Africa/Cairo')::date between p_from and p_to)
  end::numeric
$$;

create or replace view v_challenge_progress with (security_invoker = on) as
select ch.id as challenge_id, ch.name_ar, ch.description_ar, ch.metric, ch.target, ch.reward_xp,
       ch.starts_on, ch.ends_on, st.id as staff_id, st.name as staff_name,
       least(challenge_metric(ch.metric, st.id, ch.starts_on, ch.ends_on), ch.target) as progress,
       round(100.0 * least(challenge_metric(ch.metric, st.id, ch.starts_on, ch.ends_on), ch.target) / ch.target, 1) as progress_pct,
       cc.completed_at is not null as completed, cc.completed_at
  from challenges ch
  join staff st on st.active and (ch.roles is null or st.roles && ch.roles)
  left join challenge_completions cc on cc.challenge_id = ch.id and cc.staff_id = st.id
 where ch.active;

create or replace function check_challenges() returns int
language plpgsql security definer set search_path = public as $$
declare r record; n int := 0;
begin
  for r in
    select ch.*, st.id as staff_id
      from challenges ch
      join staff st on st.active and (ch.roles is null or st.roles && ch.roles)
     where ch.active and cairo_today() >= ch.starts_on
       and not exists (select 1 from challenge_completions cc where cc.challenge_id = ch.id and cc.staff_id = st.id)
       and challenge_metric(ch.metric, st.id, ch.starts_on, ch.ends_on) >= ch.target
  loop
    insert into challenge_completions (challenge_id, staff_id) values (r.id, r.staff_id) on conflict do nothing;
    perform award_xp(r.staff_id, 'challenge_reward', 'challenge:' || r.id || ':' || r.staff_id,
                     null, null, null, null, r.reward_xp, r.name_ar, r.id);
    n := n + 1;
  end loop;
  return n;
end $$;

-- التحدي بيتشيك فورًا مع كل نقطة جديدة (علشان الاحتفال يبان لايف)
create or replace function trg_xp_check_challenges() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.rule_code <> 'challenge_reward' then perform check_challenges(); end if;
  return new;
end $$;
create trigger xp_check_challenges after insert on xp_ledger
  for each row execute function trg_xp_check_challenges();

-- ---- لوحة الشرف ----
create or replace view v_leaderboard with (security_invoker = on) as
with m as (select date_trunc('month', cairo_today())::date as month_start),
agg as (
  select st.id as staff_id, st.name, st.roles,
         coalesce(sum(x.points) filter (where (x.created_at at time zone 'Africa/Cairo')::date >= m.month_start), 0) as xp_month,
         coalesce(sum(x.points), 0) as xp_total
    from staff st cross join m
    left join xp_ledger x on x.staff_id = st.id
   where st.active
   group by st.id, st.name, st.roles)
select (select month_start from m) as month, a.*,
       rk.code as rank_code, rk.name_ar as rank_name, rk.icon as rank_icon,
       (select min(r2.min_xp) from ranks r2 where r2.min_xp > a.xp_total) as next_rank_xp,
       rank() over (order by a.xp_month desc) as position,
       least(greatest(a.xp_month, 0) * setting_num('xp_point_value'), setting_num('bonus_cap')) as expected_bonus
  from agg a
  left join lateral (select * from ranks r where r.min_xp <= greatest(a.xp_total, 0)
                      order by r.min_xp desc limit 1) rk on true;

-- ---- مين عمل إيه في كل ريل / تصميم ----
create or replace view v_deliverable_credits with (security_invoker = on) as
select d.id as deliverable_id, d.client_id, cl.name as client_name, d.deliverable_type, d.title, d.stage,
       d.post_url, d.published_at, d.revision_count, d.created_at,
       ss.photographer_id, ph.name as photographer,
       d.assignee_id, ed.name as editor,
       pub.changed_by as publisher_id, pb.name as publisher,
       fb.filming_score, fb.editing_score, fb.location_score, fb.comment as feedback_comment, fb.submitted_at as feedback_at
  from deliverables d
  join clients cl on cl.id = d.client_id
  left join studio_sessions ss on ss.id = d.session_id
  left join staff ph on ph.id = ss.photographer_id
  left join staff ed on ed.id = d.assignee_id
  left join lateral (select h.changed_by from deliverable_stage_history h
                      where h.deliverable_id = d.id and h.to_stage = 'published'
                      order by h.changed_at desc limit 1) pub on true
  left join staff pb on pb.id = pub.changed_by
  left join lateral (select * from feedback f where f.deliverable_id = d.id
                      order by f.submitted_at desc limit 1) fb on true;

-- ---- البونص الشهري ----
create table bonus_payouts (
  id uuid primary key default gen_random_uuid(),
  staff_id uuid not null references staff(id) on delete cascade,
  month date not null,
  xp int not null,
  amount numeric(12,2) not null,
  status text not null default 'pending' check (status in ('pending','approved','rejected','paid')),
  decided_by uuid references staff(id),
  decided_at timestamptz,
  paid_at timestamptz,
  note text,
  unique (staff_id, month)
);

create or replace function prepare_monthly_bonuses(p_month date default null) returns int
language plpgsql security definer set search_path = public as $$
declare v_month date := date_trunc('month', coalesce(p_month, (cairo_today() - interval '1 month')::date))::date; n int;
begin
  perform require_role(array[]::text[]);
  insert into bonus_payouts (staff_id, month, xp, amount)
  select x.staff_id, v_month, sum(x.points),
         least(greatest(sum(x.points), 0) * setting_num('xp_point_value'), setting_num('bonus_cap'))
    from xp_ledger x
   where (x.created_at at time zone 'Africa/Cairo')::date >= v_month
     and (x.created_at at time zone 'Africa/Cairo')::date < (v_month + interval '1 month')::date
   group by x.staff_id
  on conflict (staff_id, month) do update
     set xp = excluded.xp, amount = excluded.amount
   where bonus_payouts.status = 'pending';
  get diagnostics n = row_count;
  return n;
end $$;

create or replace function trg_bonus_status() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.status is distinct from old.status then
    new.decided_by := coalesce(auth.uid(), new.decided_by);
    new.decided_at := now();
    if new.status = 'paid' then new.paid_at := now(); end if;
  end if;
  return new;
end $$;
create trigger bonus_status before update on bonus_payouts
  for each row execute function trg_bonus_status();

-- ---- الـ job اليومي: التحديات + البونص أول الشهر ----
create or replace function run_daily() returns jsonb
language plpgsql security definer set search_path = public as $$
declare r jsonb := '{}';
begin
  perform require_role(array[]::text[]);
  r := r || jsonb_build_object('expired',    close_expired_cycles());
  r := r || jsonb_build_object('renewed',    renew_due_subscriptions());
  r := r || jsonb_build_object('slots',      generate_content_slots(28));
  r := r || jsonb_build_object('missed',     mark_missed_slots());
  r := r || jsonb_build_object('assigned',   assign_ready_content());
  r := r || jsonb_build_object('alerts',     generate_alerts());
  r := r || jsonb_build_object('challenges', check_challenges());
  if extract(day from cairo_today()) = 1 then
    r := r || jsonb_build_object('bonuses', prepare_monthly_bonuses());
  end if;
  return r;
end $$;

-- ---- الصلاحيات ----
alter table xp_rules enable row level security;
alter table ranks enable row level security;
alter table xp_ledger enable row level security;
alter table challenges enable row level security;
alter table challenge_completions enable row level security;
alter table bonus_payouts enable row level security;

create policy staff_read on xp_rules for select to authenticated using (is_staff());
create policy staff_read on ranks for select to authenticated using (is_staff());
create policy staff_read on xp_ledger for select to authenticated using (is_staff());
create policy staff_read on challenges for select to authenticated using (is_staff());
create policy staff_read on challenge_completions for select to authenticated using (is_staff());
create policy own_or_admin_read on bonus_payouts for select to authenticated
  using (staff_id = auth.uid() or has_role('{}'));

create policy admin_write on xp_rules for all to authenticated using (has_role('{}')) with check (has_role('{}'));
create policy admin_write on ranks for all to authenticated using (has_role('{}')) with check (has_role('{}'));
create policy admin_write on challenges for all to authenticated using (has_role('{}')) with check (has_role('{}'));
create policy admin_update on bonus_payouts for update to authenticated using (has_role('{}')) with check (has_role('{}'));

revoke all on xp_rules, ranks, xp_ledger, challenges, challenge_completions, bonus_payouts from anon;
revoke execute on function award_xp(uuid, text, text, uuid, uuid, uuid, uuid, int, text, uuid),
                           check_challenges(), run_daily() from anon, public, authenticated;
revoke execute on function challenge_metric(text, uuid, date, date), prepare_monthly_bonuses(date) from anon, public;
