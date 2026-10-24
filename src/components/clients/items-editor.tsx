'use client';

import { Button, Input, Select } from '@/components/ui';

export type DType = { code: string; name_ar: string; unit_kind: string };
export type Item = { type: string; qty: number };

/** صفوف الباقة: خدمة + كمية */
export function ItemsEditor({ types, items, onChange }: { types: DType[]; items: Item[]; onChange: (i: Item[]) => void }) {
  const set = (idx: number, patch: Partial<Item>) => onChange(items.map((it, i) => (i === idx ? { ...it, ...patch } : it)));
  const used = new Set(items.map((i) => i.type));
  return (
    <div className="flex flex-col gap-2">
      {items.map((it, idx) => {
        const t = types.find((x) => x.code === it.type);
        return (
          <div key={idx} className="flex items-center gap-2">
            <Select value={it.type} onChange={(e) => set(idx, { type: e.target.value })} className="flex-1">
              {types.filter((x) => x.code === it.type || !used.has(x.code)).map((x) => (
                <option key={x.code} value={x.code}>{x.name_ar}</option>
              ))}
            </Select>
            <Input
              type="number" min={0} step={t?.unit_kind === 'hours' ? 0.25 : 1} value={it.qty}
              onChange={(e) => set(idx, { qty: Number(e.target.value) })} className="w-28"
              aria-label="الكمية"
            />
            <span className="w-12 text-xs text-slate-500">{t?.unit_kind === 'hours' ? 'ساعة' : t?.unit_kind === 'flat' ? 'شهري' : 'عدد'}</span>
            <button type="button" className="min-h-11 px-2 text-slate-400 hover:text-red-600" onClick={() => onChange(items.filter((_, i) => i !== idx))} aria-label="شيل">✕</button>
          </div>
        );
      })}
      {items.length < types.length && (
        <Button type="button" variant="ghost" size="sm" className="self-start"
          onClick={() => onChange([...items, { type: types.find((x) => !used.has(x.code))!.code, qty: 1 }])}>
          + خدمة
        </Button>
      )}
    </div>
  );
}
