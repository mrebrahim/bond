-- =====================================================================
-- Migration 010: تنبيهات الفريق بالإيميل، والعملاء بالواتساب
-- - staff.email (من auth.users) — add_staff بيملاه
-- - v_alert_outbox: عمود channel ('whatsapp' للعميل · 'email' للفريق) + email
--   جمهور 'support' بقى لموظفين الدعم (بدل رقم support_wa)
-- =====================================================================

alter table staff add column if not exists email text;
update staff s set email = u.email from auth.users u where u.id = s.id and s.email is null;

create or replace function add_staff(p_email text, p_name text, p_roles text[], p_phone text default null)
returns uuid language plpgsql security definer set search_path = public, auth as $$
declare v_id uuid; v_email text := lower(btrim(p_email));
begin
  perform require_role(array[]::text[]);
  select id into v_id from auth.users where lower(email) = v_email;
  if v_id is null then
    raise exception 'مفيش حساب بالإيميل % — اعمل الحساب الأول', p_email;
  end if;
  if p_phone is not null and p_phone !~ '^20[0-9]{10}$' then
    raise exception 'رقم الواتساب لازم يبقى بالصيغة 20XXXXXXXXXX (12 رقم)';
  end if;
  insert into staff (id, name, roles, phone_wa, email)
  values (v_id, p_name, p_roles, p_phone, v_email)
  on conflict (id) do update set name = excluded.name, roles = excluded.roles, email = excluded.email,
                                 phone_wa = coalesce(excluded.phone_wa, staff.phone_wa), active = true;
  return v_id;
end $$;

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
   and ((r.channel = 'whatsapp' and coalesce(r.phone, '') <> '') or (r.channel = 'email' and coalesce(r.email, '') <> ''))
 order by (a.priority = 'urgent') desc, a.id;

-- الـ view ده لـ n8n بس (service role)
revoke all on v_alert_outbox from anon, authenticated;
