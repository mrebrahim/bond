import Link from 'next/link';
import { createClient } from '@/lib/supabase/server';
import { STAGE_LABELS } from '@/lib/labels';
import { addDays, cairoToday, day, dayTime, shortDate, WEEKDAYS, weekday } from '@/lib/format';
import { Badge, Card, Empty, LinkButton, PageHeader, Table, Td, Th, cn } from '@/components/ui';
import { PublishButton } from './publish-button';

const CLS: Record<string, string> = {
  published: 'bg-emerald-600', ready: 'bg-emerald-400', in_pipeline: 'bg-amber-400', empty: 'bg-red-500', missed: 'bg-slate-800',
};

export default async function ContentPage({ searchParams }: { searchParams: Promise<{ view?: string }> }) {
  const view = (await searchParams).view ?? 'runway';
  return (
    <div>
      <PageHeader title="بنك المحتوى" actions={
        <>
          <LinkButton size="sm" variant={view === 'runway' ? 'primary' : 'secondary'} href="/content">المخزون</LinkButton>
          <LinkButton size="sm" variant={view === 'calendar' ? 'primary' : 'secondary'} href="/content?view=calendar">تقويم النشر</LinkButton>
          <LinkButton size="sm" variant={view === 'today' ? 'primary' : 'secondary'} href="/content?view=today">النشر النهارده</LinkButton>
        </>
      } />
      {view === 'today' ? <Today /> : view === 'calendar' ? <CalendarView /> : <Runway />}
    </div>
  );
}

async function Runway() {
  const supabase = await createClient();
  const { data } = await supabase.from('v_content_runway').select('*').order('first_gap_date', { ascending: true, nullsFirst: false });
  if (!data?.length) return <Empty icon="🗂️">مفيش عملاء عندهم جدول نشر</Empty>;
  return (
    <Table>
      <thead><tr><Th>العميل</Th><Th>النوع</Th><Th>جاهز</Th><Th>في المونتاج</Th><Th>مواعيد الأسبوع</Th><Th>أول ميعاد فاضي</Th><Th>لازم يصوّر قبل</Th><Th>الجلسة الجاية</Th></tr></thead>
      <tbody>
        {data.map((r) => {
          const danger = r.deliverable_type === 'reel' && r.shoot_deadline && (!r.next_booked_session || r.next_booked_session.slice(0, 10) > r.shoot_deadline);
          return (
            <tr key={`${r.client_id}-${r.deliverable_type}`} className={cn(danger && 'bg-red-50')}>
              <Td><Link href={`/clients/${r.client_id}?tab=content`} className="font-medium text-brand-700">{r.client_name}</Link></Td>
              <Td>{r.deliverable_type === 'reel' ? '🎬 ريل' : '🎨 تصميم'}</Td>
              <Td className="font-bold text-emerald-600">{r.ready}</Td>
              <Td className="text-amber-600">{r.in_pipeline}</Td>
              <Td>{r.slots_next_7d}</Td>
              <Td>{day(r.first_gap_date)}</Td>
              <Td className={cn(danger && 'font-bold text-red-600')}>{r.deliverable_type === 'reel' ? day(r.shoot_deadline) : '—'}</Td>
              <Td>{r.next_booked_session ? dayTime(r.next_booked_session) : <span className="text-red-600">مفيش</span>}</Td>
            </tr>
          );
        })}
      </tbody>
    </Table>
  );
}

async function CalendarView() {
  const supabase = await createClient();
  const today = cairoToday();
  const days = Array.from({ length: 14 }, (_, i) => addDays(today, i - 3));
  const { data } = await supabase.from('v_slot_board').select('client_id, client_name, deliverable_type, slot_date, board_status')
    .gte('slot_date', days[0]).lte('slot_date', days[days.length - 1]);
  if (!data?.length) return <Empty icon="📅">مفيش مواعيد نشر — شغّل الـ job اليومي أو ضيف جدول نشر للعملاء</Empty>;
  const clients = [...new Map(data.map((d) => [d.client_id, d.client_name])).entries()];
  return (
    <Card className="overflow-x-auto p-0">
      <table className="w-full min-w-[900px] text-xs">
        <thead>
          <tr>
            <th className="sticky start-0 bg-slate-50 px-2 py-2 text-start">العميل</th>
            {days.map((d) => <th key={d} className={cn('bg-slate-50 px-1 py-2 text-center font-medium', d === today && 'bg-brand-100')}>{WEEKDAYS[weekday(d)]}<br />{shortDate(d)}</th>)}
          </tr>
        </thead>
        <tbody>
          {clients.map(([id, name]) => (
            <tr key={id}>
              <td className="sticky start-0 border-t border-slate-100 bg-white px-2 py-2 font-medium">{name}</td>
              {days.map((d) => {
                const slots = data.filter((s) => s.client_id === id && s.slot_date === d);
                return (
                  <td key={d} className={cn('border-t border-slate-100 px-1 py-2 text-center', d === today && 'bg-brand-50')}>
                    <div className="flex justify-center gap-0.5">
                      {slots.map((s) => (
                        <span key={s.deliverable_type} title={`${s.deliverable_type} · ${s.board_status}`} className={cn('inline-block h-4 w-4 rounded', CLS[s.board_status ?? 'empty'])}>
                          <span className="sr-only">{s.board_status}</span>
                        </span>
                      ))}
                    </div>
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
      <div className="flex flex-wrap gap-3 p-3 text-xs text-slate-500"><span>🟢 فيه محتوى</span><span>🟡 في المونتاج</span><span>🔴 فاضي</span><span>⚫ فات</span></div>
    </Card>
  );
}

async function Today() {
  const supabase = await createClient();
  const { data } = await supabase.from('v_slot_board').select('*').eq('slot_date', cairoToday()).order('publish_time', { nullsFirst: false });
  if (!data?.length) return <Empty icon="📣">مفيش نشر النهارده</Empty>;
  return (
    <div className="mx-auto flex max-w-xl flex-col gap-2">
      {data.map((s) => (
        <Card key={s.slot_id} className={cn('flex items-center justify-between gap-3 p-3', s.board_status === 'published' && 'opacity-60')}>
          <div>
            <div className="text-lg font-bold tabular-nums">{s.publish_time?.slice(0, 5) ?? '—'}</div>
            <div className="font-semibold">{s.client_name} <span className="text-sm font-normal">{s.deliverable_type === 'design' ? '🎨' : '🎬'}</span></div>
            <div className="text-sm text-slate-500">{s.deliverable_title ?? 'مفيش محتوى متربط'}{s.deliverable_stage && ` · ${STAGE_LABELS[s.deliverable_stage]}`}</div>
          </div>
          {s.board_status === 'published' ? <Badge className="bg-emerald-100 text-emerald-800">✔ اتنشر</Badge>
            : s.deliverable_id ? <PublishButton deliverableId={s.deliverable_id} title={s.deliverable_title ?? ''} />
            : <Badge className="bg-red-100 text-red-700">🔴 فاضي</Badge>}
        </Card>
      ))}
    </div>
  );
}
