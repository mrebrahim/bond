-- =====================================================================
-- Migration 007: تقفيل (من Supabase security advisor)
-- - دوال الـ triggers مش لازم تتنادى من الـ API خالص
-- - anon مالوش execute على أي دالة (حتى اللي اتعملت بعد 003)
-- - search_path ثابت للدوال اللي مكانش ليها
-- =====================================================================

do $$
declare f record;
begin
  for f in
    select p.oid::regprocedure as sig
      from pg_proc p join pg_namespace n on n.oid = p.pronamespace
     where n.nspname = 'public' and p.prorettype = 'trigger'::regtype
  loop
    execute format('revoke execute on function %s from anon, authenticated, public', f.sig);
  end loop;
  for f in
    select p.oid::regprocedure as sig
      from pg_proc p join pg_namespace n on n.oid = p.pronamespace
      left join pg_depend d on d.objid = p.oid and d.deptype = 'e'
     where n.nspname = 'public' and d.objid is null
  loop
    execute format('revoke execute on function %s from anon, public', f.sig);
  end loop;
end $$;

alter function cairo_today() set search_path = public;
alter function setting_num(text) set search_path = public;
alter function setting_text(text) set search_path = public;
alter function trg_block_mutation() set search_path = public;
alter function checklist_missing(text[], text, uuid, uuid) set search_path = public;
