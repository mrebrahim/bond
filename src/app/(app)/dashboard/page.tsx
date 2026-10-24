import Link from 'next/link';
import { createClient } from '@/lib/supabase/server';
import { requirePageRole } from '@/lib/auth';
import type { BalanceItem } from '@/lib/db';
import { addDays, cairoToday, day, dayTime, minutesLabel, money, num, pct, qty } from '@/lib/format';
import { Card, CardTitle, Empty, Health, PageHeader, Stat } from '@/components/ui';

export default async function DashboardPage() {
  await requirePageRole([]);
  const supabase = await createClient();
  const in7 = addDays(cairoToday(), 7);
  const [{ data: k }, { data: complaints }, { data: risk }, { data: expiring }, { data: runway }, { data: power }, { data: top }] = await Promise.all([
    supabase.from('v_dashboard_kpis').select('*').maybeSingle(),
    supabase.from('v_complaints').select('*').eq('is_open', true).order('created_at'),
    supabase.from('v_client_overview').select('client_id, name, health_score, account_manager').eq('status', 'active').lt('health_score', 50).order('health_score').limit(10),
    supabase.from('v_client_overview').select('client_id, name, days_left, balances').eq('status', 'active').gte('days_left', 0).lte('days_left', 7).order('days_left'),
    supabase.from('v_content_runway').select('*').eq('deliverable_type', 'reel').lte('first_gap_date', in7).order('first_gap_date'),
    supabase.from('v_meter_status').select('*'),
    supabase.from('v_leaderboard').select('*').order('position').limit(3),
  ]);

  return (
    <div>
      <PageHeader title="الداشبورد" subtitle={day(cairoToday())} />
      <div className="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        <Stat label="MRR" value={money(k?.mrr)} href="/reports" />
        <Stat label="العملاء النشطين" value={num(k?.active_clients)} href="/clients" />
        <Stat label="ARPA" value={money(k?.arpa)} href="/reports" />
        <Stat label="Logo churn (الشهر اللي فات)" value={pct(k?.logo_churn_pct)} tone={Number(k?.logo_churn_pct) >= 8 ? 'red' : undefined} href="/reports" />
        <Stat label="NRR" value={pct(k?.nrr_pct)} href="/reports" />
        <Stat label="فلوس متأخرة" value={money(k?.overdue_amount)} tone={Number(k?.overdue_amount) > 0 ? 'red' : undefined} href="/finance?filter=overdue" />
        <Stat label="الرصيد الضايع (3 شهور)" value={pct(k?.forfeiture_pct_3m)} tone={Number(k?.forfeiture_pct_3m) >= 15 ? 'amber' : undefined} href="/reports" />
      </div>

      <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <Card className={complaints?.length ? 'border-red-300' : ''}>
          <CardTitle action={<Link href="/inbox?tab=complaints" className="text-xs text-brand-700">الكل</Link>}>🔴 شكاوى مفتوحة</CardTitle>
          {!complaints?.length ? <Empty icon="🎉">مفيش</Empty> : (
            <ul className="flex flex-col gap-2 text-sm">
              {complaints.map((c) => (
                <li key={c.alert_id} className="flex justify-between gap-2">
                  <Link href={`/clients/${c.client_id}?tab=feedback`} className="font-medium text-brand-700">{c.client_name}</Link>
                  <span className={c.within_sla ? 'text-amber-700' : 'font-bold text-red-600'}>مستني {minutesLabel(c.wait_minutes)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <CardTitle action={<Link href="/clients?health=low" className="text-xs text-brand-700">الكل</Link>}>عملاء في خطر</CardTitle>
          {!risk?.length ? <Empty icon="💚">مفيش</Empty> : (
            <ul className="flex flex-col gap-2 text-sm">
              {risk.map((r) => (
                <li key={r.client_id} className="flex justify-between">
                  <Link href={`/clients/${r.client_id}`} className="text-brand-700">{r.name}</Link>
                  <Health score={r.health_score} />
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <CardTitle action={<Link href="/clients?expiring=7" className="text-xs text-brand-700">الكل</Link>}>باقات بتخلص الأسبوع ده</CardTitle>
          {!expiring?.length ? <Empty>مفيش</Empty> : (
            <ul className="flex flex-col gap-2 text-sm">
              {expiring.map((e) => (
                <li key={e.client_id}>
                  <div className="flex justify-between">
                    <Link href={`/clients/${e.client_id}`} className="text-brand-700">{e.name}</Link>
                    <span className={Number(e.days_left) <= 3 ? 'font-bold text-red-600' : ''}>باقي {e.days_left} يوم</span>
                  </div>
                  <div className="text-xs text-slate-500">
                    {((e.balances ?? []) as BalanceItem[]).filter((b) => b.unit !== 'flat' && b.remaining > 0).map((b) => `${b.name} ${qty(b.remaining, b.unit)}`).join(' · ') || 'الرصيد خلص'}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <CardTitle action={<Link href="/content" className="text-xs text-brand-700">بنك المحتوى</Link>}>محتاجين تصوير</CardTitle>
          {!runway?.length ? <Empty>المحتوى مكفّي</Empty> : (
            <ul className="flex flex-col gap-2 text-sm">
              {runway.map((r) => (
                <li key={r.client_id} className="flex justify-between gap-2">
                  <Link href={`/clients/${r.client_id}?tab=content`} className="text-brand-700">{r.client_name}</Link>
                  <span className="text-xs">قبل {day(r.shoot_deadline)} · {r.next_booked_session ? `محجوز ${dayTime(r.next_booked_session)}` : <b className="text-red-600">مفيش حجز</b>}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <CardTitle action={<Link href="/power" className="text-xs text-brand-700">التفاصيل</Link>}>⚡ الكهرباء</CardTitle>
          {(power ?? []).map((m) => (
            <div key={m.meter_id} className="text-sm">
              <div className="flex justify-between"><span>{m.name}</span><b>{m.balance_now_est !== null ? money(m.balance_now_est) : '—'}</b></div>
              <div className={m.runs_out_before_next_session ? 'text-xs font-semibold text-red-600' : 'text-xs text-slate-500'}>
                {m.runs_out_at ? `هيخلص ${dayTime(m.runs_out_at)}` : 'محتاج قرايتين على الأقل'}
              </div>
            </div>
          ))}
        </Card>
        <Card>
          <CardTitle action={<Link href="/game" className="text-xs text-brand-700">لوحة الشرف</Link>}>🏆 الأوائل</CardTitle>
          {!top?.length ? <Empty>لسه</Empty> : (
            <ol className="flex flex-col gap-2 text-sm">
              {top.map((t, i) => (
                <li key={t.staff_id} className="flex justify-between"><span>{['🥇', '🥈', '🥉'][i]} {t.name} {t.rank_icon}</span><b>{num(t.xp_month)} XP</b></li>
              ))}
            </ol>
          )}
        </Card>
      </div>
    </div>
  );
}
