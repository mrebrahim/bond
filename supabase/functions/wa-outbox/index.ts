// رسايل الواتساب للعملاء (جروب العميل لو موجود، وإلا الخاص) — كل دقيقة من pg_cron (migration 015)
// Secrets: EVO_API_KEY · اختياري: EVO_URL, EVO_INSTANCE, APP_URL
import { createClient } from 'npm:@supabase/supabase-js@2.45.4';

const EVO_URL = (Deno.env.get('EVO_URL') ?? 'https://evolutoin-api-evolution-api.4tqpgc.easypanel.host').replace(/\/$/, '');
const EVO_KEY = Deno.env.get('EVO_API_KEY');
const INSTANCE = Deno.env.get('EVO_INSTANCE') ?? 'dse-clients';
const APP_URL = (Deno.env.get('APP_URL') ?? 'https://bond-chi-tawny.vercel.app').replace(/\/$/, '');
const supabase = createClient(Deno.env.get('SUPABASE_URL')!, Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!, { auth: { persistSession: false } });

type Row = {
  alert_id: number; alert_key: string; payload: Record<string, unknown>; client_name: string; client_code: string;
  destination: string; deliverable_id: string | null; balance_text: string | null; cycle_end: string | null; feedback_code: string | null;
};
const TZ = 'Africa/Cairo';
const d = (v: unknown) => v ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { weekday: 'long', day: '2-digit', month: '2-digit', timeZone: TZ })
  .format(new Date(String(v).length === 10 ? `${v}T12:00:00Z` : String(v))) : '';
const t = (v: unknown) => v ? new Intl.DateTimeFormat('ar-EG-u-nu-latn', { hour: 'numeric', minute: '2-digit', timeZone: TZ }).format(new Date(String(v))) : '';
const DAYS = ['الحد', 'الاتنين', 'التلات', 'الأربع', 'الخميس', 'الجمعة', 'السبت'];
const items = (arr: unknown) => ((arr as { name: string; remaining: number }[]) ?? []).filter((x) => Number(x.remaining) > 0).map((x) => `${x.name} ${x.remaining}`).join(' · ');

