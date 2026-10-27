# doctor_visits — زيارات الدكاترة (Perfex module)

تنفيذ الـ PRD كله (المراحل 1–5): الجداول، العماير، بروفايل الدكتور + QR، Quick Log وسلم المراحل، أزور مين النهارده،
الحافز (القيمة، الهدف، الـ Streak، الـ XP والـ Levels، منطقة التقفيل، المهام)، زرار الواتساب والقوالب، التقارير، والـ API لـ n8n.

## التركيب
Setup → Modules → Upload `doctor_visits.zip` → Install → Activate، وبعدين Setup → Staff → Roles:
- المندوب: `view_own` + `create` (+ `edit` لو هيعدّل البيانات)
- مدير المبيعات: `view` + `create` + `edit` + `delete` + `manage_settings`

## الشاشات (القايمة: زيارات الدكاترة)
| الشاشة | الرابط |
|---|---|
| أزور مين النهارده | `/admin/doctor_visits/queue` |
| سجّل زيارة (Quick Log) | `/admin/doctor_visits/log` |
| الداشبورد | `/admin/doctor_visits/dashboard` |
| العماير / العمارة | `/admin/doctor_visits/buildings` |
| الدكتور (البيانات + QR + الزيارات) | `/admin/doctor_visits/doctor/{lead_id}` |
| واتساب | `/admin/doctor_visits/wa/{lead_id}` |
| التقارير + CSV | `/admin/doctor_visits/reports` |
| الإعدادات والقوالب والـ API والفحص | `/admin/doctor_visits/settings` |

وتاب "الزيارات" بيظهر في صفحة الـ Lead عن طريق `after_lead_lead_tabs` / `after_lead_tabs_content`.

## n8n
- `perfex/n8n/doctor_visits_qr.json`: Webhook من Evolution ← يلقط `DV-XXXX` أو "إلغاء" ← `qr_event` / `opt_out`.
- `perfex/n8n/doctor_visits_daily_quests.json`: الساعة 2 ← `queue` ← رسالة المهام للمندوب.
- الـ Token من صفحة الإعدادات (Header `X-DV-Token`).

## اختلافات عن الـ PRD
- `tbldv_settings`: العام `staff_id = 0` (MySQL مش بيقبل NULL في الـ Primary Key).
- `tbldv_xp_ledger`: `uniq_key` بدل `UNIQUE(staff_id, lead_id, action)` — الـ Stage XP مرة واحدة لكل دكتور، والمتابعات تتكرر.
- `tbldv_events.payload`: `LONGTEXT` (MariaDB القديمة).
- `tbldv_doctor_meta`: اتضاف `audit_link, key_insight, demo_link, offer_link, meeting_at, deal_value` (متغيرات القوالب وقيمة الصفقة).
- الـ 4 models اتجمعوا في `Dv_model` (كلهم بيحتاجوا بعض في نفس العملية).
- الإرسال على الواتساب محتاج إذن (Consent) — الزرار مقفول لحد ما الإذن يتسجل (PRD §9).
- التحويل لـ Customer بعد الـ Won بيتعمل من صفحة الـ Lead في Perfex (الموديول بيحط الحالة "DV 9 · Won" ويوجّهك).

## الاختبارات
`php perfex/tests/dv_run.php` — التفعيل، المحرّك، كل الشاشات من الكنترولر، والـ API (على SQLite من غير Perfex).
