-- =====================================================================
-- Migration 003: الحسابات والصلاحيات (RLS)
-- - كل الجداول عليها RLS. اللي مش موظف نشط مالوش أي حاجة.
-- - القراية: أي موظف نشط (فريق صغير وكله محتاج يشوف العملاء).
-- - الكتابة: حسب مصفوفة الصلاحيات في PRD §2.
-- - الجداول اللي بتتغير بالـ RPC بس (الاشتراكات، الدورات، الأرصدة، الاستهلاك) مالهاش policy كتابة.
-- - الـ views بـ security_invoker علشان تحترم الـ RLS.
-- - n8n بيستخدم service_role (بيعدّي الـ RLS).
-- =====================================================================

-- ---- أدوات الصلاحيات ----
create or replace function is_staff() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from staff where id = auth.uid() and active)
$$;

create or replace function has_role(p_roles text[]) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from staff where id = auth.uid() and active
                   and ('admin' = any(roles) or roles && p_roles))
$$;

create or replace function my_roles() returns text[]
language sql stable security definer set search_path = public as $$
  select coalesce((select roles from staff where id = auth.uid() and active), '{}')
$$;

-- check_in_session في 001 مكانش بيشيك على الدور
create or replace function check_in_session(p_session uuid) returns void
language plpgsql security definer set search_path = public as $$
begin
  perform require_role(array['studio','support','account_manager','media_lead']);
  update studio_sessions set status = 'checked_in', checked_in_at = now(),
         actual_start = coalesce(actual_start, now())
   where id = p_session and status = 'booked';
  if not found then raise exception 'الجلسة مش في حالة "محجوز"'; end if;
end $$;

-- تأكيد الحضور من الواجهة (n8n بيعمله بالـ service role من رد الواتساب)
create or replace function set_session_confirmation(p_session uuid, p_confirmation text) returns void
language plpgsql security definer set search_path = public as $$
begin
  perform require_role(array['studio','support','account_manager','media_lead']);
  if p_confirmation not in ('pending','confirmed','reschedule_requested') then
    raise exception 'حالة تأكيد غير معروفة';
  end if;
  update studio_sessions set confirmation = p_confirmation where id = p_session;
end $$;

-- ---- RLS على كل الجداول ----
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

-- ---- القراية: أي موظف نشط ----
do $$
declare t text;
begin
  foreach t in array array[
    'settings','staff','clients','deliverable_types','plans','plan_items','subscriptions','subscription_items',
    'cycles','cycle_entitlements','studios','scripts','studio_sessions','deliverables','deliverable_stage_history',
    'consumption_log','invoices','payments','subscription_events','publishing_schedule','content_slots',
    'checklist_templates','checklist_template_items','checklist_runs','feedback'] loop
    execute format('create policy staff_read on public.%I for select to authenticated using (is_staff())', t);
  end loop;
end $$;

-- ---- إعدادات وقوالب: الأدمن بس ----
do $$
declare t text;
begin
  foreach t in array array['settings','plans','plan_items','studios','checklist_templates','checklist_template_items','staff'] loop
    execute format($f$create policy admin_write on public.%I for all to authenticated
                      using (has_role('{}')) with check (has_role('{}'))$f$, t);
  end loop;
end $$;

-- ---- العملاء + جدول النشر ----
create policy clients_insert on clients for insert to authenticated
  with check (has_role('{sales,support,account_manager}'));
create policy clients_update on clients for update to authenticated
  using (has_role('{sales,support,account_manager}')) with check (has_role('{sales,support,account_manager}'));

create policy schedule_write on publishing_schedule for all to authenticated
  using (has_role('{sales,support,account_manager}')) with check (has_role('{sales,support,account_manager}'));

-- ---- الجلسات (الحجز والتعديل؛ الحالات بالـ RPC) ----
create policy sessions_insert on studio_sessions for insert to authenticated
  with check (has_role('{support,account_manager,studio,media_lead}') and status = 'booked');
create policy sessions_update on studio_sessions for update to authenticated
  using (has_role('{support,account_manager,studio,media_lead}'))
  with check (has_role('{support,account_manager,studio,media_lead}'));

-- ---- الـ deliverables (المرحلة، المسؤول، اللينك، العنوان) ----
create policy deliverables_insert on deliverables for insert to authenticated
  with check (has_role('{account_manager,studio,editor,designer,social_media,media_lead}'));
create policy deliverables_update on deliverables for update to authenticated
  using (has_role('{account_manager,studio,editor,designer,social_media,media_lead}'))
  with check (has_role('{account_manager,studio,editor,designer,social_media,media_lead}'));

-- ---- السكربتات ----
create policy scripts_write on scripts for all to authenticated
  using (has_role('{support,account_manager,media_lead,scriptwriter}'))
  with check (has_role('{support,account_manager,media_lead,scriptwriter}'));

-- التعديل بعد الموافقة بيزوّد revision_count
create or replace function trg_script_before() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if tg_op = 'UPDATE' then
    if old.status = 'approved' and (new.body is distinct from old.body or new.status <> 'approved') then
      new.revision_count := old.revision_count + 1;
      if new.status = 'approved' then new.status := 'internal_review'; end if;
      new.approved_at := null;
    end if;
    if new.status = 'changes_requested' and old.status <> 'changes_requested' then
      new.revision_count := new.revision_count + 1;
    end if;
  end if;
  if new.status = 'sent_to_client' and (tg_op = 'INSERT' or old.status <> 'sent_to_client') then
    new.sent_at := now();
  end if;
  if new.status = 'approved' and (tg_op = 'INSERT' or old.status <> 'approved') then
    new.approved_at := now();
  end if;
  return new;
end $$;
create trigger script_before before insert or update on scripts
  for each row execute function trg_script_before();

-- ---- المانوال: كل موظف يعلّم البنود باسمه ----
create policy checklist_insert on checklist_runs for insert to authenticated
  with check (is_staff() and done_by = auth.uid());
create policy checklist_update on checklist_runs for update to authenticated
  using (is_staff()) with check (is_staff() and done_by = auth.uid());
create policy checklist_delete on checklist_runs for delete to authenticated
  using (done_by = auth.uid() or has_role('{media_lead}'));

-- ---- الفلوس ----
create policy invoices_write on invoices for all to authenticated
  using (has_role('{finance}')) with check (has_role('{finance}'));
create policy payments_insert on payments for insert to authenticated
  with check (has_role('{finance}'));
alter table payments alter column received_by set default auth.uid();

-- ---- التنبيهات: كل واحد يشوف اللي يخصه ----
create policy alerts_read on alerts for select to authenticated using (
  has_role('{}')
  or audience = any(my_roles())
  or recipient_staff_id = auth.uid()
  or (audience = 'account_manager' and exists (
        select 1 from clients c where c.id = alerts.client_id and c.account_manager_id = auth.uid()))
  or (audience = 'client' and has_role('{support,account_manager}'))
);

-- ---- الـ API: anon مالوش حاجة، والدوال الداخلية بالـ service role بس ----
revoke all on all tables in schema public from anon;
revoke all on all sequences in schema public from anon;
revoke execute on all functions in schema public from anon, public;
grant execute on all functions in schema public to authenticated;

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

alter default privileges in schema public revoke all on tables from anon;
alter default privileges in schema public revoke execute on functions from anon, public;
