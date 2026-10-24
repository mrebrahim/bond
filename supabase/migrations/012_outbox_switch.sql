-- =====================================================================
-- Migration 012: مفتاح إيقاف للرسايل (outbox_enabled)
-- لو false: v_alert_outbox بيرجع فاضي، فـ n8n مبيبعتش أي حاجة (أيًا كانت نسخة الـ workflow)
-- =====================================================================
insert into settings (key, value, description_ar) values
  ('outbox_enabled', 'false', 'إرسال الرسايل (واتساب/إيميل) شغال؟ true / false — اقفله فورًا لو فيه رسايل بتتكرر')
on conflict (key) do update set value = 'false';

create or replace view v_alert_outbox with (security_invoker = on) as
select a.id as alert_id, a.alert_key, a.audience, a.priority, a.payload, a.created_at,
       a.client_id, cl.name as client_name, cl.code as client_code, cl.phone_wa as client_phone,
       a.cycle_id, a.session_id, a.deliverable_id,
       r.phone, r.recipient_name, r.email, r.channel
  from alerts a
  left join clients cl on cl.id = a.client_id
  cross join lateral (
    select cl.phone_wa as phone, cl.name as recipient_name, null::text as email, 'whatsapp'::text as channel
     where a.audience = 'client'
    union all
    select st.phone_wa, st.name, st.email, 'email'
      from staff st
     where st.active and (
            (a.audience = 'account_manager' and st.id = cl.account_manager_id)
         or (a.audience = 'account_manager' and cl.account_manager_id is null and 'support' = any(st.roles))
         or (a.audience in ('sales','admin','finance','media_lead','support') and a.audience = any(st.roles))
         or (a.audience = 'staff' and st.id = a.recipient_staff_id))
  ) r
 where a.status = 'queued'
   and coalesce(setting_text('outbox_enabled'), 'false') = 'true'
   and ((r.channel = 'whatsapp' and coalesce(r.phone, '') <> '') or (r.channel = 'email' and coalesce(r.email, '') <> ''))
 order by (a.priority = 'urgent') desc, a.id;

revoke all on v_alert_outbox from anon, authenticated;
