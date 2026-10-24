# CLAUDE.md — قواعد الشغل في المشروع

اقرا `PRD.md` الأول. الـ backend خلصان في `supabase/migrations/`، وانت بتبني الواجهة.

## القواعد الأساسية
1. **قواعد البيزنس في الداتابيز بس.** متحسبش رصيد، تاريخ انتهاء، مدة حجز، لبسات، XP، أو health في TypeScript. اقرا من الـ views ونادي الـ RPCs.
2. **متعدّلش migration قديمة.** أي تغيير = ملف جديد `00X_*.sql`، وشغّل `node tests/run.mjs` قبل وبعد. ضيف اختبار لأي منطق جديد.
3. **الأخطاء من الداتابيز عربي وجاهزة** — اعرض `error.message` في toast زي ما هي.
4. **مفيش service role key في الـ frontend.** كل الكتابة بالـ JWT بتاع المستخدم، والـ RLS هو الحماية الحقيقية. إخفاء الأزرار حسب الدور للتجربة بس.
5. **الأنواع:** بعد أي migration: `supabase gen types typescript --linked > src/types/database.ts`. ممنوع `any`.

## الواجهة
- عربي RTL: `<html lang="ar" dir="rtl">`، خط IBM Plex Sans Arabic، استخدم `ms-/me-/ps-/pe-` مش `ml-/mr-`.
- الأرقام 0-9، الفلوس `Intl.NumberFormat('ar-EG-u-nu-latn')` + "ج.م"، التاريخ والوقت بتوقيت `Africa/Cairo` دايمًا.
- shadcn/ui للمكونات. Mobile-first لـ `/today` و `/power` و `/production`.
- النصوص في الواجهة بالمصري البسيط، زي رسايل الداتابيز.

## هيكل الملفات
```
src/
  app/(auth)/login
  app/(app)/{dashboard,inbox,clients,calendar,today,sessions,production,content,scripts,finance,game,power,reports,settings}
  components/{ui,clients,calendar,production,...}
  lib/supabase/{server.ts,client.ts,middleware.ts}
  lib/{format.ts,roles.ts}
  types/database.ts
supabase/migrations/   tests/run.mjs   n8n/ (exports للـ workflows)
```

## أوامر
- `npm run dev` · `npm run build` · `npm run lint`
- `node tests/run.mjs` — اختبارات الداتابيز (PGlite، من غير Supabase)
- `supabase db push` — تطبيق الـ migrations على الـ project

## الترتيب
اشتغل بـ Phases الـ PRD بالترتيب. خلّص الـ "Done" بتاع كل phase واختبره قبل اللي بعده.
