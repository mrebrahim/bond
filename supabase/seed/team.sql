-- =====================================================================
-- الفريق الحالي — شغّله مرة واحدة بعد ما تعمل الحسابات في Supabase Auth
-- (Authentication → Users → Add user، أو دعوة من /settings/team)
-- غيّر الإيميلات والأرقام قبل التشغيل.
-- =====================================================================
select add_staff('ibrahim@digitalsolutionegypt.com', 'إبراهيم خليل', '{admin}',                                '201000000001');
select add_staff('mariam@digitalsolutionegypt.com',  'مريم ناجي',    '{sales}',                                '201000000002');
select add_staff('engy@digitalsolutionegypt.com',    'إنجي عماد',    '{support,account_manager}',             '201000000003');
select add_staff('madonna@digitalsolutionegypt.com', 'مادونا',        '{social_media}',                         '201000000004');
select add_staff('andrew.i@digitalsolutionegypt.com','أندرو إسحاق',  '{scriptwriter,studio,ads}',              '201000000005');
select add_staff('andrew.a@digitalsolutionegypt.com','أندرو أيمن',   '{studio,editor,designer}',               '201000000006');
select add_staff('mina@digitalsolutionegypt.com',    'مينا مجدي',    '{studio,editor,designer,media_lead}',    '201000000007');
