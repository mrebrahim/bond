# Digital Solution — نظام العملاء والباقات والاستوديو

Next.js 15 (App Router) + Supabase (Auth, Postgres, RLS, Storage, Realtime). عربي RTL، Mobile-first.
اقرا `PRD.md` و `CLAUDE.md` الأول.

## التشغيل
```bash
cp .env.example .env.local   # حط الـ publishable key
npm install
npm run dev
```

| أمر | |
|---|---|
| `npm run build` / `npm run lint` | |
| `npm run test:db` | 61 اختبار للداتابيز على Postgres حقيقي (PGlite) من غير Supabase |

## الداتابيز (`supabase/migrations/`)
| Migration | |
|---|---|
| `001_core_schema` | الجداول وقواعد البيزنس والـ views |
| `002_media_manual` | المانوال + `location_score` + كود العميل الأوتوماتيك + validation الواتساب |
| `003_roles_rls` | RLS لكل الجداول حسب مصفوفة الصلاحيات، views بـ `security_invoker`، قفل anon |
| `004_gamification` | XP، الرتب، التحديات، لوحة الشرف، البونص، `v_deliverable_credits` |
| `005_team` | `add_staff` + الـ AM الافتراضي |
| `006_power_and_complaints` | الكهرباء، الشكاوى والتصعيد، `run_hourly`، Storage، Realtime |
| `007_hardening` | تقفيل دوال الـ triggers و `search_path` (من Supabase advisor) |
| `008_ui_helpers` | `preview_booking`، `schedule_package_diff`، `v_dashboard_kpis`، `v_slot_board` |

كلها متطبقة على مشروع Supabase `bond`. بعد أي migration جديدة: اعمل generate للأنواع في `src/types/database.ts`.

## الفريق
1. اعمل حساب لكل واحد (Supabase → Authentication → Add user) أو ادعيه من `/settings?tab=team` (محتاج أدمن واحد الأول).
2. أول أدمن: شغّل في SQL Editor:
   `select add_staff('email', 'إبراهيم خليل', '{admin}', '2010XXXXXXXX');`
3. باقي الفريق في `supabase/seed/team.sql`.

## Vercel
Environment variables: `NEXT_PUBLIC_SUPABASE_URL` و `NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY`.
في Supabase → Authentication → URL Configuration: حط دومين Vercel في Site URL و Redirect URLs (`https://<domain>/auth/callback`).

## n8n (بالـ service role بس)
- Daily 00:05 Cairo: `select run_daily()` · Hourly: `select run_hourly()`
- Outbox كل دقيقتين: `select * from v_alert_outbox limit 50` → واتساب → `update alerts set status='sent', sent_at=now()`
- Feedback: `insert into feedback (deliverable_id, filming_score, editing_score, location_score, wants_changes, issues, comment, revision_requested, source_ref)`
