'use client';

import { useState } from 'react';
import { Field, Input } from '@/components/ui';
import { ActionForm, SubmitButton } from '@/components/ui/client';
import { ItemsEditor, type DType, type Item } from '@/components/clients/items-editor';
import { savePlan } from '@/lib/actions/settings';

export function PlanForm({ types }: { types: DType[] }) {
  const [items, setItems] = useState<Item[]>([{ type: 'reel', qty: 10 }]);
  return (
    <ActionForm action={savePlan} className="grid gap-3 sm:grid-cols-3" onSuccess={() => setItems([{ type: 'reel', qty: 10 }])}>
      <Field label="الاسم"><Input name="name" required /></Field>
      <Field label="السعر"><Input name="base_price" type="number" min={0} required /></Field>
      <Field label="المدة (أيام)"><Input name="validity_days" type="number" min={1} defaultValue={30} /></Field>
      <Field label="الخدمات" className="sm:col-span-3"><ItemsEditor types={types} items={items} onChange={setItems} /></Field>
      <input type="hidden" name="items" value={JSON.stringify(items)} />
      <SubmitButton className="sm:col-span-3">احفظ القالب</SubmitButton>
    </ActionForm>
  );
}
