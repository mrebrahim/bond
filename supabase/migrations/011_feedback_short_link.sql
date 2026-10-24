-- =====================================================================
-- Migration 011: لينك تقييم قصير لكل ريل/تصميم
--   https://<app>/f/<short_code> → فورم جوجل متملي فيه كود العميل ورقم الريل
-- =====================================================================

alter table deliverables add column if not exists short_code text unique
  default substr(replace(gen_random_uuid()::text, '-', ''), 1, 8);
update deliverables set short_code = substr(replace(gen_random_uuid()::text, '-', ''), 1, 8) where short_code is null;
alter table deliverables alter column short_code set not null;

insert into settings (key, value, description_ar) values
  ('feedback_form_url', '"https://forms.gle/xUTj4TopqgZMB7St9"',
   'لينك فورم التقييم (بيتستخدم لو اللينك المتملي مسبقًا لسه متحطش)'),
  ('feedback_form_prefill_url', '""',
   'لينك الفورم المتملي مسبقًا: من الفورم ← ⋮ ← Get pre-filled link، اكتب CLIENTCODE في "كود العميل" و REELID في "رقم الريل" وانسخ اللينك هنا')
on conflict (key) do nothing;

-- بيتنادى من غير login (العميل اللي بيفتح اللينك) — بيرجّع لينك الفورم بس
create or replace function feedback_redirect(p_code text) returns text
language sql stable security definer set search_path = public as $$
  select case when coalesce(setting_text('feedback_form_prefill_url'), '') <> ''
              then replace(replace(setting_text('feedback_form_prefill_url'), 'CLIENTCODE', c.code), 'REELID', d.id::text)
              else setting_text('feedback_form_url') end
    from deliverables d join clients c on c.id = d.client_id
   where d.short_code = p_code
$$;

revoke execute on function feedback_redirect(text) from public;
grant execute on function feedback_redirect(text) to anon, authenticated;
