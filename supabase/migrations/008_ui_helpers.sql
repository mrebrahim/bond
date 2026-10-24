-- =====================================================================
-- Migration 008: دوال وviews للواجهة (علشان مفيش حسابات بيزنس في TypeScript)
-- - preview_booking: الـ preview قبل حفظ الحجز
-- - schedule_package_diff: التحذير اللايف في wizard العميل الجديد
-- - v_dashboard_kpis: كروت الداشبورد
-- - v_slot_board: تقويم النشر بالألوان + شاشة "النهارده"
-- =====================================================================

-- نفس معادلات trg_session_before (من نفس الإعدادات)
create or replace function session_minutes(p_unit text, p_qty numeric) returns int
language sql stable set search_path = public as $$
  select ceil(case when p_unit = 'count'
                   then p_qty * setting_num('minutes_per_reel_max') + setting_num('setup_minutes')
                   else p_qty * 60 + setting_num('setup_minutes') end)::int
$$;

create or replace function session_outfits(p_qty numeric) returns int
language sql stable set search_path = public as $$
  select case when p_qty <= 1 then 1
              else greatest(setting_num('min_outfits_multi'), ceil(p_qty / setting_num('reels_per_outfit')))::int end
$$;

create or replace function session_locations(p_qty numeric) returns int
language sql stable set search_path = public as $$
  select case when p_qty <= 1 then 1 else greatest(2, ceil(p_qty / setting_num('reels_per_location')))::int end
$$;

create or replace function preview_booking(p_client uuid, p_type text, p_qty numeric, p_start timestamptz default null)
returns jsonb language plpgsql stable security definer set search_path = public as $$
declare v_unit text; v_cycle cycles; v_day date; v_avail numeric := 0; v_reserved numeric := 0;
        v_scripts int; v_stock int; v_until date; v_overage boolean;
begin
  if not is_staff() and auth.uid() is not null then raise exception 'غير مصرح'; end if;
  select unit_kind into v_unit from deliverable_types where code = p_type;
  v_day := coalesce((p_start at time zone 'Africa/Cairo')::date, cairo_today());

  select c.* into v_cycle from cycles c
    join cycle_entitlements e on e.cycle_id = c.id and e.deliverable_type = p_type
   where c.client_id = p_client and c.status in ('active','pending_payment')
     and v_day between c.start_date and c.end_date
   order by (c.status = 'active') desc limit 1;

  if v_cycle.id is not null then
    select greatest(granted + bonus - used, 0) into v_avail
      from cycle_entitlements where cycle_id = v_cycle.id and deliverable_type = p_type;
    select coalesce(sum(planned_qty), 0) into v_reserved from studio_sessions
     where cycle_id = v_cycle.id and deliverable_type = p_type and status in ('booked','checked_in');
    select overage_rate is not null into v_overage from subscriptions where id = v_cycle.subscription_id;
  end if;

  select count(*) into v_scripts from scripts sc where sc.client_id = p_client and sc.status = 'approved'
     and not exists (select 1 from deliverables d where d.script_id = sc.id);

  select count(*) into v_stock from deliverables
   where client_id = p_client and deliverable_type = p_type
     and stage in ('approved','filmed','editing','review');

  select slot_date into v_until from (
    select slot_date, row_number() over (order by slot_date) rn from content_slots
     where client_id = p_client and deliverable_type = p_type
       and slot_date >= cairo_today() and status in ('empty','reserved')) u
   where rn = v_stock + coalesce(p_qty, 0)::int;

  return jsonb_build_object(
    'unit', v_unit,
    'cycle_id', v_cycle.id, 'cycle_status', v_cycle.status, 'cycle_end', v_cycle.end_date,
    'available', v_avail - v_reserved, 'remaining', v_avail, 'reserved', v_reserved,
    'enough', coalesce(p_qty, 0) <= v_avail - v_reserved or (v_unit = 'hours' and coalesce(v_overage, false)),
    'duration_minutes', session_minutes(v_unit, coalesce(p_qty, 0)),
    'outfits', case when v_unit = 'count' then session_outfits(coalesce(p_qty, 0)) end,
    'locations', case when v_unit = 'count' then session_locations(coalesce(p_qty, 0)) end,
    'approved_scripts', v_scripts,
    'scripts_short', v_unit = 'count' and v_scripts < coalesce(p_qty, 0),
    'covers_until', v_until);
end $$;

