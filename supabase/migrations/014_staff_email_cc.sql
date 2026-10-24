-- =====================================================================
-- Migration 014: نسخة (CC) من كل إيميلات التنبيهات
-- =====================================================================
insert into settings (key, value, description_ar) values
  ('staff_email_cc', '"engyemad@digitalsolutionegy.com, ibrahim@digitalsolutionegy.com"',
   'إيميلات بتاخد نسخة (CC) من كل تنبيهات الفريق — افصل بينهم بفاصلة')
on conflict (key) do nothing;
