-- =====================================================================
-- Migration 002: مانوال تيم الميديا + تكميلات للـ schema
-- البنود الإجبارية (is_required) بتقفل المرحلة:
--   complete_session ← onboarding (أول جلسة) + pre_shoot + shoot
--   approved ← editing / design · published ← publishing
-- =====================================================================

-- ---- تكميلات 001 ----
-- تريجر التقييمات بيستخدم location_score (سؤال "قيّم اللوكيشن" في الفورم)
alter table feedback add column if not exists location_score int check (location_score between 1 and 5);

-- كود العميل auto: C001, C002, ...
create sequence if not exists client_code_seq;

create or replace function trg_client_code() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if new.code is null or btrim(new.code) = '' then
    loop
      new.code := 'C' || lpad(nextval('client_code_seq')::text, 3, '0');
      exit when not exists (select 1 from clients where code = new.code);
    end loop;
  end if;
  if new.phone_wa !~ '^20[0-9]{10}$' then
    raise exception 'رقم الواتساب لازم يبقى بالصيغة 20XXXXXXXXXX (12 رقم)';
  end if;
  return new;
end $$;

create trigger client_code before insert or update of code, phone_wa on clients
  for each row execute function trg_client_code();

-- ---- المانوال ----
do $$
declare t uuid;
begin
  -- أول جلسة للعميل
  insert into checklist_templates (stage, applies_to, name) values ('onboarding', null, 'أول جلسة للعميل') returning id into t;
  insert into checklist_template_items (template_id, sort, text_ar, is_required, client_facing, role) values
    (t, 1, 'اتفقنا مع العميل على نبرة الكلام وستايل الفيديوهات', true,  true,  'account_manager'),
    (t, 2, 'استلمنا اللوجو وألوان البراند',                       true,  true,  'account_manager'),
    (t, 3, 'العميل شاف أمثلة من شغلنا ووافق على الستايل',          false, true,  'account_manager'),
    (t, 4, 'شرحنا للعميل سياسة الغياب والإلغاء المتأخر',           true,  true,  'support');

  -- قبل التصوير
  insert into checklist_templates (stage, applies_to, name) values ('pre_shoot', '{reel,trainer_hours}', 'قبل التصوير') returning id into t;
  insert into checklist_template_items (template_id, sort, text_ar, is_required, client_facing, role) values
    (t, 1, 'السكربتات المتوافق عليها جاهزة ومتطبوعة / على التابلت', true,  false, 'studio'),
    (t, 2, 'الكاميرا مشحونة والكروت فاضية',                      true,  false, 'studio'),
    (t, 3, 'الإضاءة والمايكات اتجربت',                            true,  false, 'studio'),
    (t, 4, 'العميل جاب عدد اللبسات المطلوب',                      false, true,  'studio'),
    (t, 5, 'اللوكيشنز متجهزة',                                    false, false, 'studio');

  -- يوم التصوير
  insert into checklist_templates (stage, applies_to, name) values ('shoot', '{reel,trainer_hours}', 'يوم التصوير') returning id into t;
  insert into checklist_template_items (template_id, sort, text_ar, is_required, client_facing, role) values
    (t, 1, 'كل ريل اتصور منه take احتياطي على الأقل',             false, false, 'studio'),
    (t, 2, 'الصوت اتسمع بالسماعة بعد كل take',                    true,  false, 'studio'),
    (t, 3, 'الملفات اتنقلت على الدرايف في فولدر العميل',          true,  false, 'studio'),
    (t, 4, 'العميل اتصور صورة مع الاستوديو (لو موافق)',           false, true,  'studio');

  -- المونتاج (ريلز + فيديوهات المدربين)
  insert into checklist_templates (stage, applies_to, name) values ('editing', '{reel,trainer_video_edit}', 'المونتاج') returning id into t;
  insert into checklist_template_items (template_id, sort, text_ar, is_required, client_facing, role) values
    (t, 1, 'الكابشن على الشاشة مراجَع إملائيًا',                  true,  false, 'editor'),
    (t, 2, 'اللوجو والألوان حسب البراند',                         true,  false, 'editor'),
    (t, 3, 'الصوت متظبط ومفيش موسيقى عليها copyright',            true,  false, 'editor'),
    (t, 4, 'أول 3 ثواني فيها hook',                                false, false, 'editor'),
    (t, 5, 'العميل وافق على النسخة النهائية',                     false, true,  'account_manager');

  -- التصميم
  insert into checklist_templates (stage, applies_to, name) values ('design', '{design}', 'التصميم') returning id into t;
  insert into checklist_template_items (template_id, sort, text_ar, is_required, client_facing, role) values
    (t, 1, 'المقاسات صح للمنصة',                                  true,  false, 'designer'),
    (t, 2, 'النص مراجَع إملائيًا',                                 true,  false, 'designer'),
    (t, 3, 'اللوجو والألوان حسب البراند',                         true,  false, 'designer'),
    (t, 4, 'العميل وافق على التصميم',                             false, true,  'account_manager');

  -- النشر
  insert into checklist_templates (stage, applies_to, name) values ('publishing', '{reel,design}', 'النشر') returning id into t;
  insert into checklist_template_items (template_id, sort, text_ar, is_required, client_facing, role) values
    (t, 1, 'الكابشن والهاشتاجات اتكتبوا',                         true,  false, 'social_media'),
    (t, 2, 'الغلاف (cover) اتختار',                                false, false, 'social_media'),
    (t, 3, 'اتنشر على كل المنصات المتفق عليها',                   true,  false, 'social_media');
end $$;
