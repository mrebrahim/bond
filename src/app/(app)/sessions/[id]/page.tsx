import Link from 'next/link';
import { notFound } from 'next/navigation';
import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { CONFIRMATION, SESSION_STATUS } from '@/lib/labels';
import { loadChecklist } from '@/lib/checklists';
import { cairoHm, cairoYmd, dayTime, qty, time, waLink } from '@/lib/format';
import { Badge, Card, CardTitle, Field, Input, Select, Textarea } from '@/components/ui';
import { ActionButton, ActionForm, Modal, SubmitButton } from '@/components/ui/client';
import { Checklist } from '@/components/checklist/checklist';
import { cancelSession, checkIn, completeSession, noShow, rescheduleSession, setConfirmation } from '@/lib/actions/sessions';

export default async function SessionPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const staff = await getStaff();
  const supabase = await createClient();
  const { data: s } = await supabase.from('studio_sessions')
    .select('*, clients(id, name, phone_wa), studios(name), staff!studio_sessions_photographer_id_fkey(name)')
    .eq('id', id).maybeSingle();
  if (!s) notFound();

  const [{ count: doneBefore }, { data: studios }, { data: photographers }] = await Promise.all([
    supabase.from('studio_sessions').select('id', { count: 'exact', head: true }).eq('client_id', s.client_id).eq('status', 'done').neq('id', id),
    supabase.from('studios').select('id, name').eq('active', true),
    supabase.from('staff').select('id, name').contains('roles', ['studio']).eq('active', true),
  ]);
  const first = (doneBefore ?? 0) === 0;
  const stages = [...(first ? ['onboarding'] : []), 'pre_shoot', 'shoot'];
  const groups = await loadChecklist(supabase, stages, s.deliverable_type, { session_id: id });
  const missing = groups.flatMap((g) => g.items).filter((i) => i.is_required && !i.done).length;

  const open = s.status === 'booked' || s.status === 'checked_in';
  const canOps = hasRole(staff.roles, CAN.sessionOps);
  const unit = s.deliverable_type === 'trainer_hours' ? 'hours' : 'count';

  return (
    <div className="mx-auto max-w-2xl">
      <Card className="mb-4">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div>
            <Link href={`/clients/${s.clients?.id}`} className="text-xl font-bold hover:underline">{s.clients?.name}</Link>
            <div className="text-sm text-slate-500">{dayTime(s.booked_start)} → {time(s.booked_end)} · {s.studios?.name} · {s.staff?.name ?? 'من غير مصور'}</div>
          </div>
          <div className="flex flex-wrap gap-1">
            <Badge className={SESSION_STATUS[s.status].cls}>{SESSION_STATUS[s.status].label}</Badge>
            {s.status === 'booked' && <Badge className={CONFIRMATION[s.confirmation].cls}>{CONFIRMATION[s.confirmation].label}</Badge>}
            {first && <Badge className="bg-violet-100 text-violet-800">⭐ أول جلسة</Badge>}
            {s.late_cancel && <Badge className="bg-red-100 text-red-700">إلغاء متأخر</Badge>}
          </div>
        </div>
        <div className="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
          <div className="rounded-lg bg-slate-50 p-2"><b>{qty(s.actual_qty ?? s.planned_qty, unit)}</b><div>{unit === 'hours' ? 'ساعات' : 'ريلز'}</div></div>
          <div className="rounded-lg bg-slate-50 p-2"><b>{s.outfits_required ?? '—'}</b><div>لبس</div></div>
          <div className="rounded-lg bg-slate-50 p-2"><b>{s.locations_required ?? '—'}</b><div>لوكيشن</div></div>
        </div>
        {s.notes && <p className="mt-3 rounded-lg bg-amber-50 p-2 text-sm">{s.notes}</p>}
        <div className="mt-4 flex flex-wrap gap-2">
          <a href={waLink(s.clients?.phone_wa)} target="_blank" rel="noreferrer" className="inline-flex min-h-11 items-center rounded-lg border border-emerald-300 px-3 text-sm text-emerald-700">واتساب</a>
          {canOps && s.status === 'booked' && (
            <>
              <ActionButton action={checkIn.bind(null, id)} variant="success">✅ وصل</ActionButton>
              <ActionButton action={noShow.bind(null, id)} variant="danger" confirm="متأكد إنه مجاش؟ هيتخصم من الرصيد.">مجاش</ActionButton>
              {s.confirmation !== 'confirmed' && <ActionButton action={setConfirmation.bind(null, id, 'confirmed')}>أكّد</ActionButton>}
              <Modal trigger="تعديل الميعاد" title="تعديل الجلسة">
                {(close) => (
                  <ActionForm action={rescheduleSession.bind(null, id)} onSuccess={close} reset={false} className="flex flex-col gap-3">
                    <div className="grid grid-cols-2 gap-3">
                      <Field label="اليوم"><Input name="date" type="date" dir="ltr" defaultValue={cairoYmd(s.booked_start)} /></Field>
                      <Field label="الساعة"><Input name="time" type="time" dir="ltr" step={900} defaultValue={cairoHm(s.booked_start)} /></Field>
                      <Field label="العدد"><Input name="planned_qty" type="number" min={0.5} step={unit === 'hours' ? 0.5 : 1} defaultValue={Number(s.planned_qty)} /></Field>
                      <Field label="الاستوديو">
                        <Select name="studio_id" defaultValue={s.studio_id}>{(studios ?? []).map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</Select>
                      </Field>
                      <Field label="المصور" className="col-span-2">
                        <Select name="photographer_id" defaultValue={s.photographer_id ?? ''}>
                          <option value="">—</option>
                          {(photographers ?? []).map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
                        </Select>
                      </Field>
                    </div>
                    <Field label="ملاحظات"><Textarea name="notes" defaultValue={s.notes ?? ''} /></Field>
                    <SubmitButton>احفظ</SubmitButton>
                  </ActionForm>
                )}
              </Modal>
              <Modal trigger="إلغاء" title="إلغاء الجلسة" triggerVariant="ghost">
                {(close) => (
                  <ActionForm action={cancelSession.bind(null, id)} onSuccess={close} className="flex flex-col gap-3">
                    <p className="text-sm text-slate-600">الإلغاء قبل الميعاد بأقل من 24 ساعة بيتخصم من الرصيد.</p>
                    <Field label="السبب"><Textarea name="reason" required /></Field>
                    <SubmitButton variant="danger">إلغاء الجلسة</SubmitButton>
                  </ActionForm>
                )}
              </Modal>
            </>
          )}
        </div>
      </Card>

      <Card className="mb-4">
        <CardTitle>المانوال</CardTitle>
        <Checklist groups={groups} target={{ session_id: id }} disabled={!open} />
      </Card>

      {open && canOps && (
        <Card className="mb-8">
          <CardTitle>خلصت الجلسة</CardTitle>
          {missing > 0 && <p className="mb-3 text-sm text-red-600">⛔ فاضل {missing} بند إجباري في المانوال</p>}
          <ActionForm action={completeSession.bind(null, id)} reset={false} className="flex flex-col gap-3">
            {unit === 'count' ? (
              <Field label="عدد الريلز اللي اتصورت فعلًا">
                <Input name="actual_qty" type="number" min={1} defaultValue={Number(s.planned_qty)} required className="text-lg" />
              </Field>
            ) : (
              <div className="grid grid-cols-3 gap-3">
                <input type="hidden" name="date" value={cairoYmd(s.booked_start)} />
                <Field label="البداية"><Input name="actual_start" type="time" dir="ltr" defaultValue={cairoHm(s.actual_start ?? s.booked_start)} /></Field>
                <Field label="النهاية"><Input name="actual_end" type="time" dir="ltr" /></Field>
                <Field label="أو الساعات"><Input name="actual_qty" type="number" min={0.25} step={0.25} /></Field>
              </div>
            )}
            <SubmitButton size="lg" disabled={missing > 0}>خلصت الجلسة</SubmitButton>
          </ActionForm>
        </Card>
      )}
    </div>
  );
}
