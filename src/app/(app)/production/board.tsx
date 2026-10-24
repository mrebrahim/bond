'use client';

import { useEffect, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button, Field, Input, Select, cn } from '@/components/ui';
import { Modal } from '@/components/ui/client';
import { Checklist, type ChecklistGroup } from '@/components/checklist/checklist';
import { createClient } from '@/lib/supabase/client';
import { assignDeliverable, createDesign, deliverableChecklist, moveStage } from '@/lib/actions/production';
import { PLATFORMS, STAGE_LABELS } from '@/lib/labels';
import { ago } from '@/lib/format';

export type Card = {
  id: string; title: string | null; stage: string; type: string; client_id: string; client: string;
  assignee_id: string | null; assignee: string | null; revisions: number; since: string; post_url: string | null;
};
type T = { code: string; name: string; stages: string[] };
type Opt = { id: string; name: string };

export function Board({ types, cards, team, me, initialMine, canMove, clients }: {
  types: T[]; cards: Card[]; team: Opt[]; me: string; initialMine: boolean; canMove: boolean; clients: Opt[];
}) {
  const router = useRouter();
  const [type, setType] = useState(types[0]?.code ?? 'reel');
  const [mine, setMine] = useState(initialMine);
  const [client, setClient] = useState('');
  const [assignee, setAssignee] = useState('');
  const [pending, start] = useTransition();
  const [gate, setGate] = useState<{ card: Card; to: string; groups: ChecklistGroup[] } | null>(null);
  const [publish, setPublish] = useState<Card | null>(null);

  // Realtime: الكروت بتتحدث لايف
  useEffect(() => {
    const supabase = createClient();
    const ch = supabase.channel('deliverables-board')
      .on('postgres_changes', { event: '*', schema: 'public', table: 'deliverables' }, () => router.refresh())
      .subscribe();
    return () => { supabase.removeChannel(ch); };
  }, [router]);

  const t = types.find((x) => x.code === type);
  const visible = cards.filter((c) => c.type === type && (!mine || c.assignee_id === me) && (!client || c.client_id === client) && (!assignee || c.assignee_id === assignee));

  function move(card: Card, to: string) {
    if (to === 'published') { setPublish(card); return; }
    start(async () => {
      const res = await moveStage(card.id, to);
      if (res.ok) { router.refresh(); return; }
      toast.error(res.error);
      if (res.error.includes('لازم تخلص البنود')) {
        setGate({ card, to, groups: await deliverableChecklist(card.id, to) });
      }
    });
  }

  return (
    <div>
      <div className="mb-3 flex flex-wrap gap-2">
        {types.map((x) => (
          <button key={x.code} type="button" onClick={() => setType(x.code)}
            className={cn('min-h-11 rounded-lg px-4 text-sm font-medium', x.code === type ? 'bg-brand-600 text-white' : 'border border-slate-300 bg-white')}>
            {x.name}
          </button>
        ))}
      </div>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <Select value={client} onChange={(e) => setClient(e.target.value)} className="w-auto">
          <option value="">كل العملاء</option>
          {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </Select>
        <Select value={assignee} onChange={(e) => setAssignee(e.target.value)} className="w-auto">
          <option value="">كل المسؤولين</option>
          {team.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
        </Select>
        <label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" className="h-5 w-5" checked={mine} onChange={(e) => setMine(e.target.checked)} /> شغلي</label>
        {canMove && (type === 'design' || type === 'trainer_video_edit') && (
          <NewItem type={type} clients={clients} team={team} />
        )}
      </div>

      <div className={cn('flex gap-3 overflow-x-auto pb-4', pending && 'opacity-70')}>
        {(t?.stages ?? []).map((stage, si) => {
          const col = visible.filter((c) => c.stage === stage);
          return (
            <div key={stage} className="w-72 shrink-0 rounded-xl bg-slate-100 p-2">
              <div className="mb-2 flex items-center justify-between px-1 text-sm font-semibold">
                <span>{STAGE_LABELS[stage] ?? stage}</span><span className="text-xs text-slate-500">{col.length}</span>
              </div>
              <div className="flex flex-col gap-2">
                {col.map((c) => {
                  const lateEdit = stage === 'editing' && Date.now() - new Date(c.since).getTime() > 2 * 864e5;
                  return (
                    <div key={c.id} className="rounded-lg border border-slate-200 bg-white p-3 text-sm shadow-sm">
                      <div className="font-semibold">{c.client}</div>
                      <div className="text-slate-600">{c.title ?? '—'}</div>
                      <div className="mt-1 flex flex-wrap gap-x-3 text-xs text-slate-500">
                        <span className={cn(lateEdit && 'font-bold text-red-600')}>⏱ {ago(c.since)}</span>
                        {c.revisions > 0 && <span>✏️ {c.revisions} تعديل</span>}
                        {c.post_url && <a className="text-brand-700 underline" href={c.post_url} target="_blank" rel="noreferrer">البوست</a>}
                      </div>
                      {canMove && (
                        <>
                          <Select value={c.assignee_id ?? ''} className="mt-2 min-h-9 text-xs"
                            onChange={(e) => start(async () => { const r = await assignDeliverable(c.id, e.target.value || null); if (!r.ok) toast.error(r.error); router.refresh(); })}>
                            <option value="">بدون مسؤول</option>
                            {team.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                          </Select>
                          <div className="mt-2 flex gap-1">
                            {si > 0 && <button type="button" onClick={() => move(c, t!.stages[si - 1])} className="min-h-9 flex-1 rounded-md border border-slate-200 text-xs hover:bg-slate-50">→ {STAGE_LABELS[t!.stages[si - 1]]}</button>}
                            {si < t!.stages.length - 1 && <button type="button" onClick={() => move(c, t!.stages[si + 1])} className="min-h-9 flex-1 rounded-md bg-brand-600 text-xs text-white hover:bg-brand-700">{STAGE_LABELS[t!.stages[si + 1]]} ←</button>}
                          </div>
                        </>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          );
        })}
      </div>

      <Modal title={gate ? `مانوال قبل "${STAGE_LABELS[gate.to]}"` : ''} open={!!gate} onOpenChange={(o) => !o && setGate(null)}>
        {gate && (
          <div className="flex flex-col gap-4">
            <Checklist groups={gate.groups} target={{ deliverable_id: gate.card.id }} />
            <Button onClick={() => { const g = gate; setGate(null); move(g.card, g.to); }}>خلصت — انقل الكارت</Button>
          </div>
        )}
      </Modal>
      <PublishDialog card={publish} onClose={() => setPublish(null)} onGate={(card, groups) => setGate({ card, to: 'published', groups })} />
    </div>
  );
}

function PublishDialog({ card, onClose, onGate }: { card: Card | null; onClose: () => void; onGate: (c: Card, g: ChecklistGroup[]) => void }) {
  const router = useRouter();
  const [url, setUrl] = useState('');
  const [platforms, setPlatforms] = useState<string[]>([]);
  const [pending, start] = useTransition();
  return (
    <Modal title="اتنشر" open={!!card} onOpenChange={(o) => !o && onClose()}>
      {card && (
        <div className="flex flex-col gap-3">
          <p className="text-sm text-slate-600">{card.client} — {card.title}</p>
          <Field label="لينك البوست *"><Input dir="ltr" type="url" value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://instagram.com/p/..." /></Field>
          <Field label="المنصات">
            <div className="flex flex-wrap gap-3">
              {Object.entries(PLATFORMS).map(([k, v]) => (
                <label key={k} className="flex items-center gap-1.5 text-sm">
                  <input type="checkbox" className="h-4 w-4" checked={platforms.includes(k)} onChange={(e) => setPlatforms(e.target.checked ? [...platforms, k] : platforms.filter((p) => p !== k))} />{v}
                </label>
              ))}
            </div>
          </Field>
          <p className="text-xs text-slate-500">ده اللي بيبعت للعميل رسالة &quot;الريل نزل&quot; + لينك التقييم.</p>
          <Button disabled={!url || pending} onClick={() => start(async () => {
            const res = await moveStage(card.id, 'published', { post_url: url, platforms });
            if (res.ok) { toast.success(res.message ?? 'تم'); setUrl(''); onClose(); router.refresh(); return; }
            toast.error(res.error);
            if (res.error.includes('لازم تخلص البنود')) { onClose(); onGate(card, await deliverableChecklist(card.id, 'published')); }
          })}>اتنشر</Button>
        </div>
      )}
    </Modal>
  );
}

function NewItem({ type, clients, team }: { type: string; clients: Opt[]; team: Opt[] }) {
  return (
    <Modal trigger={type === 'design' ? '+ تصميم' : '+ فيديو مدرب'} title={type === 'design' ? 'تصميم جديد' : 'فيديو مدرب جديد'} triggerSize="sm" triggerVariant="primary">
      {(close) => <NewItemForm type={type} clients={clients} team={team} close={close} />}
    </Modal>
  );
}

function NewItemForm({ type, clients, team, close }: { type: string; clients: Opt[]; team: Opt[]; close: () => void }) {
  const router = useRouter();
  const [pending, start] = useTransition();
  return (
    <form className="flex flex-col gap-3" onSubmit={(e) => {
      e.preventDefault();
      const fd = new FormData(e.currentTarget);
      fd.set('deliverable_type', type);
      start(async () => { const r = await createDesign(fd); if (r.ok) { toast.success(r.message ?? 'تم'); close(); router.refresh(); } else toast.error(r.error); });
    }}>
      <Field label="العميل">
        <Select name="client_id" required>{clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select>
      </Field>
      <Field label="العنوان"><Input name="title" /></Field>
      <Field label="المسؤول">
        <Select name="assignee_id"><option value="">—</option>{team.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}</Select>
      </Field>
      <Button type="submit" disabled={pending}>أضف</Button>
    </form>
  );
}
