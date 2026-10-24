import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { dayTime, money, num, shortDate } from '@/lib/format';
import { Card, CardTitle, Empty, Field, Input, PageHeader, cn } from '@/components/ui';
import { ActionForm, Modal, SubmitButton } from '@/components/ui/client';
import { addRecharge, recordReading } from '@/lib/actions/power';
import { BalanceChart } from './chart';

export default async function PowerPage() {
  const staff = await getStaff();
  const supabase = await createClient();
  const { data: meters } = await supabase.from('v_meter_status').select('*').order('name');
  const since = new Date(Date.now() - 30 * 864e5).toISOString();
  const { data: readings } = await supabase.from('meter_readings').select('meter_id, balance, read_at').gte('read_at', since).order('read_at');
  const canRecord = hasRole(staff.roles, CAN.power);

  if (!meters?.length) return <Empty icon="⚡">مفيش عدادات — ضيف واحد من الإعدادات</Empty>;
  return (
    <div className="mx-auto max-w-xl">
      <PageHeader title="الكهرباء" />
      {meters.map((m) => {
        const danger = m.runs_out_before_next_session || (m.days_left !== null && Number(m.days_left) <= 2);
        return (
          <Card key={m.meter_id} className={cn('mb-4', danger && 'border-red-300')}>
            <CardTitle>{m.name}</CardTitle>
            <div className={cn('text-4xl font-bold tabular-nums', danger ? 'text-red-600' : 'text-slate-900')}>
              {m.balance_now_est !== null ? money(m.balance_now_est) : 'لسه مفيش قراية'}
            </div>
            <div className="mt-1 text-xs text-slate-500">آخر قراية: {m.last_read_at ? `${money(m.last_balance)} · ${dayTime(m.last_read_at)}` : '—'}</div>
            <div className="mt-3 grid grid-cols-2 gap-2 text-sm">
              <div className="rounded-lg bg-slate-50 p-2">بيصرف في اليوم<div className="font-bold">{m.daily_burn ? money(m.daily_burn) : '—'}</div></div>
              <div className="rounded-lg bg-slate-50 p-2">الشريحة<div className="font-bold">{m.tier ?? '—'}</div></div>
              <div className={cn('rounded-lg p-2', danger ? 'bg-red-50 text-red-800' : 'bg-slate-50')}>هيخلص<div className="font-bold">{m.runs_out_at ? dayTime(m.runs_out_at) : '—'}</div>{m.days_left !== null && <div className="text-xs">بعد {num(m.days_left)} يوم</div>}</div>
              <div className={cn('rounded-lg p-2', m.runs_out_before_next_session ? 'bg-red-50 text-red-800' : 'bg-slate-50')}>الجلسة الجاية<div className="font-bold">{m.next_session ? dayTime(m.next_session) : '—'}</div>{m.runs_out_before_next_session && <div className="text-xs font-semibold">⚠️ الرصيد هيخلص قبلها</div>}</div>
            </div>
            {canRecord && (
              <div className="mt-4 grid grid-cols-2 gap-2">
                <Modal trigger="📸 سجّل القراية" title="قراية العداد" triggerVariant="primary" triggerSize="lg">
                  {(close) => (
                    <ActionForm action={recordReading} onSuccess={close} className="flex flex-col gap-3">
                      <input type="hidden" name="meter_id" value={m.meter_id!} />
                      <Field label="الرصيد على العداد (ج.م) *"><Input name="balance" type="number" step="0.01" min={0} inputMode="decimal" required className="text-lg" /></Field>
                      <Field label="الشريحة"><Input name="tier" type="number" min={1} inputMode="numeric" defaultValue={m.tier ?? ''} /></Field>
                      <Field label="الكيلووات (اختياري)"><Input name="kwh" type="number" step="0.01" inputMode="decimal" /></Field>
                      <Field label="صورة العداد"><Input name="photo" type="file" accept="image/*" capture="environment" /></Field>
                      <SubmitButton size="lg">سجّل</SubmitButton>
                    </ActionForm>
                  )}
                </Modal>
                <Modal trigger="🔌 شحنت" title="شحن الكارت" triggerSize="lg">
                  {(close) => (
                    <ActionForm action={addRecharge} onSuccess={close} className="flex flex-col gap-3">
                      <input type="hidden" name="meter_id" value={m.meter_id!} />
                      <Field label="المبلغ (ج.م) *"><Input name="amount" type="number" min={1} inputMode="numeric" required /></Field>
                      <Field label="ملاحظة"><Input name="note" /></Field>
                      <SubmitButton size="lg">سجّل</SubmitButton>
                    </ActionForm>
                  )}
                </Modal>
              </div>
            )}
            <div className="mt-4">
              <BalanceChart data={(readings ?? []).filter((r) => r.meter_id === m.meter_id).map((r) => ({ label: shortDate(r.read_at), balance: Number(r.balance) }))} />
            </div>
          </Card>
        );
      })}
    </div>
  );
}
