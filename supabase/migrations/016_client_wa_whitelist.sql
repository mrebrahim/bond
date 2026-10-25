-- =====================================================================
-- 016: الواتساب للعميل — الأوتوماتيك يبقى 3 رسايل بس
-- - السكربت اتبعت (script_sent) · ميعاد الاستوديو اتحجز (session_booked) · البوست نزل (published)
-- - أي تنبيه تاني للعميل بيتعلّم skipped ومبيتبعتش (التذكيرات، الرصيد، الفواتير، ...)
-- - رسايل المانوال اللي الفريق بيبعتها بزرار زي ما هي
-- - التقييم الوحش بيروح إيميل للفريق (client_unhappy — موجود من 001 / 013)
-- - wa_group_members: أرقام الفريق اللي بتتضاف لأي جروب عميل جديد
-- =====================================================================

insert into settings (key, value, description_ar) values
  ('client_wa_auto_keys', to_jsonb('script_sent,session_booked,published'::text),
   'الرسايل الأوتوماتيك المسموح تتبعت لجروب العميل (مفصولة بفاصلة) — أي حاجة تانية مبتتبعتش'),
  ('wa_group_members', to_jsonb('201050858834,201284816199,201224186293,201276530062'::text),
   'أرقام الفريق اللي بتتضاف لجروب أي عميل جديد (إنجي، مينا، أندرو إسحاق، أندرو أيمن)')
on conflict (key) do nothing;

-- ---- رسالة "تم الحجز" — مع الحجز أو تغيير الميعاد ----
create or replace function trg_session_booked() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.status = 'booked' and (tg_op = 'INSERT' or new.booked_start is distinct from old.booked_start) then
    perform queue_alert('session_booked', 'session_booked:' || new.id || ':' || extract(epoch from new.booked_start)::bigint,
      array['client'], new.client_id,
      jsonb_build_object('start', new.booked_start, 'qty', new.planned_qty, 'type', new.deliverable_type,
                         'studio', (select name from studios where id = new.studio_id),
                         'outfits', new.outfits_required, 'locations', new.locations_required,
                         'rescheduled', tg_op = 'UPDATE'),
      new.cycle_id, new.id);
  end if;
  return new;
end $$;

drop trigger if exists session_booked_alert on studio_sessions;
create trigger session_booked_alert after insert or update of booked_start on studio_sessions
  for each row execute function trg_session_booked();

-- ---- الـ outbox: المسموح بس، والباقي skipped ----
create or replace function claim_wa_messages(p_limit int default 10)
returns table (alert_id bigint, alert_key text, priority text, payload jsonb, client_id uuid, client_name text,
               client_code text, destination text, cycle_id uuid, deliverable_id uuid,
               balance_text text, cycle_end date, feedback_code text)
language sql volatile security definer set search_path = public as $$
  with allowed as (
    select btrim(k) as k from unnest(string_to_array(coalesce(setting_text('client_wa_auto_keys'), ''), ',')) k),
  skip as (
    update alerts a set status = 'skipped', error = 'مش من رسايل الواتساب الأوتوماتيك المسموحة'
     where a.status = 'queued' and a.audience = 'client'
       and not coalesce((a.payload->>'manual')::boolean, false)
       and a.alert_key not in (select k from allowed)
    returning a.id),
  q as (
    select a.*, cl.name as cname, cl.code as ccode,
           coalesce(nullif(cl.wa_group_jid, ''), cl.phone_wa) as dest,
           coalesce((a.payload->>'manual')::boolean, false) as manual
      from alerts a join clients cl on cl.id = a.client_id
     where a.status = 'queued' and a.audience = 'client'
       and (coalesce((a.payload->>'manual')::boolean, false) or a.alert_key in (select k from allowed))
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

revoke execute on function claim_wa_messages(int) from public, anon, authenticated;
grant execute on function claim_wa_messages(int) to service_role;

-- الحد اليومي: 3 أنواع رسايل كلها بتيجي من شغل الفريق — نخليه 6 علشان "تم الحجز" متستناش لبكرة
update settings set value = '6' where key = 'wa_messages_per_day' and value = '3'::jsonb;
