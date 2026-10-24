-- =====================================================================
-- Migration 005: إضافة الفريق
-- اعمل الحساب في Supabase Auth (أو دعوة من /settings/team)، وبعدين:
--   select add_staff('email', 'الاسم', '{studio,editor}', '2010XXXXXXXX');
-- =====================================================================

create or replace function add_staff(p_email text, p_name text, p_roles text[], p_phone text default null)
returns uuid language plpgsql security definer set search_path = public, auth as $$
declare v_id uuid;
begin
  perform require_role(array[]::text[]);   -- الأدمن أو service_role بس
  select id into v_id from auth.users where lower(email) = lower(btrim(p_email));
  if v_id is null then
    raise exception 'مفيش حساب بالإيميل % — اعمل الحساب الأول', p_email;
  end if;
  if p_phone is not null and p_phone !~ '^20[0-9]{10}$' then
    raise exception 'رقم الواتساب لازم يبقى بالصيغة 20XXXXXXXXXX (12 رقم)';
  end if;
  insert into staff (id, name, roles, phone_wa)
  values (v_id, p_name, p_roles, p_phone)
  on conflict (id) do update set name = excluded.name, roles = excluded.roles,
                                 phone_wa = coalesce(excluded.phone_wa, staff.phone_wa), active = true;
  return v_id;
end $$;

revoke execute on function add_staff(text, text, text[], text) from anon, public;
grant execute on function add_staff(text, text, text[], text) to authenticated;

-- الـ AM الافتراضي للعملاء الجداد = أول موظف نشط عنده account_manager (إنجي)
create or replace function trg_client_default_am() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.account_manager_id is null then
    select id into new.account_manager_id from staff
     where active and 'account_manager' = any(roles) order by created_at limit 1;
  end if;
  return new;
end $$;
create trigger client_default_am before insert on clients
  for each row execute function trg_client_default_am();
