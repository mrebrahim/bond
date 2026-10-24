// كل العرض: أرقام 0-9، فلوس "15,000 ج.م"، تواريخ وأوقات بتوقيت القاهرة
const TZ = 'Africa/Cairo';
const LOCALE = 'ar-EG-u-nu-latn';

const numFmt = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 2 });
const weekdayFmt = new Intl.DateTimeFormat(LOCALE, { weekday: 'long', timeZone: TZ });
const dmFmt = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: '2-digit', timeZone: TZ });
const timeFmt = new Intl.DateTimeFormat(LOCALE, { hour: 'numeric', minute: '2-digit', timeZone: TZ });
const ymdFmt = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: TZ });

// تاريخ من غير وقت ("2026-10-30") بيتعامل كظهر اليوم UTC علشان ميتزحلقش
function toDate(v: string | Date): Date {
  if (v instanceof Date) return v;
  return /^\d{4}-\d{2}-\d{2}$/.test(v) ? new Date(`${v}T12:00:00Z`) : new Date(v);
}

export function num(n: number | string | null | undefined): string {
  if (n === null || n === undefined || n === '') return '—';
  return numFmt.format(Number(n));
}

export function money(n: number | string | null | undefined): string {
  if (n === null || n === undefined) return '—';
  return `${numFmt.format(Math.round(Number(n)))} ج.م`;
}

export function pct(n: number | string | null | undefined): string {
  if (n === null || n === undefined) return '—';
  return `${numFmt.format(Number(n))}%`;
}

/** "الخميس 30/10" */
export function day(v: string | Date | null | undefined): string {
  if (!v) return '—';
  const d = toDate(v);
  return `${weekdayFmt.format(d)} ${dmFmt.format(d)}`;
}

/** "30/10" */
export function shortDate(v: string | Date | null | undefined): string {
  if (!v) return '—';
  return dmFmt.format(toDate(v));
}

/** "3:30 م" */
export function time(v: string | Date | null | undefined): string {
  if (!v) return '—';
  return timeFmt.format(toDate(v));
}

export function dayTime(v: string | Date | null | undefined): string {
  if (!v) return '—';
  return `${day(v)} · ${time(v)}`;
}

/** 12.5 → "12:30 ساعة" */
export function hours(n: number | string | null | undefined): string {
  if (n === null || n === undefined) return '—';
  const total = Math.round(Number(n) * 60);
  const h = Math.floor(total / 60);
  const m = total % 60;
  return `${h}:${String(m).padStart(2, '0')} ساعة`;
}

/** كمية حسب نوع الوحدة */
export function qty(n: number | string | null | undefined, unit: string | null | undefined): string {
  return unit === 'hours' ? hours(n) : num(n);
}

/** "YYYY-MM-DD" النهارده بتوقيت القاهرة */
export function cairoToday(): string {
  return ymdFmt.format(new Date());
}

export function addDays(ymd: string, days: number): string {
  const d = new Date(`${ymd}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

/** يوم الأسبوع (0 = الحد) لتاريخ YYYY-MM-DD */
export function weekday(ymd: string): number {
  return new Date(`${ymd}T12:00:00Z`).getUTCDay();
}

/** وقت محلي في القاهرة (YYYY-MM-DD + HH:mm) → ISO مع الـ offset الصح */
export function cairoToIso(ymd: string, hm: string): string {
  const guess = new Date(`${ymd}T${hm}:00Z`);
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TZ, hour12: false, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
  }).formatToParts(guess);
  const get = (t: string) => Number(parts.find((p) => p.type === t)?.value);
  const asCairo = Date.UTC(get('year'), get('month') - 1, get('day'), get('hour') % 24, get('minute'));
  const offset = asCairo - guess.getTime();
  return new Date(guess.getTime() - offset).toISOString();
}

/** ISO → "HH:mm" بتوقيت القاهرة (لحقول الإدخال) */
export function cairoHm(v: string | Date): string {
  return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TZ }).format(toDate(v));
}

export function cairoYmd(v: string | Date): string {
  return ymdFmt.format(toDate(v));
}

/** "من 45 دقيقة" / "من 3 ساعات" */
export function ago(v: string | Date | null | undefined): string {
  if (!v) return '—';
  return minutesLabel(Math.max(0, Math.round((Date.now() - toDate(v).getTime()) / 60000)));
}

export function minutesLabel(mins: number | null | undefined): string {
  if (mins === null || mins === undefined) return '—';
  if (mins < 60) return `${mins} دقيقة`;
  if (mins < 60 * 24) return `${Math.floor(mins / 60)} ساعة${mins % 60 ? ` و ${mins % 60} دقيقة` : ''}`;
  return `${Math.floor(mins / 1440)} يوم`;
}

export function waLink(phone: string | null | undefined, text?: string): string {
  if (!phone) return '#';
  return `https://wa.me/${phone}${text ? `?text=${encodeURIComponent(text)}` : ''}`;
}

export const WEEKDAYS = ['الحد', 'الاتنين', 'التلات', 'الأربع', 'الخميس', 'الجمعة', 'السبت'];
