import Link from 'next/link';
import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { CONFIRMATION, SESSION_STATUS } from '@/lib/labels';
import { day, qty, time, waLink } from '@/lib/format';
import { Badge, Card, Empty, PageHeader, cn } from '@/components/ui';
import { ActionButton } from '@/components/ui/client';
import { checkIn, noShow } from '@/lib/actions/sessions';
import { cairoToday } from '@/lib/format';

export default async function TodayPage() {
  const staff = await getStaff();
  const supabase = await createClient();
  const { data } = await supabase.from('v_today_sessions').select('*').order('booked_start');
  const canOps = hasRole(staff.roles, CAN.sessionOps);

  return (
    <div className="mx-auto max-w-xl">
      <PageHeader title="الاستوديو النهارده" subtitle={day(cairoToday())} />
      {!data?.length ? <Empty icon="🎬">مفيش جلسات النهارده</Empty> : (
        <div className="flex flex-col gap-3">
          {data.map((s) => (
            <Card key={s.session_id} className={cn(s.status === 'checked_in' && 'border-emerald-300', s.status === 'no_show' && 'opacity-60')}>
              <div className="flex items-start justify-between gap-2">
                <div>
                  <div className="text-2xl font-bold tabular-nums">{time(s.booked_start)}</div>
                  <div className="text-lg font-semibold">{s.client_name}</div>
                  <div className="text-sm text-slate-500">{s.studio}{s.photographer && ` · ${s.photographer}`}</div>
                </div>
                <div className="flex flex-col items-end gap-1">
                  <Badge className={SESSION_STATUS[s.status ?? 'booked'].cls}>{SESSION_STATUS[s.status ?? 'booked'].label}</Badge>
                  {s.status === 'booked' && <Badge className={CONFIRMATION[s.confirmation ?? 'pending'].cls}>{CONFIRMATION[s.confirmation ?? 'pending'].label}</Badge>}
                  {s.first_session && <Badge className="bg-violet-100 text-violet-800">⭐ أول جلسة</Badge>}
                </div>
              </div>
              <div className="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
                <div className="rounded-lg bg-slate-50 p-2"><div className="font-bold">{qty(s.planned_qty, s.deliverable_type === 'trainer_hours' ? 'hours' : 'count')}</div>{s.deliverable_type === 'trainer_hours' ? 'ساعات' : 'ريلز'}</div>
                <div className="rounded-lg bg-slate-50 p-2"><div className="font-bold">{s.outfits_required ?? '—'}</div>لبس</div>
                <div className="rounded-lg bg-slate-50 p-2"><div className="font-bold">{s.locations_required ?? '—'}</div>لوكيشن</div>
              </div>
              <div className={cn('mt-2 text-sm', Number(s.approved_scripts_unused) < Number(s.planned_qty) && s.deliverable_type === 'reel' ? 'text-amber-700' : 'text-slate-600')}>
                📝 سكربتات جاهزة: {s.approved_scripts_unused}
              </div>
              <div className="mt-4 grid grid-cols-2 gap-2">
                {s.status === 'booked' && canOps && (
                  <>
                    <ActionButton action={checkIn.bind(null, s.session_id!)} variant="success" size="lg">✅ وصل</ActionButton>
                    <ActionButton action={noShow.bind(null, s.session_id!)} variant="danger" size="lg" confirm={`متأكد إن ${s.client_name} مجاش؟ هيتخصم من الرصيد.`}>🟥 مجاش</ActionButton>
                  </>
                )}
                <Link href={`/sessions/${s.session_id}`} className="col-span-2 flex min-h-14 items-center justify-center rounded-lg bg-brand-600 text-base font-medium text-white">
                  {s.status === 'checked_in' ? 'المانوال وقفل الجلسة' : 'فتح الجلسة'}
                </Link>
                <a href={waLink(s.client_phone)} target="_blank" rel="noreferrer" className="col-span-2 text-center text-sm text-emerald-700 underline">واتساب العميل</a>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