-- p_per_week: {"reel":3,"design":2} · p_items: [{"type":"reel","qty":10}]
create or replace function schedule_package_diff(p_per_week jsonb, p_items jsonb, p_days int)
returns jsonb language sql stable set search_path = public as $$
  select coalesce(jsonb_agg(jsonb_build_object(
           'type', t.code, 'name', t.name_ar, 'per_week', w.per_week, 'package_qty', coalesce(i.qty, 0),
           'needed', round(w.per_week * p_days / 7.0, 1),
           'diff', round(coalesce(i.qty, 0) - w.per_week * p_days / 7.0, 1))), '[]'::jsonb)
    from (select key as type, value::numeric as per_week from jsonb_each_text(p_per_week)) w
    join deliverable_types t on t.code = w.type
    left join (select x.type, x.qty from jsonb_to_recordset(p_items) as x(type text, qty numeric)) i on i.type = w.type
   where w.per_week > 0 and abs(coalesce(i.qty, 0) - w.per_week * p_days / 7.0) >= 1
$$;

-- MRR هنا = الدورات النشطة النهارده (v_mrr_monthly لقطة يوم 1 في الشهر للتقارير)
create or replace view v_dashboard_kpis with (security_invoker = on) as
with live as (
  select coalesce(sum(monthly_value), 0) as mrr, count(distinct client_id) as clients
    from cycles where status = 'active' and cairo_today() between start_date and end_date)
select
  (select mrr from live) as mrr,
  (select clients from live) as active_clients,
  (select round(mrr / nullif(clients, 0), 2) from live) as arpa,
  (select logo_churn_pct from v_retention_monthly order by month desc limit 1) as logo_churn_pct,
  (select nrr_pct from v_retention_monthly order by month desc limit 1) as nrr_pct,
  (select grr_pct from v_retention_monthly order by month desc limit 1) as grr_pct,
  (select coalesce(sum(amount - paid_amount), 0) from invoices
    where status in ('unpaid','partial') and due_date < cairo_today() - setting_num('payment_overdue_days')::int) as overdue_amount,
  (select coalesce(sum(amount - paid_amount), 0) from invoices where status in ('unpaid','partial')) as outstanding_amount,
  (select round(100.0 * sum(forfeited) / nullif(sum(total), 0), 1) from v_utilization_monthly
    where month >= (date_trunc('month', cairo_today()) - interval '3 months')::date) as forfeiture_pct_3m,
  (select coalesce(sum(amount), 0) from payments
    where (paid_at at time zone 'Africa/Cairo')::date >= date_trunc('month', cairo_today())::date) as collected_this_month;

create or replace view v_slot_board with (security_invoker = on) as
with stock as (
  select d.client_id, d.deliverable_type,
         count(*) filter (where d.stage = 'approved'
                            and not exists (select 1 from content_slots s where s.deliverable_id = d.id)) as ready_free,
         count(*) filter (where d.stage in ('filmed','editing','review','brief','designing')) as pipeline
    from deliverables d group by 1, 2),
empty_rank as (
  select id, row_number() over (partition by client_id, deliverable_type order by slot_date) as rn
    from content_slots where status = 'empty' and slot_date >= cairo_today())
select s.id as slot_id, s.client_id, cl.name as client_name, cl.code as client_code,
       s.deliverable_type, s.slot_date, s.publish_time, s.status, s.deliverable_id,
       d.title as deliverable_title, d.stage as deliverable_stage, d.post_url,
       case when s.status = 'published' then 'published'
            when s.status = 'reserved' then 'ready'
            when s.status in ('missed_no_content','missed_internal') then 'missed'
            when er.rn <= coalesce(st.ready_free, 0) then 'ready'
            when er.rn <= coalesce(st.ready_free, 0) + coalesce(st.pipeline, 0) then 'in_pipeline'
            else 'empty' end as board_status
  from content_slots s
  join clients cl on cl.id = s.client_id
  left join deliverables d on d.id = s.deliverable_id
  left join empty_rank er on er.id = s.id
  left join stock st on st.client_id = s.client_id and st.deliverable_type = s.deliverable_type
 where s.slot_date between cairo_today() - 7 and cairo_today() + 28;

revoke execute on function preview_booking(uuid, text, numeric, timestamptz),
                           schedule_package_diff(jsonb, jsonb, int),
                           session_minutes(text, numeric), session_outfits(numeric), session_locations(numeric)
  from anon, public;
grant execute on function preview_booking(uuid, text, numeric, timestamptz),
                          schedule_package_diff(jsonb, jsonb, int),
                          session_minutes(text, numeric), session_outfits(numeric), session_locations(numeric)
  to authenticated;
revoke all on v_dashboard_kpis, v_slot_board from anon;
