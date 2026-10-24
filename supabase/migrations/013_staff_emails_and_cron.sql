-- =====================================================================
-- Migration 013: إيميلات الفريق من Supabase نفسه (من غير n8n) + الجدولة جوه الداتابيز
-- - v_alert_outbox بقى للواتساب بس (العملاء) — n8n
-- - v_staff_email_outbox + claim_staff_emails(): الـ Edge Function "staff-emails" بتقراهم وتبعت
-- - pg_cron: الإيميلات كل دقيقة · run_hourly كل ساعة · run_daily 00:05 القاهرة (22:05 UTC)
-- =====================================================================

insert into settings (key, value, description_ar) values
  ('email_enabled', 'true', 'إيميلات التنبيهات للفريق شغالة؟ true / false')
on conflict (key) do nothing;

-- ---- الواتساب (العملاء) بس ----
create or replace view v_alert_outbox with (security_invoker = on) as
select a.id as alert_id, a.alert_key, a.audience, a.priority, a.payload, a.created_at,
       a.client_id, cl.name as client_name, cl.code as client_code, cl.phone_wa as client_phone,
       a.cycle_id, a.session_id, a.deliverable_id,
       cl.phone_wa as phone, cl.name as recipient_name, null::text as email, 'whatsapp'::text as channel
  from alerts a
  join clients cl on cl.id = a.client_id
 where a.status = 'queued' and a.audience = 'client'
   and coalesce(setting_text('outbox_enabled'), 'false') = 'true'
   and coalesce(cl.phone_wa, '') <> ''
 order by (a.priority = 'urgent') desc, a.id;

-- ---- الإيميلات (الفريق) ----
create or replace view v_staff_email_outbox with (security_invoker = on) as
select a.id as alert_id, a.alert_key, a.audience, a.priority, a.payload, a.created_at,
       a.client_id, cl.name as client_name, cl.code as client_code,
       st.name as recipient_name, st.email
  from alerts a
  left join clients cl on cl.id = a.client_id
  join staff st on st.active and coalesce(st.email, '') <> '' and st.email not like '%.local' and (
          (a.audience = 'account_manager' and st.id = cl.account_manager_id)
       or (a.audience = 'account_manager' and cl.account_manager_id is null and 'support' = any(st.roles))
       or (a.audience in ('sales','admin','finance','media_lead','support') and a.audience = any(st.roles))
       or (a.audience = 'staff' and st.id = a.recipient_staff_id))
 where a.status = 'queued' and a.audience <> 'client'
   and coalesce(setting_text('email_enabled'), 'true') = 'true';

-- بيحجز الإيميلات (sent) قبل الإرسال — مستحيل إيميل يتبعت مرتين
create or replace function claim_staff_emails(p_limit int default 30)
returns table (alert_id bigint, alert_key text, priority text, payload jsonb, client_id uuid,
               client_name text, recipient_name text, email text)
language sql volatile security definer set search_path = public as $$
  with batch as (
    select * from v_staff_email_outbox o
     where o.alert_id in (select distinct x.alert_id from v_staff_email_outbox x
                           order by x.alert_id limit p_limit)),
  claimed as (
    update alerts a set status = 'sent', sent_at = now()
     where a.id in (select b.alert_id from batch b) and a.status = 'queued'
    returning a.id)
  select b.alert_id, b.alert_key, b.priority, b.payload, b.client_id, b.client_name, b.recipient_name, b.email
    from batch b where b.alert_id in (select id from claimed)
$$;

create or replace function mark_alert_failed(p_alert bigint, p_error text)
returns void language sql security definer set search_path = public as $$
  update alerts set status = 'failed', error = left(p_error, 500) where id = p_alert
$$;

revoke all on v_alert_outbox, v_staff_email_outbox from anon, authenticated;
revoke execute on function claim_staff_emails(int), mark_alert_failed(bigint, text) from anon, authenticated, public;

-- ---- الجدولة (Supabase بس) ----
do $$
begin
  if exists (select 1 from pg_available_extensions where name = 'pg_cron')
     and exists (select 1 from pg_available_extensions where name = 'pg_net') then
    create extension if not exists pg_cron;
    create extension if not exists pg_net;

    perform cron.unschedule(jobname) from cron.job where jobname in ('dse-daily', 'dse-hourly', 'dse-staff-emails');
    perform cron.schedule('dse-daily', '5 22 * * *', 'select public.run_daily()');
    perform cron.schedule('dse-hourly', '2 * * * *', 'select public.run_hourly()');
    perform cron.schedule('dse-staff-emails', '* * * * *', $job$
      select net.http_post(
        url := 'https://izpvbgzwiwyaoctdroxr.supabase.co/functions/v1/staff-emails',
        headers := jsonb_build_object('Content-Type', 'application/json',
          'Authorization', 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Iml6cHZiZ3p3aXd5YW9jdGRyb3hyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTI4NDMzNTcsImV4cCI6MjEwODQxOTM1N30.ipId703OimV8EfIypvli0ll3RXFNu7A0B8LtWTvLnB4'),
        body := '{}'::jsonb,
        timeout_milliseconds := 30000)
      where exists (select 1 from public.v_staff_email_outbox)
    $job$);
  end if;
end $$;
