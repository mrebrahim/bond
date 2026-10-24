'use client';

import { useState } from 'react';
import { Field, Input, Select, Textarea } from '@/components/ui';
import { ActionForm, Modal, SubmitButton } from '@/components/ui/client';
import { ItemsEditor, type DType, type Item } from '@/components/clients/items-editor';
import { addTopup, cancelSubscription, changeSubscription, newSubscription, recordPayment } from '@/lib/actions/clients';
import { PAYMENT_METHODS } from '@/lib/labels';
import { money } from '@/lib/format';

type Inv = { id: string; description: string | null; amount: number; paid_amount: number };

export function TopupButton({ cycleId, types }: { cycleId: string; types: DType[] }) {
  return (
    <Modal trigger="Top-up" title="رصيد زيادة (فوري)">
      {(close) => (
        <ActionForm action={addTopup.bind(null, cycleId)} onSuccess={close} className="flex flex-col gap-3">
          <Field label="الخدمة">
            <Select name="type">{types.filter((t) => t.unit_kind !== 'flat').map((t) => <option key={t.code} value={t.code}>{t.name_ar}</option>)}</Select>
          </Field>
          <Field label="الكمية"><Input name="qty" type="number" min={0.25} step={0.25} required /></Field>
          <Field label="السعر (ج.م)"><Input name="price" type="number" min={0} required /></Field>
          <SubmitButton>أضف</SubmitButton>
        </ActionForm>
      )}
    </Modal>
  );
}

export function ChangeSubButton({ subId, types, current, price, validity }: { subId: string; types: DType[]; current: Item[]; price: number; validity: number }) {
  const [items, setItems] = useState<Item[]>(current);
  return (
    <Modal trigger="Upgrade / Downgrade" title="تغيير الباقة (من الدورة الجاية)">
      {(close) => (
        <ActionForm action={changeSubscription.bind(null, subId)} onSuccess={close} reset={false} className="flex flex-col gap-3">
          <Field label="النوع">
            <Select name="event"><option value="upgrade">Upgrade</option><option value="downgrade">Downgrade</option><option value="special_offer">عرض خاص</option></Select>
          </Field>
          <Field label="السعر الجديد"><Input name="price" type="number" min={0} defaultValue={price} required /></Field>
          <Field label="المدة (أيام)"><Input name="validity_days" type="number" min={1} defaultValue={validity} /></Field>
          <Field label="الخدمات"><ItemsEditor types={types} items={items} onChange={setItems} /></Field>
          <input type="hidden" name="items" value={JSON.stringify(items)} />
          <Field label="ملاحظة"><Textarea name="note" /></Field>
          <SubmitButton>سجّل</SubmitButton>
        </ActionForm>
      )}
    </Modal>
  );
}

export function CancelSubButton({ subId }: { subId: string }) {
  return (
    <Modal trigger="إلغاء الاشتراك" title="العميل مش هيجدد" triggerVariant="ghost">
      {(close) => (
        <ActionForm action={cancelSubscription.bind(null, subId)} onSuccess={close} className="flex flex-col gap-3">
          <p className="text-sm text-slate-600">الدورة الحالية هتكمل لآخرها، والتجديد اللي لسه متدفعش هيتلغي.</p>
          <Field label="السبب"><Textarea name="reason" required /></Field>
          <SubmitButton variant="danger">تأكيد الإلغاء</SubmitButton>
        </ActionForm>
      )}
    </Modal>
  );
}

export function PaymentButton({ invoices, invoiceId, label = 'تسجيل دفع' }: { invoices: Inv[]; invoiceId?: string; label?: string }) {
  const [sel, setSel] = useState(invoiceId ?? invoices[0]?.id ?? '');
  const inv = invoices.find((i) => i.id === sel);
  if (!invoices.length) return null;
  return (
    <Modal trigger={label} title="تسجيل دفع" triggerVariant="primary" triggerSize={invoiceId ? 'sm' : 'md'}>
      {(close) => (
        <ActionForm action={recordPayment} onSuccess={close} className="flex flex-col gap-3">
          <Field label="الفاتورة">
            <Select name="invoice_id" value={sel} onChange={(e) => setSel(e.target.value)}>
              {invoices.map((i) => <option key={i.id} value={i.id}>{i.description} — باقي {money(i.amount - i.paid_amount)}</option>)}
            </Select>
          </Field>
          <Field label="المبلغ"><Input key={sel} name="amount" type="number" min={1} defaultValue={inv ? inv.amount - inv.paid_amount : ''} required /></Field>
          <Field label="الطريقة">
            <Select name="method">{Object.entries(PAYMENT_METHODS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</Select>
          </Field>
          <Field label="المرجع (رقم العملية)"><Input name="reference" dir="ltr" /></Field>
          <SubmitButton>سجّل</SubmitButton>
        </ActionForm>
      )}
    </Modal>
  );
}

export function NewSubButton({ clientId, types, today }: { clientId: string; types: DType[]; today: string }) {
  const [items, setItems] = useState<Item[]>([{ type: 'reel', qty: 10 }]);
  return (
    <Modal trigger="+ اشتراك" title="اشتراك جديد" triggerVariant="primary">
      {(close) => (
        <ActionForm action={newSubscription.bind(null, clientId)} onSuccess={close} className="flex flex-col gap-3">
          <Field label="اسم الباقة"><Input name="name" required /></Field>
          <Field label="السعر"><Input name="price" type="number" min={0} required /></Field>
          <Field label="المدة (أيام)"><Input name="validity_days" type="number" min={1} defaultValue={30} /></Field>
          <Field label="البداية"><Input name="start" type="date" dir="ltr" defaultValue={today} /></Field>
          <Field label="سعر الساعة الزيادة (للمدربين)"><Input name="overage_rate" type="number" min={0} /></Field>
          <Field label="الخدمات"><ItemsEditor types={types} items={items} onChange={setItems} /></Field>
          <input type="hidden" name="items" value={JSON.stringify(items)} />
          <SubmitButton>إنشاء</SubmitButton>
        </ActionForm>
      )}
    </Modal>
  );
}
