import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { addDays, cairoToIso, cairoToday, cairoYmd, shortDate, weekday } from '@/lib/format';
import { LinkButton, PageHeader } from '@/components/ui';
import { CalendarBoard, type CalSession } from './calendar-board';

export default async function CalendarPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const sp = await searchParams;
  const staff = await getStaff();
  const supabase = await createClient();
  const view = sp.view === 'day' ? 'day' : 'week';
  const by = sp.by === 'photographer' ? 'photographer' : 'studio';
  const base = sp.date ?? cairoToday();
  // الأسبوع بيبدأ السبت
  const start = view === 'day' ? base : addDays(base, -((weekday(base) + 1) % 7));
  const days = Array.from({ length: view === 'day' ? 1 : 7 }, (_, i) => addDays(start, i));
  const end = addDays(start, days.length);

  const [{ data: rows }, { data: studios }, { data: photographers }, { data: clients }] = await Promise.all([
    supabase.from('studio_sessions')
      .select('id, booked_start, booked_end, status, confirmation, planned_qty, deliverable_type, studio_id, photographer_id, clients(name), staff!studio_sessions_photographer_id_fkey(name)')
      .gte('booked_start', cairoToIso(start, '00:00')).lt('booked_start', cairoToIso(end, '00:00'))
      .order('booked_start'),
    supabase.from('studios').select('id, name').eq('active', true).order('name'),
    supabase.from('staff').select('id, name').contains('roles', ['studio']).eq('active', true).order('name'),
    supabase.from('clients').select('id, name, code').eq('status', 'active').order('name'),
  ]);

  const now = Date.now();
  const sessions: CalSession[] = (rows ?? []).map((s) => ({
    id: s.id,
    day: cairoYmd(s.booked_start),
    booked_start: s.booked_start,
    booked_end: s.booked_end,
    status: s.status, confirmation: s.confirmation, planned_qty: Number(s.planned_qty),
    deliverable_type: s.deliverable_type, studio_id: s.studio_id, photographer_id: s.photographer_id,
    client_name: s.clients?.name ?? '', photographer: s.staff?.name ?? null,
    soon: new Date(s.booked_start).getTime() - now < 24 * 3600e3,
  }));

  const columns = by === 'studio' ? (studios ?? []) : (photographers ?? []);
  const link = (patch: Record<string, string>) => `/calendar?${new URLSearchParams({ view, by, date: base, ...patch })}`;
  const step = view === 'day' ? 1 : 7;

  return (
    <div>
      <PageHeader
        title="كالندر الاستوديو"
        subtitle={`${shortDate(days[0])} → ${shortDate(days[days.length - 1])}`}
        actions={
          <>
            <LinkButton size="sm" href={link({ date: addDays(base, -step) })}>→ السابق</LinkButton>
            <LinkButton size="sm" href={link({ date: cairoToday() })}>النهارده</LinkButton>
            <LinkButton size="sm" href={link({ date: addDays(base, step) })}>التالي ←</LinkButton>
            <LinkButton size="sm" variant={view === 'day' ? 'primary' : 'secondary'} href={link({ view: 'day' })}>يوم</LinkButton>
            <LinkButton size="sm" variant={view === 'week' ? 'primary' : 'secondary'} href={link({ view: 'week' })}>أسبوع</LinkButton>
            <LinkButton size="sm" variant={by === 'studio' ? 'primary' : 'secondary'} href={link({ by: 'studio' })}>الاستوديوهات</LinkButton>
            <LinkButton size="sm" variant={by === 'photographer' ? 'primary' : 'secondary'} href={link({ by: 'photographer' })}>المصورين</LinkButton>
          </>
        }
      />
      <CalendarBoard
        days={days}
        columns={columns}
        by={by}
        sessions={sessions}
        clients={clients ?? []}
        studios={studios ?? []}
        photographers={photographers ?? []}
        canBook={hasRole(staff.roles, CAN.book)}
        bookClient={sp.book}
      />
    </div>
  );
}
