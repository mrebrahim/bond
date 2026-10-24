'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button, Input, cn } from '@/components/ui';
import { saveSchedule } from '@/lib/actions/clients';
import { WEEKDAYS } from '@/lib/format';

type Row = { deliverable_type: string; weekday: number; publish_time: string | null; platforms: string[] | null };

export function ScheduleEditor({ clientId, rows, platforms, canEdit }: { clientId: string; rows: Row[]; platforms: string[]; canEdit: boolean }) {
  const router = useRouter();
  const [pending, start] = useTransition();
  const init = (t: 'reel' | 'design') => WEEKDAYS.map((_, wd) => {
    const r = rows.find((x) => x.deliverable_type === t && x.weekday === wd);
    return { on: !!r, time: r?.publish_time?.slice(0, 5) ?? (t === 'reel' ? '20:00' : '14:00') };
  });
  const [grid, setGrid] = useState({ reel: init('reel'), design: init('design') });

  return (
    <div className="flex flex-col gap-4">
      {(['reel', 'design'] as const).map((t) => (
        <div key={t}>
          <h4 className="mb-2 text-sm font-semibold">{t === 'reel' ? '🎬 ريلز' : '🎨 تصميمات'}</h4>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-7">
            {WEEKDAYS.map((d, wd) => {
              const s = grid[t][wd];
              const set = (p: Partial<typeof s>) => setGrid({ ...grid, [t]: grid[t].map((x, i) => (i === wd ? { ...x, ...p } : x)) });
              return (
                <div key={d} className={cn('rounded-lg border p-2', s.on ? 'border-brand-400 bg-brand-50' : 'border-slate-200')}>
                  <label className="flex min-h-9 items-center gap-2 text-sm">
                    <input type="checkbox" disabled={!canEdit} checked={s.on} onChange={(e) => set({ on: e.target.checked })} className="h-4 w-4" />{d}
                  </label>
                  {s.on && <Input type="time" dir="ltr" disabled={!canEdit} value={s.time} onChange={(e) => set({ time: e.target.value })} className="mt-1 min-h-9" />}
                </div>
              );
            })}
          </div>
        </div>
      ))}
      {canEdit && (
        <Button className="self-start" disabled={pending} onClick={() => start(async () => {
          const out = (['reel', 'design'] as const).flatMap((t) => grid[t].map((s, wd) => ({ s, wd })).filter(({ s }) => s.on)
            .map(({ s, wd }) => ({ deliverable_type: t, weekday: wd, publish_time: s.time, platforms })));
          const res = await saveSchedule(clientId, out);
          if (res.ok) { toast.success(res.message ?? 'تم'); router.refresh(); } else toast.error(res.error);
        })}>احفظ الجدول</Button>
      )}
    </div>
  );
}
