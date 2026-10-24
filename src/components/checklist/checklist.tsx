'use client';

import { useOptimistic, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import clsx from 'clsx';
import { toggleChecklist } from '@/lib/actions/sessions';

export type ChecklistGroup = {
  id: string; name: string;
  items: { id: string; text_ar: string; is_required: boolean; client_facing: boolean; done: boolean; done_by?: string | null }[];
};

/** المانوال: كل checkbox = صف في checklist_runs باسم اللي علّمه */
export function Checklist({ groups, target, disabled }: {
  groups: ChecklistGroup[]; target: { session_id?: string; deliverable_id?: string }; disabled?: boolean;
}) {
  const router = useRouter();
  const [, start] = useTransition();
  const [state, setOpt] = useOptimistic(
    groups,
    (g: ChecklistGroup[], u: { id: string; done: boolean }) =>
      g.map((grp) => ({ ...grp, items: grp.items.map((i) => (i.id === u.id ? { ...i, done: u.done } : i)) })),
  );
  return (
    <div className="flex flex-col gap-4">
      {state.map((g) => {
        const req = g.items.filter((i) => i.is_required);
        const reqDone = req.filter((i) => i.done).length;
        return (
          <div key={g.id}>
            <div className="mb-2 flex items-center justify-between">
              <h3 className="font-semibold">{g.name}</h3>
              <span className={clsx('text-xs', reqDone === req.length ? 'text-emerald-600' : 'text-red-600')}>الإجباري {reqDone}/{req.length}</span>
            </div>
            <ul className="flex flex-col gap-1">
              {g.items.map((i) => (
                <li key={i.id}>
                  <label className={clsx('flex min-h-12 items-center gap-3 rounded-lg border px-3 py-2 text-sm',
                    i.done ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white', disabled && 'opacity-60')}>
                    <input
                      type="checkbox" className="h-5 w-5 shrink-0" checked={i.done} disabled={disabled}
                      onChange={(e) => {
                        const done = e.target.checked;
                        start(async () => {
                          setOpt({ id: i.id, done });
                          const res = await toggleChecklist(i.id, target, done);
                          if (!res.ok) toast.error(res.error);
                          router.refresh();
                        });
                      }}
                    />
                    <span className="flex-1">{i.text_ar}</span>
                    {i.is_required && <span title="إجباري">⛔</span>}
                    {i.client_facing && <span title="مع العميل">👤</span>}
                  </label>
                </li>
              ))}
            </ul>
          </div>
        );
      })}
    </div>
  );
}
