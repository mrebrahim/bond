-- =====================================================================
-- الفريق — الحسابات لازم تكون موجودة في Supabase Auth الأول
-- (Authentication → Users → Add user، أو دعوة من /settings?tab=team)
-- =====================================================================
select add_staff('engyemad@digitalsolutionegy.com',   'إنجي عماد',   '{support,account_manager}',           '201050858834');
select add_staff('mina@digitalsolutionegy.com',       'مينا مجدي',   '{media_lead,studio,editor,designer}', '201284816199');
select add_staff('mariam@digitalsolutionegy.com',     'مريم ناجي',   '{sales}',                             '201050858831');
select add_staff('madonna@digitalsolutionegy.com',    'مادونا',       '{social_media}',                      '201050858834');
select add_staff('androisaak@digitalsolutionegy.com', 'أندرو إسحاق', '{studio,scriptwriter,ads}',           '201224186293');
select add_staff('androaymen@digitalsolutionegy.com', 'أندرو أيمن',  '{studio,editor,designer}',            '201276530062');
