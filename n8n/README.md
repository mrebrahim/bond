# n8n workflows — الواتساب والـ jobs

5 ملفات جاهزة للـ Import (n8n → Workflows → Import from file). مفيش أي key جوه الملفات، كله credentials في n8n.

| الملف | التشغيل | بيعمل إيه |
|---|---|---|
| `01_daily.json` | كل يوم 00:05 (القاهرة) | `select run_daily()` |
| `02_hourly.json` | كل ساعة | `select run_hourly()` |
| `03_outbox.json` | كل دقيقتين | `v_alert_outbox` ← نص الرسالة ← **العملاء: واتساب** (Evolution) · **الفريق: إيميل** (SMTP) ← `alerts.status = sent/failed/skipped` |
| `04_feedback.json` | صف جديد في شيت ردود الفورم | `insert into feedback` (مرة واحدة لكل صف بالـ `source_ref`) |
| `05_inbound_whatsapp.json` | Webhook من Evolution | العميل كتب 1 ← الجلسة اتأكدت · كتب 2 ← `reschedule_requested` + تنبيه للدعم ← رد عليه |

الـ SQL اللي في الملفات بيتختبر في `npm run test:db`.

## 1) Credentials في n8n
**Supabase bond (Postgres)** — النوع Postgres:
- من Supabase → Project → Connect → **Session pooler**: Host `aws-…pooler.supabase.com` · Port `5432` · Database `postgres` · User `postgres.izpvbgzwiwyaoctdroxr` · Password = باسورد الداتابيز · SSL: require.
- ده بيدخل كـ `postgres` (بيعدّي الـ RLS) — علشان كده يفضل في n8n بس.

**Evolution API key (header: apikey)** — النوع Header Auth: Name = `apikey` · Value = الـ `AUTHENTICATION_API_KEY`.

**SMTP (digitalsolutionegy.com)** — النوع SMTP، لإيميلات الفريق. Google Workspace: Host `smtp.gmail.com` · Port `465` · SSL · User = إيميل البعت · Password = App Password (مش باسورد الإيميل). وحط نفس الإيميل في `fromEmail` في node الـ Config.

**Google Sheets** — OAuth2 للشيت بتاع ردود الفورم.

بعد الـ Import افتح كل node عليه ⚠️ واختار الـ credential.

## 2) Evolution API
1. من الـ manager اعمل instance اسمه `dse-clients` (رقم العملاء) و `dse-team` (رقم الفريق) واعمل scan للـ QR — أو غيّر الأسماء في node الـ **Config**.
2. Webhook على `dse-clients`: URL = `https://comp.digitalsolutionegy.com/webhook/dse-evolution-inbound` · Event: `MESSAGES_UPSERT`.
3. لو الـ instance شغال بـ **WhatsApp Cloud API** (الرسمي): الرسايل الحرة بتوصل بس جوه 24 ساعة من آخر رسالة من العميل. برّه الـ 24 ساعة لازم Utility templates متوافق عليها من Meta (PRD §10) — ساعتها غيّر node الـ sendText لـ `sendTemplate` لكل `alert_key`.

## 3) node الـ Config (في 03 و 05)
`evoUrl` · `instanceClients` · `instanceTeam` · `fromEmail` · `appUrl`.

لينك التقييم في رسالة الواتساب قصير: `<appUrl>/f/<كود>` — الموقع بيحوّله للفورم متملي. اللينك المتملي مسبقًا بيتحط مرة واحدة في الإعدادات ← عام ← `feedback_form_prefill_url`.

## 4) Feedback
في node الـ **Map** عدّل أسماء الأعمدة حسب عناوين الأسئلة في الشيت. خانتين "كود العميل" و"رقم الريل" بيتملوا لوحدهم من اللينك اللي في رسالة الواتساب (pre-filled) — العميل مش بيكتبهم.

## 5) التشغيل
شغّل الـ 5 workflows (Active). اختبر الأول: اعمل alert تجريبي لعميل رقمه رقمك، وتابع `alerts.status`.
