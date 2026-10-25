-- =====================================================================
-- Migration 015: مانوال العميل الجديد + رسايل الواتساب على جروب العميل
-- - clients: wa_group_jid / wa_group_link / contract_path
-- - checklist_runs.client_id + مرحلة 'client_onboarding' + عمود action للبنود
-- - send_client_message(): رسايل المانوال (ترحيب، قواعد، حجز، لبس، عقد، أكسس) — بتروح الجروب
-- - claim_wa_messages(): الـ Edge Function "wa-outbox" بتبعت (بدل n8n) — جروب العميل لو موجود، وإلا الخاص
-- - handle_wa_reply(): ردود العميل (1 / 2 للجلسة، "قرأت" للقواعد) — Edge Function "wa-inbound"
-- =====================================================================

alter table clients add column if not exists wa_group_jid text;
alter table clients add column if not exists wa_group_link text;
alter table clients add column if not exists contract_path text;

alter table checklist_runs add column if not exists client_id uuid references clients(id) on delete cascade;
alter table checklist_runs drop constraint if exists checklist_runs_check;
alter table checklist_runs add constraint checklist_runs_target_check
  check (session_id is not null or deliverable_id is not null or client_id is not null);
create unique index if not exists checklist_runs_client_uq on checklist_runs(item_id, client_id) where client_id is not null;

alter table checklist_templates drop constraint if exists checklist_templates_stage_check;
alter table checklist_templates add constraint checklist_templates_stage_check check (stage in
  ('onboarding','script','pre_shoot','shoot','post_shoot','editing','design','publishing','client_onboarding'));
alter table checklist_template_items add column if not exists action text;

-- ---- المانوال ----
do $$
declare t uuid;
begin
  insert into checklist_templates (stage, applies_to, name) values ('client_onboarding', null, 'مانوال العميل الجديد') returning id into t;
  insert into checklist_template_items (template_id, sort, text_ar, is_required, client_facing, role, action) values
    (t,  1, 'عمل جروب واتساب للعميل (العميل + الـ AM + تيم ليدر الميديا)',            true,  true,  'account_manager', 'create_group'),
    (t,  2, 'رسالة ترحيب وتعريف بالفريق في الجروب',                                  false, true,  'account_manager', 'send_welcome'),
    (t,  3, 'إرسال قواعد التعامل في الجروب',                                          true,  true,  'account_manager', 'send_rules'),
    (t,  4, 'العميل أكد إنه قرأ القواعد (رد بـ "قرأت")',                              true,  true,  'account_manager', 'rules_ack'),
    (t,  5, 'استلام صورة التعاقد ورفعها على السيستم',                                 true,  true,  'sales',           'upload_contract'),
    (t,  6, 'الدفعة الأولى اتسجلت والباقة اتفعّلت',                                   true,  false, 'finance',         null),
    (t,  7, 'طلب الأكسس على صفحات السوشيال (Partner على Meta Business — من غير باسوردات)', true, true, 'account_manager', 'send_access_request'),
    (t,  8, 'الأكسس اتاخد فعلًا على كل المنصات اللي في الباقة',                      true,  false, 'social_media',    null),
    (t,  9, 'استلام اللوجو والهوية البصرية (ألوان وخطوط)',                           true,  true,  'account_manager', null),
    (t, 10, 'تحديد مين عند العميل بيوافق على المحتوى ورقمه',                          true,  true,  'account_manager', null),
    (t, 11, 'الاتفاق على جدول النشر وتسجيله في البروفايل',                            true,  true,  'account_manager', null),
    (t, 12, 'حسابات مرجعية / منافسين العميل بيحبهم',                                  false, true,  'account_manager', null),
    (t, 13, 'أول سكربتات اتبعتت للموافقة قبل أول تصوير',                             false, true,  'scriptwriter',    null),
    (t, 14, 'رسالة حجز أول ميعاد تصوير',                                              true,  true,  'support',         'send_booking'),
    (t, 15, 'تنبيه: يجيب أكتر من لبس لو هيصور أكتر من ريل',                          true,  true,  'support',         'send_outfits');
end $$;

insert into settings (key, value, description_ar) values
  ('client_rules_text', to_jsonb(E'📌 *قواعد التعامل* — من فضلك اقراها كويس\n\n1) مواعيد التصوير بتتحجز مسبقًا، والتأكيد بيكون بالرد *1* على رسالة التذكير.\n2) الإلغاء أو التأجيل قبل الميعاد بأقل من 24 ساعة بيتخصم من رصيد الباقة.\n3) عدم الحضور من غير إبلاغ بيتخصم من الرصيد.\n4) لو هتصور أكتر من ريل هات أكتر من لبس (لبس لكل 3 ريلز تقريبًا).\n5) السكربتات بتتبعت هنا للموافقة قبل التصوير.\n6) كل ريل ليه تعديلين بعد التسليم.\n7) الرصيد اللي ماتستخدمش في مدة الباقة مش بيترحّل للباقة الجاية.\n8) الباقة بتتفعل بعد الدفع.\n9) متبعتش باسوردات في الجروب — الأكسس بيكون بإضافتنا Partner.\n\nبعد ما تقرا القواعد اكتب *قرأت* ✅'::text),
   'نص قواعد التعامل اللي بيتبعت لجروب العميل الجديد'),
  ('wa_webhook_token', to_jsonb(replace(gen_random_uuid()::text, '-', '')),
   'كود سري لاستقبال ردود الواتساب (متغيّروش إلا لو هتظبط الـ webhook تاني)'),
  ('wa_messages_per_day', '3', 'أقصى عدد رسايل أوتوماتيك لنفس العميل في اليوم (الرسايل اللي الفريق بيبعتها بإيده مش بتتحسب)')
