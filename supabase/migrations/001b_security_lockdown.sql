-- =====================================================================
-- Migration 001b: قفل أمان مؤقت لحد ما 003_roles_rls.sql يتطبق
-- - RLS شغال على كل الجداول (من غير policies = ممنوع على أي حد غير service_role)
-- - الـ views بتشتغل بصلاحيات اللي بيقرا (security_invoker) علشان متعديش الـ RLS
-- - anon مالوش أي صلاحية، والدوال الداخلية مش متاحة من الـ API
-- - policy واحدة بس: الموظف يقرا صفه في staff (علشان الـ login والتحويل حسب الدور)
-- كل اللي هنا idempotent ومتوافق مع إن 003 يضيف policies فوقه.
-- =====================================================================

do $$
declare t text;
begin
  for t in select tablename from pg_tables where schemaname = 'public' loop
    execute format('alter table public.%I enable row level security', t);
  end loop;
  for t in select viewname from pg_views where schemaname = 'public' loop
    execute format('alter view public.%I set (security_invoker = on)', t);
  end loop;
end $$;

revoke all on all tables in schema public from anon;
revoke all on all sequences in schema public from anon;
revoke execute on all functions in schema public from anon, public;

-- دوال داخلية (بتتنادى من جوه الداتابيز أو من n8n بالـ service role بس)
revoke execute on function
  queue_alert(text, text, text[], uuid, jsonb, uuid, uuid, uuid, uuid, text),
  create_cycle(uuid, date),
  close_expired_cycles(),
  renew_due_subscriptions(),
  generate_content_slots(int),
  assign_ready_content(),
  mark_missed_slots(),
  generate_alerts(),
  generate_session_reminders(),
  run_daily()
from authenticated;

drop policy if exists staff_read_self_bootstrap on staff;
create policy staff_read_self_bootstrap on staff
  for select to authenticated using (id = auth.uid());
