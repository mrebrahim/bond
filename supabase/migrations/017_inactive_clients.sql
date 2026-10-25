-- =====================================================================
-- 017: تنبيه "العميل بقاله فترة مابيصورش"
-- عنده باقة شغالة وفيها رصيد، وآخر تصوير (أو بداية الباقة) من أكتر من client_inactive_days يوم،
-- ومفيش جلسة محجوزة جاية → تنبيه للسيلز والـ AM (والإيميل)
-- ومعاه حالة المحتوى: فاضل كام ريل جاهز للنشر وأول ميعاد نشر فاضي — لو المحتوى خلص يبقى urgent
-- =====================================================================

insert into settings (key, value, description_ar) values
  ('client_inactive_days', '21', 'العميل مصورش من الأيام دي وعنده رصيد ومفيش حجز جاي = تنبيه للسيلز')
on conflict (key) do nothing;

create or replace function check_inactive_clients() returns int
language plpgsql security definer set search_path = public as $$
declare r record; n int := 0; v_ready int; v_pipe int; v_gap date;
begin
  for r in
    with bal as (
      select cycle_id, client_id, string_agg(name_ar || ' ' || trim_scale(remaining)::text, ' · ') as remaining
        from v_cycle_balance
       where cycle_status = 'active' and unit_kind in ('count','hours') and remaining > 0
       group by cycle_id, client_id)
    select b.*, cy.start_date,
           (select max(s.actual_end) from studio_sessions s where s.client_id = b.client_id and s.status = 'done') as last_done
      from bal b join cycles cy on cy.id = b.cycle_id
  loop
    continue when greatest(coalesce((r.last_done at time zone 'Africa/Cairo')::date, r.start_date), r.start_date)
                  > cairo_today() - setting_num('client_inactive_days')::int;
    continue when exists (select 1 from studio_sessions s where s.client_id = r.client_id
                           and s.status = 'booked' and s.booked_start > now());
    select coalesce(sum(ready), 0), coalesce(sum(in_pipeline), 0), min(first_gap_date)
      into v_ready, v_pipe, v_gap from v_content_runway where client_id = r.client_id;
    n := n + queue_alert('client_inactive',
      'inactive:' || r.client_id || ':' || coalesce(r.last_done::date::text, 'never:' || r.cycle_id),
      array['sales','account_manager'], r.client_id,
      jsonb_build_object('last_session', r.last_done, 'cycle_start', r.start_date, 'remaining', r.remaining,
                         'days', cairo_today() - greatest(coalesce((r.last_done at time zone 'Africa/Cairo')::date, r.start_date), r.start_date),
                         'ready', v_ready, 'in_pipeline', v_pipe, 'first_gap_date', v_gap,
                         'content_out', v_ready = 0),
      r.cycle_id, null, null, null, case when v_ready = 0 then 'urgent' else 'normal' end);
  end loop;
  return n;
end $$;

revoke execute on function check_inactive_clients() from public, anon, authenticated;

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
  r := r || jsonb_build_object('inactive',   check_inactive_clients());
  r := r || jsonb_build_object('challenges', check_challenges());
  if extract(day from cairo_today()) = 1 then
    r := r || jsonb_build_object('bonuses', prepare_monthly_bonuses());
  end if;
  return r;
end $$;