on conflict (key) do nothing;

-- ---- رسايل المانوال (من زرار في بروفايل العميل) ----
create or replace function send_client_message(p_client uuid, p_key text) returns int
language plpgsql security definer set search_path = public as $$
declare c clients; v_team text; v_payload jsonb; n int; v_action text;
begin
  perform require_role(array['sales','support','account_manager','media_lead']);
  if p_key not in ('welcome','client_rules','booking_request','outfits_reminder','contract_request','access_request') then
    raise exception 'نوع رسالة غير معروف';
  end if;
  select * into c from clients where id = p_client;
  if c.id is null then raise exception 'العميل مش موجود'; end if;
  if coalesce(c.wa_group_jid, '') = '' and coalesce(c.phone_wa, '') = '' then
    raise exception 'العميل مالوش جروب ولا رقم واتساب';
  end if;

  select string_agg(st.name || ' (' || case when 'media_lead' = any(st.roles) then 'الميديا'
                                            when st.id = c.account_manager_id then 'Account Manager' else 'الفريق' end || ')', '، ')
    into v_team from staff st
   where st.active and (st.id = c.account_manager_id or 'media_lead' = any(st.roles)) and st.email not like '%.local';

  v_payload := jsonb_build_object('manual', true, 'client_name', c.name, 'team', v_team,
    'rules', setting_text('client_rules_text'),
    'preferred_day', c.preferred_shoot_day, 'reels_per_session', c.reels_per_session_avg);

  n := queue_alert(p_key, 'manual:' || p_key || ':' || p_client || ':' || extract(epoch from clock_timestamp()),
                   array['client'], p_client, v_payload, null, null, null, null, 'urgent');

  -- علّم البند بتاعه في المانوال
  v_action := case p_key when 'welcome' then 'send_welcome' when 'client_rules' then 'send_rules'
                         when 'booking_request' then 'send_booking' when 'outfits_reminder' then 'send_outfits'
                         when 'access_request' then 'send_access_request' end;
  if v_action is not null then
    insert into checklist_runs (item_id, client_id, done_by)
    select i.id, p_client, auth.uid() from checklist_template_items i where i.action = v_action
    on conflict do nothing;
  end if;
  return n;
end $$;

-- ---- الإرسال (Edge Function wa-outbox) ----
create or replace function claim_wa_messages(p_limit int default 10)
returns table (alert_id bigint, alert_key text, priority text, payload jsonb, client_id uuid, client_name text,
               client_code text, destination text, cycle_id uuid, deliverable_id uuid,
               balance_text text, cycle_end date, feedback_code text)
language sql volatile security definer set search_path = public as $$
  with q as (
    select a.*, cl.name as cname, cl.code as ccode,
           coalesce(nullif(cl.wa_group_jid, ''), cl.phone_wa) as dest,
           coalesce((a.payload->>'manual')::boolean, false) as manual
      from alerts a join clients cl on cl.id = a.client_id
     where a.status = 'queued' and a.audience = 'client'
       and coalesce(setting_text('outbox_enabled'), 'false') = 'true'
       and coalesce(nullif(cl.wa_group_jid, ''), cl.phone_wa, '') <> ''),
  sent_today as (
    select coalesce(nullif(cl.wa_group_jid, ''), cl.phone_wa) as dest, count(*) as n
      from alerts a join clients cl on cl.id = a.client_id
     where a.audience = 'client' and a.status = 'sent' and a.sent_at > now() - interval '24 hours'
       and not coalesce((a.payload->>'manual')::boolean, false)
     group by 1),
  ranked as (
    select q.*, row_number() over (partition by q.dest, q.manual order by q.id) as rn from q),
  batch as (
    select r.* from ranked r left join sent_today t on t.dest = r.dest
     where r.manual or coalesce(t.n, 0) + r.rn <= coalesce(setting_num('wa_messages_per_day'), 3)
     order by (r.priority = 'urgent') desc, r.id
     limit p_limit),
  claimed as (
    update alerts a set status = 'sent', sent_at = now()
     where a.id in (select id from batch) and a.status = 'queued'
    returning a.id)
  select b.id, b.alert_key, b.priority, b.payload, b.client_id, b.cname, b.ccode, b.dest, b.cycle_id, b.deliverable_id,
         (select string_agg(x.name_ar || ' ' || trim_scale(x.remaining)::text, ' · ')
            from v_cycle_balance x where x.cycle_id = b.cycle_id and x.unit_kind in ('count','hours')),
         (select end_date from cycles where id = b.cycle_id),
         (select short_code from deliverables where id = b.deliverable_id)
    from batch b where b.id in (select id from claimed)
