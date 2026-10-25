# n8n — تقييمات العملاء بس

كل الباقي اتنقل لـ **Supabase** ومبقاش محتاج n8n:

| كان في n8n | دلوقتي |
|---|---|
| Daily / Hourly | `pg_cron` جوه Supabase (`dse-daily` 00:05 القاهرة · `dse-hourly`) |
| رسايل الواتساب للعملاء | Edge Function `wa-outbox` كل دقيقة — على **جروب العميل** لو متربط، وإلا الخاص |
| ردود العملاء (1 / 2 / قرأت) | Edge Function `wa-inbound` (بيتظبط من الإعدادات ← الواتساب) |
| إيميلات الفريق | Edge Function `staff-emails` كل دقيقة (Gmail SMTP) |

⚠️ لو عندك workflows قديمة على n8n باسم `DSE · Daily` / `Hourly` / `Outbox` / `Inbound` — **امسحها** (الـ view القديم بقى بيرجع فاضي، بس الأحسن تتمسح).

## الباقي: `04_feedback.json`
Google Sheets (ردود فورم التقييم) → `insert into feedback`. اعمله Import، واختار credential الـ Postgres (Supabase → Connect → Session pooler) والـ Google Sheets، وعدّل أسماء الأعمدة في node الـ **Map** لو مختلفة.
