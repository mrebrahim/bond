# نظام العملاء والباقات والاستوديو — Digital Solution Egypt

داتابيز Supabase كاملة: الباقات والأرصدة، حجز الاستوديو، بنك المحتوى، مانوال الميديا، التقييمات، التنبيهات، الـ KPIs، والتحديات والـ XP.

## الملفات (بالترتيب)

| الملف | فيه إيه |
|---|---|
| `001_core_schema.sql` | الجداول، قواعد الخصم والحجز، التجديد والانتهاء، بنك المحتوى، التنبيهات، الـ views |
| `002_media_manual.sql` | مانوال تيم الميديا (البنود الإجبارية بتقفل المرحلة) |
| `003_roles_rls.sql` | الحسابات والصلاحيات (RLS) |
| `004_gamification.sql` | الـ XP والتحديات والرتب والبونص الشهري |
| `005_team.sql` | دالة `add_staff` لإضافة الفريق |
| `006_power_and_complaints.sql` | كارت الكهرباء + متابعة شكاوى العملاء |
| `seed/team.sql` | الفريق الحالي بأدواره — غيّر الإيميلات والأرقام وشغّله مرة واحدة |

التشغيل: `supabase db push` أو انسخهم بالترتيب في SQL Editor.

## الفريق والأدوار

| الاسم | الأدوار |
|---|---|
| إبراهيم خليل | `admin` |
| مريم ناجي | `sales` |
| إنجي عماد | `support` `account_manager` (الـ AM الافتراضي لكل العملاء) |
| مادونا | `social_media` |
| أندرو إسحاق | `scriptwriter` `studio` `ads` |
| أندرو أيمن | `studio` `editor` `designer` |
| مينا مجدي | `studio` `editor` `designer` `media_lead` |

الموظف الواحد ممكن ياخد أكتر من دور. لإضافة حد جديد: اعمل حسابه في Supabase Auth، وبعدين
`select add_staff('email', 'الاسم', '{studio,editor}', '2010XXXXXXXX');`

**العميل المتضايق** (تقييم 3 أو أقل، أو كتب إن فيه حاجة مضايقاه): تنبيه عاجل لمريم ومعاه رقمه واسم المصور والمونتير. لما تكلمه: `select resolve_alert(alert_id, 'حصل إيه')`. لو محدش كلمه في ساعتين → يوصل لإبراهيم. كل الشكاوى ووقت الرد في `v_complaints`.

**كارت الكهرباء:** الاستوديو يسجل كل يوم الصبح `select record_meter_reading(meter_id, الرصيد, الشريحة)`، والشحن في `meter_recharges`. النظام بيحسب بيصرف كام في اليوم وهيخلص إمتى (`v_meter_status`).

**مين بيستلم إيه:** تنبيهات التجديد والساعات والـ upgrade ← مريم · الانتهاء والاستهلاك الضعيف والحجز ← إنجي · التأخير في المونتاج والتقييمات الوحشة والمواعيد اللي فاتت بسبب الفريق ← مينا · الأدمن مش بيستلم تنبيهات تشغيل يومية.

## العمليات الأساسية (RPC من الواجهة)

```sql
-- اشتراك (باقة مفصّلة)
select create_subscription(client_id, 'Growth', 15000,
  '[{"type":"reel","qty":10},{"type":"design","qty":10},{"type":"content_mgmt","qty":1}]', '2026-11-01', 30);
-- باقة مدربين: 50 ساعة، صالحة 90 يوم، الساعة الزيادة بـ 2500
select create_subscription(client_id, 'مدربين 50', 90000, '[{"type":"trainer_hours","qty":50}]', null, 90, 2500);

-- الدفع (بيفعّل الباقة لوحده)
insert into payments (invoice_id, amount, method) values (...);

-- الحجز (الرصيد والتعارض واللبس واللوكيشن بيتحسبوا لوحدهم)
insert into studio_sessions (client_id, studio_id, photographer_id, booked_start, planned_qty, created_by) values (...);

select check_in_session(id);            -- وصل
select complete_session(id, 6);         -- خلص: بيخصم وبيعمل الريلز (لازم المانوال يخلص)
select mark_no_show(id);                -- مجاش
select cancel_session(id, 'السبب');     -- إلغاء (المتأخر بيتخصم)

select add_topup(cycle_id, 'reel', 3, 3000);
select change_subscription(sub_id, 'upgrade', 20000, '[...]');
select cancel_subscription(sub_id, 'السبب');
```

## n8n (3 workflows)

| Workflow | التوقيت (Africa/Cairo) | بيعمل إيه |
|---|---|---|
| Daily | كل يوم 00:05 | `select run_daily()` — انتهاء، تجديد، مواعيد نشر، تنبيهات، تحديات، بونص أول الشهر |
| Hourly | كل ساعة | `select run_hourly()` — تذكيرات الجلسات، تصعيد الشكاوى، الكهرباء |
| Outbox | كل دقيقتين (العاجل الأول) | يقرا `v_alert_outbox` ← يبعت واتساب حسب `alert_key` ← `update alerts set status='sent'` |
| Feedback | Google Sheets Trigger | `insert into feedback (..., source_ref)` بالـ row number |
| Publish | حد / تلات / خميس 8 م | الرسايل بتطلع لوحدها من alert `published` (فيها `post_url` و `client_code` للينك التقييم) |

استخدم n8n Schedule بتوقيت القاهرة بدل pg_cron، علشان التوقيت الصيفي.

## Views للوحة التحكم

`v_client_overview` · `v_cycle_balance` · `v_content_runway` · `v_schedule_package_check` · `v_today_sessions` · `v_mrr_monthly` · `v_retention_monthly` · `v_utilization_monthly` · `v_feedback_by_staff` · `v_leaderboard` · `v_challenge_progress` · `v_deliverable_credits` · `v_complaints` · `v_meter_status`

## الاختبار

`node tests/run.mjs` — 48 سيناريو على Postgres حقيقي (PGlite).