$$;

-- ---- ردود العميل (Edge Function wa-inbound) ----
create or replace function handle_wa_reply(p_chat text, p_sender text, p_text text) returns jsonb
language plpgsql security definer set search_path = public as $$
declare c clients; v_text text := btrim(translate(coalesce(p_text, ''), '٠١٢٣٤٥٦٧٨٩', '0123456789'));
        s studio_sessions; v_conf text;
begin
  -- الجروب بالـ jid، والخاص بالرقم
  if p_chat like '%@g.us' then
    select * into c from clients where wa_group_jid = p_chat limit 1;
  else
    select * into c from clients where phone_wa = split_part(p_chat, '@', 1) limit 1;
  end if;
  if c.id is null then return jsonb_build_object('action', 'unknown_chat'); end if;

  if v_text in ('1', '2') then
    select * into s from studio_sessions
     where client_id = c.id and status = 'booked' and booked_start between now() and now() + interval '48 hours'
     order by booked_start limit 1;
    if s.id is null then return jsonb_build_object('action', 'no_session'); end if;
    v_conf := case when v_text = '1' then 'confirmed' else 'reschedule_requested' end;
    update studio_sessions set confirmation = v_conf where id = s.id;
    if v_conf = 'reschedule_requested' then
      perform queue_alert('reschedule_requested', 'reschedule:' || s.id, '{support,account_manager}', c.id, '{}'::jsonb, s.cycle_id, s.id);
    end if;
    return jsonb_build_object('action', v_conf, 'client_id', c.id, 'reply',
      case when v_conf = 'confirmed' then 'تمام ✅ الميعاد اتأكد — مستنيينك' else 'تمام، هنكلمك دلوقتي نظبط ميعاد تاني 🙏' end);
  end if;

  if v_text ~ '(قرأت|قريت|قرات)' then
    insert into checklist_runs (item_id, client_id, done_by, note)
    select i.id, c.id, null, 'العميل أكد على الواتساب' from checklist_template_items i where i.action = 'rules_ack'
    on conflict do nothing;
    return jsonb_build_object('action', 'rules_ack', 'client_id', c.id, 'reply', 'شكرًا ليك 🙏 كده نبدأ على طول');
  end if;
  return jsonb_build_object('action', 'ignored');
end $$;

-- n8n مبقاش بيبعت واتساب — الـ view القديم بيرجع فاضي علشان أي workflow قديم ميبعتش حاجة
create or replace view v_alert_outbox with (security_invoker = on) as
select a.id as alert_id, a.alert_key, a.audience, a.priority, a.payload, a.created_at,
       a.client_id, cl.name as client_name, cl.code as client_code, cl.phone_wa as client_phone,
       a.cycle_id, a.session_id, a.deliverable_id,
       cl.phone_wa as phone, cl.name as recipient_name, null::text as email, 'whatsapp'::text as channel
  from alerts a
  join clients cl on cl.id = a.client_id
 where false;

revoke execute on function claim_wa_messages(int), handle_wa_reply(text, text, text) from anon, authenticated, public;
revoke execute on function send_client_message(uuid, text) from anon, public;
grant execute on function send_client_message(uuid, text) to authenticated;

-- العقود (Storage) + الجدولة
do $$
begin
  if exists (select 1 from information_schema.tables where table_schema = 'storage' and table_name = 'buckets') then
    insert into storage.buckets (id, name, public) values ('contracts', 'contracts', false) on conflict (id) do nothing;
    execute $p$create policy contracts_read on storage.objects for select to authenticated
              using (bucket_id = 'contracts' and public.has_role('{sales,support,account_manager,finance}'))$p$;
    execute $p$create policy contracts_insert on storage.objects for insert to authenticated
              with check (bucket_id = 'contracts' and public.has_role('{sales,support,account_manager}'))$p$;
  end if;
  if exists (select 1 from pg_extension where extname = 'pg_cron') then
    perform cron.unschedule(jobname) from cron.job where jobname = 'dse-wa-outbox';
    perform cron.schedule('dse-wa-outbox', '* * * * *', $job$
      select net.http_post(
        url := 'https://izpvbgzwiwyaoctdroxr.supabase.co/functions/v1/wa-outbox',
        headers := jsonb_build_object('Content-Type', 'application/json',
          'Authorization', 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Iml6cHZiZ3p3aXd5YW9jdGRyb3hyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTI4NDMzNTcsImV4cCI6MjEwODQxOTM1N30.ipId703OimV8EfIypvli0ll3RXFNu7A0B8LtWTvLnB4'),
        body := '{}'::jsonb, timeout_milliseconds := 30000)
      where coalesce(public.setting_text('outbox_enabled'), 'false') = 'true'
        and exists (select 1 from public.alerts where status = 'queued' and audience = 'client')
    $job$);
  end if;
end $$;