function text(a: Row): string | null {
  const p = a.payload ?? {};
  const bal = a.balance_text ?? '';
  const end = d(a.cycle_end ?? p.end_date);
  switch (a.alert_key) {
    // ---- مانوال العميل الجديد (بيبعتها الفريق بزرار) ----
    case 'welcome': return `أهلًا بيكم في Digital Solution 👋\nده الجروب الخاص بـ *${p.client_name}* — هنا هتلاقوا مواعيد التصوير، والمحتوى قبل النشر، وأي تحديثات.\n${p.team ? `معاكم في الجروب: ${p.team}\n` : ''}أي استفسار اكتبوه هنا وهنرد عليكم في أسرع وقت 🙏`;
    case 'client_rules': return String(p.rules ?? '');
    case 'booking_request': return `📅 يلا نحجز أول ميعاد تصوير!\nابعتلنا اليوم والساعة اللي يناسبوك${p.preferred_day !== null && p.preferred_day !== undefined ? ` (عادةً يوم ${DAYS[Number(p.preferred_day)]})` : ''}، وعدد الريلز اللي هتصورها${p.reels_per_session ? ` (المتوسط ${p.reels_per_session})` : ''}.\nأول ما نأكد هيوصلك تذكير قبلها بيوم.`;
    case 'outfits_reminder': return '👕 تذكير مهم: لو هتصور أكتر من ريل، هات أكتر من لبس — لبس لكل 3 ريلز تقريبًا — علشان الفيديوهات متبانش شبه بعض.';
    case 'contract_request': return '📄 من فضلك ابعتلنا صورة العقد بعد التوقيع هنا في الجروب.';
    case 'access_request': return '🔐 علشان نقدر ننشر ونتابع الأداء: من فضلك ضيفنا *Partner* على Meta Business Suite (فيسبوك وإنستجرام) — هنبعتلك الخطوات.\nلو عندك تيك توك أو يوتيوب أو Google Business قولنا نتفق على طريقة الأكسس.\n⚠️ متبعتش باسوردات في الجروب.';
    // ---- أوتوماتيك ----
    case 'published': return `الريل بتاعك نزل 🎬\n${p.post_url}\n\nرأيك في 10 ثواني: ${APP_URL}/f/${a.feedback_code}${bal ? `\n\nفاضلك ${bal} لحد ${end}` : ''}`;
    case 'session_done': return `صورنا النهارده ${p.qty} ${p.unit === 'hours' ? 'ساعة' : 'ريل'} ✅\nباقيلك ${p.remaining} لحد ${end}`;
    case 'session_24h': return `تصويرك بكرة ${d(p.start)} الساعة ${t(p.start)} 📸\n${p.qty} ${p.type === 'trainer_hours' ? 'ساعة' : 'ريلز'}${p.locations ? ` في ${p.locations} لوكيشن` : ''}${p.outfits ? ` · هات ${p.outfits} لبس` : ''}\n\nاكتب *1* للتأكيد أو *2* لو عايز تغيّر الميعاد`;
    case 'session_2h': return `تذكير: معادك النهارده الساعة ${t(p.start)} 📍`;
    case 'expiry_7d': case 'expiry_3d': case 'expiry_1d': return `باقيلك ${items(p.items)} والباقة بتخلص ${d(p.end_date)}\nاحجز معادك قبلها علشان متضيعش 🙏`;
    case 'usage_high': return `استهلكت 80% من ${p.name} (${p.used} من ${p.total})`;
    case 'content_runway_low': return `معانا محتوى لحد ${d(p.first_gap_date)} بس\nمحتاجين تصوير قبل ${d(p.shoot_deadline)}`;
    case 'slot_missed': return 'النهارده كان ميعاد نشر ومكانش عندنا محتوى جاهز — كلّمنا نحجز تصوير';
    case 'expired_summary': return `الباقة خلصت ${d(p.end_date)}\nاستلمت: ${((p.items as { name: string; used: number; total: number }[]) ?? []).map((x) => `${x.name} ${x.used}/${x.total}`).join(' · ')}\nالرصيد اللي ماتستخدمش انتهى حسب العقد`;
    case 'renewal_invoice': return `فاتورة التجديد: ${p.amount} ج.م — الباقة الجديدة تبدأ ${d(p.start_date)}\nالدفع: InstaPay / فودافون كاش / تحويل`;
    case 'payment_overdue': return `تذكير بالفاتورة: ${p.description} — ${p.amount} ج.م\nالدفع: InstaPay / فودافون كاش / تحويل`;
    case 'no_show': return `ماقدرناش نشوفك النهارده 😔 اتخصم ${p.penalty} ${p.unit === 'hours' ? 'ساعة' : 'ريل'} علشان الغياب حسب العقد`;
    case 'script_sent': return `سكربت جديد للمراجعة 📝\n*${p.title}*\n\n${p.body}\n\nرد بـ "موافق" أو ابعتلنا التعديلات`;
    default: return null;
  }
}

Deno.serve(async () => {
  if (!EVO_KEY) return Response.json({ error: 'EVO_API_KEY مش متحط في الـ secrets' }, { status: 500 });
  const { data, error } = await supabase.rpc('claim_wa_messages', { p_limit: 10 });
  if (error) return Response.json({ error: error.message }, { status: 500 });
  let sent = 0, skipped = 0, failed = 0;
  for (const a of (data ?? []) as Row[]) {
    const body = text(a);
    if (!body) { skipped++; continue; }
    try {
      const res = await fetch(`${EVO_URL}/message/sendText/${INSTANCE}`, {
        method: 'POST', headers: { apikey: EVO_KEY, 'Content-Type': 'application/json' },
        body: JSON.stringify({ number: a.destination, text: body }),
      });
      if (!res.ok) throw new Error(`Evolution ${res.status}: ${(await res.text()).slice(0, 300)}`);
      sent++;
    } catch (e) {
      failed++;
      await supabase.rpc('mark_alert_failed', { p_alert: a.alert_id, p_error: String((e as Error)?.message ?? e) });
    }
    await new Promise((r) => setTimeout(r, 3000)); // 3 ثواني بين كل رسالة
  }
  return Response.json({ sent, skipped, failed });
});
