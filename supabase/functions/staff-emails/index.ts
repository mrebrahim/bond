// إيميلات التنبيهات للفريق — بتشتغل كل دقيقة من pg_cron (migration 013)
// Secrets: SMTP_PASS (Gmail App password) · اختياري: SMTP_USER, APP_URL
import { createClient } from 'npm:@supabase/supabase-js@2.45.4';
import nodemailer from 'npm:nodemailer@6.9.15';

const SMTP_USER = Deno.env.get('SMTP_USER') ?? 'n8n.arabic@gmail.com';
const SMTP_PASS = Deno.env.get('SMTP_PASS');
const APP_URL = (Deno.env.get('APP_URL') ?? 'https://bond-chi-tawny.vercel.app').replace(/\/$/, '');

const supabase = createClient(Deno.env.get('SUPABASE_URL')!, Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!, {
  auth: { persistSession: false },
});

type Row = {
  alert_id: number; alert_key: string; priority: string; payload: Record<string, unknown>;
  client_id: string | null; client_name: string | null; recipient_name: string; email: string;
};

const TZ = 'Africa/Cairo';
const d = (v: unknown) => v ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { weekday: 'long', day: '2-digit', month: '2-digit', timeZone: TZ })
  .format(new Date(String(v).length === 10 ? `${v}T12:00:00Z` : String(v))) : '';
const t = (v: unknown) => v ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { hour: 'numeric', minute: '2-digit', timeZone: TZ }).format(new Date(String(v))) : '';
const s = (v: unknown) => (v === null || v === undefined ? '—' : String(v));

function message(r: Row): { subject: string; lines: string[] } | null {
  const p = r.payload ?? {};
  const c = r.client_name ?? '';
  switch (r.alert_key) {
    case 'client_unhappy': return { subject: `🔴 عميل متضايق: ${c}`, lines: [
      `تقييم: تصوير ${s(p.filming)} · مونتاج ${s(p.editing)} · لوكيشن ${s(p.location)}`,
      p.comment ? `قال: "${p.comment}"` : '', `الرقم: ${s(p.client_phone)}`,
      `المصور: ${s(p.photographer)} · المونتير: ${s(p.editor)}`, p.post_url ? `البوست: ${p.post_url}` : '',
      'كلّمه دلوقتي واقفل الشكوى من السيستم (التنبيهات ← كلمته).'] };
    case 'complaint_escalated': return { subject: `🚨 ${c} مستني من ${s(p.wait_minutes)} دقيقة ومحدش كلمه`, lines: [p.comment ? `"${p.comment}"` : ''] };
    case 'feedback_low': return { subject: `تقييم وحش على شغلك — ${c}`, lines: [`تصوير ${s(p.filming)} · مونتاج ${s(p.editing)}`, p.comment ? `"${p.comment}"` : ''] };
    case 'revision_requested': return { subject: `${c} طالب تعديل`, lines: [p.comment ? `"${p.comment}"` : ''] };
    case 'editing_late': return { subject: `⏱ "${s(p.title)}" متأخر في المونتاج`, lines: [`واقف من ${d(p.since)}`] };
    case 'usage_full_early': return { subject: `فرصة upgrade: ${c}`, lines: [`خلّص ${s(p.name)} وباقي ${s(p.days_left)} يوم في الباقة`] };
    case 'hours_low': return { subject: `ساعات المدربين قربت تخلص — ${c}`, lines: [`باقي ${s(p.remaining)} من ${s(p.total)} · الباقة لحد ${d(p.end_date)}`] };
    case 'low_usage_mid': return { subject: `خطر churn: ${c}`, lines: [`استهلك ${s(p.used_pct)}% بس ونص الدورة عدّى`] };
    case 'session_unconfirmed': return { subject: `${c} لسه مأكدش الجلسة`, lines: [`الجلسة ${d(p.start)} ${t(p.start)} — كلّمه`] };
    case 'slot_missed': return { subject: `ميعاد نشر فات من غير محتوى — ${c}`, lines: [`${d(p.date)}`] };
    case 'slot_missed_internal': return { subject: `ميعاد نشر ${c} فات والمحتوى كان جاهز!`, lines: [`${d(p.date)}`] };
    case 'content_runway_low': return { subject: `المحتوى قرب يخلص — ${c}`, lines: [`أول ميعاد فاضي ${d(p.first_gap_date)} · لازم يصوّر قبل ${d(p.shoot_deadline)}`] };
    case 'power_low': return { subject: '⚡ كارت الكهرباء قرب يخلص', lines: [`الرصيد ${s(p.balance)} ج.م · هيخلص ${d(p.runs_out_at)} ${t(p.runs_out_at)}`] };
    case 'reschedule_requested': return { subject: `${c} عايز يغيّر ميعاد الجلسة`, lines: ['كلّمه وظبّط ميعاد جديد.'] };
    case 'no_show': return { subject: `${c} مجاش الجلسة`, lines: [`اتخصم ${s(p.penalty)}`] };
    case 'payment_overdue': return { subject: `فلوس متأخرة — ${c}`, lines: [`${s(p.description)} · ${s(p.amount)} ج.م · كانت مستحقة ${d(p.due_date)}`] };
    case 'expiry_7d': case 'expiry_3d': case 'expiry_1d':
      return { subject: `باقة ${c} بتخلص ${d(p.end_date)}`, lines: [`فاضل ${s(p.days_left)} يوم وفيه رصيد لسه.`] };
    case 'usage_high': return { subject: `${c} استهلك 80% من ${s(p.name)}`, lines: [`${s(p.used)} من ${s(p.total)}`] };
    case 'expired_summary': return { subject: `باقة ${c} خلصت`, lines: [`انتهت ${d(p.end_date)}`] };
    default: return null;
  }
}

