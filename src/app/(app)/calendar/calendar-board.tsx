'use client';

import Link from 'next/link';
import { useState } from 'react';
import { cn } from '@/components/ui';
import { BookingDrawer } from './booking-drawer';
import { day, time } from '@/lib/format';

export type CalSession = {
  id: string; day: string; booked_start: string; booked_end: string | null; status: string; confirmation: string;
  planned_qty: number; deliverable_type: string; studio_id: string; photographer_id: string | null;
  client_name: string; photographer: string | null; soon: boolean;
};
type Opt = { id: string; name: string };

function chip(s: CalSession) {
  if (s.status === 'checked_in') return 'border-emerald-400 bg-emerald-50 text-emerald-900';
  if (s.status === 'done') return 'border-slate-300 bg-slate-50 text-slate-600';
  if (s.status === 'no_show') return 'border-red-300 bg-red-50 text-red-800';
  if (s.status === 'cancelled') return 'border-slate-200 bg-white text-slate-400 line-through';
  if (s.confirmation === 'confirmed') return 'border-green-400 bg-green-50 text-green-900';
  if (s.confirmation === 'reschedule_requested') return 'border-orange-400 bg-orange-50 text-orange-900';
  if (s.soon) return 'border-amber-400 bg-amber-50 text-amber-900';
  return 'border-blue-300 bg-blue-50 text-blue-900';
}

export function CalendarBoard({
  days, columns, by, sessions, clients, studios, photographers, canBook, bookClient,
}: {
  days: string[];
  columns: Opt[];
  by: 'studio' | 'photographer';
  sessions: CalSession[];
  clients: (Opt & { code: string | null })[];
  studios: Opt[];
  photographers: Opt[];
  canBook: boolean;
  bookClient?: string;
}) {
  const [open, setOpen] = useState(!!bookClient);
  const [preset, setPreset] = useState({ date: days[0], time: '12:00', studioId: undefined as string | undefined, clientId: bookClient });
  const book = (date: string, colId?: string) => {
    if (!canBook) return;
    setPreset({ date, time: '12:00', studioId: by === 'studio' ? colId : undefined, clientId: bookClient });
    setOpen(true);
  };

  return (
    <>
      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table className="w-full min-w-[720px] table-fixed text-sm">
          <thead>
            <tr>
              <th className="w-28 bg-slate-50 px-2 py-2 text-start text-xs text-slate-500">اليوم</th>
              {columns.map((c) => <th key={c.id} className="bg-slate-50 px-2 py-2 text-start text-xs font-semibold text-slate-600">{c.name}</th>)}
            </tr>
          </thead>
          <tbody>
            {days.map((d) => (
              <tr key={d} className="align-top">
                <td className="border-t border-slate-100 px-2 py-2 text-xs font-medium text-slate-600">{day(d)}</td>
                {columns.map((c) => {
                  const list = sessions.filter((s) => s.day === d && (by === 'studio' ? s.studio_id === c.id : s.photographer_id === c.id));
                  return (
                    <td key={c.id} className="border-t border-s border-slate-100 p-1.5">
                      <div className="flex min-h-16 flex-col gap-1">
                        {list.map((s) => (
                          <Link key={s.id} href={`/sessions/${s.id}`} className={cn('rounded-md border px-2 py-1 text-xs', chip(s))}>
                            <div className="font-semibold tabular-nums">{time(s.booked_start)} – {time(s.booked_end)}</div>
                            <div className="truncate">{s.client_name}</div>
                            <div className="opacity-75">{s.deliverable_type === 'trainer_hours' ? `${s.planned_qty} ساعة` : `${s.planned_qty} ريل`}{s.photographer && ` · ${s.photographer}`}</div>
                          </Link>
                        ))}
                        {canBook && (
                          <button type="button" onClick={() => book(d, c.id)} className="mt-auto rounded-md border border-dashed border-slate-200 py-1 text-xs text-slate-400 hover:border-brand-400 hover:text-brand-600">+ حجز</button>
                        )}
                      </div>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">
        <span>🟦 محجوز</span><span>🟩 أكّد</span><span>🟨 مستني تأكيد</span><span>✅ وصل</span><span>🟥 مجاش</span><span>⬜ اتلغى</span>
      </div>
      {canBook && (
        <BookingDrawer clients={clients} studios={studios} photographers={photographers} open={open} onOpenChange={setOpen} preset={preset} />
      )}
    </>
  );
}
