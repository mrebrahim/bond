# n8n workflows — الواتساب والـ jobs

5 ملفات جاهزة للـ Import (n8n → Workflows → Import from file). مفيش أي key جوه الملفات، كله credentials في n8n.

| الملف | التشغيل | بيعمل إيه |
|---|---|---|
| `01_daily.json` | كل يوم 00:05 (القاهرة) | `select run_daily()` |
| `02_hourly.json` | كل ساعة | `select run_hourly()` |
| `03_outbox.json` | كل دقيقتين | `v_alert_outbox` ← نص الرسالة ← Evolution `sendText` ← `alerts.status = sent/failed/skipped` |
| `04_feedback.json` | صف جديد في شيت ردود الفورم | `insert into feedback` (مرة واحدة لكل صف بالـ `source_ref`) |
| `05_inbound_whatsapp.json` | Webhook من Evolution | العميل كتب 1 ← الجلسة اتأكدت · كتب 2 ← `reschedule_requested` + تنبيه للدعم ← رد عليه |

الـ SQL اللي في الملفات بيتختبر في `npm run test:db`.

## الطريقة السريعة (من الـ cmd)
بتعمل الـ credentials وتركّب 01 و 02 و 03 و 05 وتشغّلهم (لو الـ workflow موجود بتحدّثه):
```
powershell -ExecutionPolicy Bypass -c "iwr -UseBasicParsing https://raw.githubusercontent.com/mrebrahim/bond/claude/dreamy-babbage-t6r4kb/n8n/install.ps1 -OutFile $env:TEMP\bond-n8n.ps1; & $env:TEMP\bond-n8n.ps1"
```
هيسألك عن مفتاح n8n ومفتاح Evolution والـ pooler host وباسورد الداتابيز.

## 1) Credentials في n8n (يدوي)
**Supabase bond (Postgres)** — النوع Postgres:
- من Supabase → Project → Connect → **Session pooler**: Host `aws-…pooler.supabase.com` · Port `5432` · Database `postgres` · User `postgres.izpvbgzwiwyaoctdroxr` · Password = باسورد الداتابيز · SSL: require.
- ده بيدخل كـ `postgres` (بيعدّي الـ RLS) — علشان كده يفضل في n8n بس.

**Evolution API key (header: apikey)** — النوع Header Auth: Name = `apikey` · Value = الـ `AUTHENTICATION_API_KEY`.

**Google Sheets** — OAuth2 للشيت بتاع ردود الفورم.

بعد الـ Import افتح كل node عليه ⚠️ واختار الـ credential.

## 2) Evolution API
1. من الـ manager رقم العملاء على instance `010` (رقم Bond)، والفريق مؤقتًا على نفس الرقم. لو عملت instance منفصل للفريق غيّر `instanceTeam` في node الـ **Config**.
2. الرابط بـ `https` (متعمول كده في node الـ Config).
3. Webhook على `010` (متظبط): URL = `https://comp.digitalsolutionegy.com/webhook/dse-evolution-inbound` · Event: `MESSAGES_UPSERT` · خلّي **Webhook by Events** و **Webhook Base64** مقفولين (غير كده الرابط بيتغير والـ workflow مش هيستلم).
4. لو الـ instance شغال بـ **WhatsApp Cloud API** (الرسمي): الرسايل الحرة بتوصل بس جوه 24 ساعة من آخر رسالة من العميل. برّه الـ 24 ساعة لازم Utility templates متوافق عليها من Meta (PRD §10) — ساعتها غيّر node الـ sendText لـ `sendTemplate` لكل `alert_key`.

## 3) node الـ Config (في 03 و 05)
`evoUrl` · `instanceClients` · `instanceTeam` · `formId` · `entryClient` · `entryDeliverable` (أرقام الـ entry من "Get pre-filled link" في الفورم).

## 4) Feedback
في node الـ **Map** عدّل أسماء الأعمدة حسب عناوين الأسئلة في الشيت. الفورم لازم يكون فيه حقل `deliverable_id` (بيتملى من اللينك).

## 5) التشغيل
شغّل الـ 5 workflows (Active). اختبر الأول: اعمل alert تجريبي لعميل رقمه رقمك، وتابع `alerts.status`.
