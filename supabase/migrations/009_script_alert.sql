-- =====================================================================
-- Migration 009: "ابعت للعميل" في السكربتات بيحط رسالة واتساب في الطابور
-- (قبل كده كان بيغيّر الحالة بس). كل نسخة من السكربت بتتبعت مرة واحدة.
-- =====================================================================

create or replace function trg_script_sent() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.status = 'sent_to_client' and (tg_op = 'INSERT' or old.status is distinct from 'sent_to_client') then
    perform queue_alert('script_sent', 'script_sent:' || new.id || ':' || new.revision_count, array['client'], new.client_id,
      jsonb_build_object('script_id', new.id, 'title', new.title, 'body', left(coalesce(new.body, ''), 3500)));
  end if;
  return new;
end $$;

create trigger script_sent after insert or update of status on scripts
  for each row execute function trg_script_sent();

revoke execute on function trg_script_sent() from anon, authenticated, public;