function html(r: Row, m: { subject: string; lines: string[] }) {
  const link = r.client_id ? `${APP_URL}/clients/${r.client_id}` : `${APP_URL}/inbox`;
  const esc = (x: string) => x.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const body = m.lines.filter(Boolean).map((l) => `<p style="margin:0 0 8px">${esc(l)}</p>`).join('');
  return `<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;font-size:15px;color:#0f172a;max-width:560px">
    <p style="margin:0 0 12px">أهلًا ${esc(r.recipient_name)}،</p>
    <h2 style="font-size:17px;margin:0 0 12px;color:${r.priority === 'urgent' ? '#dc2626' : '#0f172a'}">${esc(m.subject)}</h2>
    ${body}
    <p style="margin:20px 0"><a href="${link}" style="background:#1d5cf0;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">افتح في السيستم</a></p>
    <p style="color:#64748b;font-size:12px">Digital Solution — تنبيه أوتوماتيك</p></div>`;
}

Deno.serve(async () => {
  if (!SMTP_PASS) return Response.json({ error: 'SMTP_PASS مش متحط في الـ secrets' }, { status: 500 });
  const { data, error } = await supabase.rpc('claim_staff_emails', { p_limit: 30 });
  if (error) return Response.json({ error: error.message }, { status: 500 });

  const transporter = nodemailer.createTransport({
    host: 'smtp.gmail.com', port: 465, secure: true, auth: { user: SMTP_USER, pass: SMTP_PASS.replace(/\s/g, '') },
  });
  // CC من الإعدادات (staff_email_cc)
  const { data: ccRow } = await supabase.from('settings').select('value').eq('key', 'staff_email_cc').maybeSingle();
  const cc = String(ccRow?.value ?? '').split(/[,;\s]+/).map((x) => x.trim().toLowerCase()).filter((x) => x.includes('@'));

  // إيميل واحد لكل تنبيه — كل المستلمين في To
  const groups = new Map<number, Row[]>();
  for (const r of (data ?? []) as Row[]) groups.set(r.alert_id, [...(groups.get(r.alert_id) ?? []), r]);

  let sent = 0, skipped = 0, failed = 0;
  for (const rows of groups.values()) {
    const r = rows[0];
    const m = message(r);
    if (!m) { skipped++; continue; }
    const to = [...new Set(rows.map((x) => x.email.toLowerCase()))];
    const ccList = cc.filter((x) => !to.includes(x));
    const greeting = { ...r, recipient_name: rows.map((x) => x.recipient_name).join(' و ') };
    try {
      await transporter.sendMail({
        from: `Digital Solution <${SMTP_USER}>`, to, cc: ccList.length ? ccList : undefined, subject: m.subject,
        text: [...m.lines.filter(Boolean), '', r.client_id ? `${APP_URL}/clients/${r.client_id}` : `${APP_URL}/inbox`].join('\n'),
        html: html(greeting, m),
      });
      sent++;
    } catch (e) {
      failed++;
      await supabase.rpc('mark_alert_failed', { p_alert: r.alert_id, p_error: String((e as Error)?.message ?? e) });
    }
  }
  return Response.json({ sent, skipped, failed });
});
