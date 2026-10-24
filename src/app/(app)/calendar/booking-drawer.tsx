'use client';

import { useEffect, useMemo, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button, Field, Input, Select, Textarea, cn } from '@/components/ui';
import { Modal } from '@/components/ui/client';
import { bookSession, clientBalances, previewBooking, type BookingPreview } from '@/lib/actions/sessions';
import { cairoToIso, day, hours as fmtHours, minutesLabel, num, qty as fmtQty } from '@/lib/format';

type Opt = { id: string; name: string };
type Bal = Awaited<ReturnType<typeof clientBalances>>[number];

export function BookingDrawer({
  clients, studios, photographers, open, onOpenChange, preset,
}: {
  clients: (Opt & { code: string | null })[];
  studios: Opt[];
  photographers: Opt[];
  open: boolean;
  onOpenChange: (o: boolean) => void;
  preset: { date: string; time: string; studioId?: string; clientId?: string };
}) {
  const router = useRouter();
  const [pending, start] = useTransition();
  const [search, setSearch] = useState('');
  const [clientId, setClientId] = useState(preset.clientId ?? '');
  const [bal, setBal] = useState<Bal[]>([]);
  const [type, setType] = useState('reel');
  const [qty, setQty] = useState(4);
  const [date, setDate] = useState(preset.date);
  const [time, setTime] = useState(preset.time);
  const [studioId, setStudioId] = useState(preset.studioId ?? studios[0]?.id ?? '');
  const [photographerId, setPhotographerId] = useState('');
  const [notes, setNotes] = useState('');
  const [preview, setPreview] = useState<BookingPreview | null>(null);

  useEffect(() => { setDate(preset.date); setTime(preset.time); if (preset.studioId) setStudioId(preset.studioId); if (preset.clientId) setClientId(preset.clientId); }, [preset]);

  useEffect(() => {
    if (!clientId) { setBal([]); return; }
    clientBalances(clientId).then((b) => {
      setBal(b);
      const bookable = b.filter((x) => x.deliverable_type === 'reel' || x.deliverable_type === 'trainer_hours');
      if (bookable.length && !bookable.some((x) => x.deliverable_type === type)) setType(bookable[0].deliverable_type!);
    });
  }, [clientId]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!clientId || !qty) { setPreview(null); return; }
    const t = setTimeout(() => {
      previewBooking(clientId, type, qty, date && time ? cairoToIso(date, time) : null).then(setPreview);
    }, 250);
    return () => clearTimeout(t);
  }, [clientId, type, qty, date, time]);

  const filtered = useMemo(() => {
    const s = search.trim();
    return s ? clients.filter((c) => c.name.includes(s) || c.code?.toLowerCase().includes(s.toLowerCase())) : clients;
  }, [clients, search]);
  const bookable = bal.filter((b) => b.deliverable_type === 'reel' || b.deliverable_type === 'trainer_hours');
  const cur = bal.find((b) => b.deliverable_type === type);

  function save() {
    const fd = new FormData();
    Object.entries({ client_id: clientId, deliverable_type: type, planned_qty: String(qty), date, time, studio_id: studioId, photographer_id: photographerId, notes })
      .forEach(([k, v]) => fd.set(k, v));
    start(async () => {
      const res = await bookSession(fd);
      if (!res.ok) { toast.error(res.error); return; }
      toast.success(res.message ?? 'تم');
      onOpenChange(false);
      router.refresh();
    });
  }

  return (
    <Modal title="حجز جلسة" open={open} onOpenChange={onOpenChange}>
      <div className="flex flex-col gap-3">
        <Field label="العميل">
          <Input placeholder="بحث بالاسم أو الكود" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Select value={clientId} onChange={(e) => setClientId(e.target.value)} size={Math.min(6, Math.max(2, filtered.length + 1))} className="mt-1 h-auto">
            <option value="">— اختار —</option>
            {filtered.map((c) => <option key={c.id} value={c.id}>{c.name} ({c.code})</option>)}
          </Select>
        </Field>
        {clientId && (
          <div className={cn('rounded-lg p-3 text-sm', cur ? 'bg-blue-50 text-blue-900' : 'bg-red-50 text-red-800')}>
            {cur
              ? <>فاضله {fmtQty(cur.remaining, cur.unit_kind)} {cur.name_ar} — الباقة لحد {day(cur.end_date)}{cur.cycle_status === 'pending_payment' && ' (لسه متدفعتش)'}</>
              : 'مفيش باقة نشطة فيها تصوير للعميل ده'}
          </div>
        )}
        <div className="grid grid-cols-2 gap-3">
          <Field label="النوع">
            <Select value={type} onChange={(e) => setType(e.target.value)}>
              {(bookable.length ? bookable : [{ deliverable_type: 'reel', name_ar: 'ريل' }]).map((b) => (
                <option key={b.deliverable_type} value={b.deliverable_type!}>{b.deliverable_type === 'reel' ? 'ريلز' : 'ساعات مدربين'}</option>
              ))}
            </Select>
          </Field>
          <Field label={type === 'trainer_hours' ? 'الساعات' : 'عدد الريلز'}>
            <Input type="number" min={type === 'trainer_hours' ? 0.5 : 1} step={type === 'trainer_hours' ? 0.5 : 1} value={qty} onChange={(e) => setQty(Number(e.target.value))} />
          </Field>
          <Field label="اليوم"><Input type="date" dir="ltr" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label="الساعة"><Input type="time" dir="ltr" step={900} value={time} onChange={(e) => setTime(e.target.value)} /></Field>
          <Field label="الاستوديو">
            <Select value={studioId} onChange={(e) => setStudioId(e.target.value)}>{studios.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</Select>
          </Field>
          <Field label="المصور">
            <Select value={photographerId} onChange={(e) => setPhotographerId(e.target.value)}>
              <option value="">—</option>
              {photographers.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </Select>
          </Field>
        </div>
        <Field label="ملاحظات"><Textarea value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>

        {preview && (
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
            <div className="mb-1 font-semibold">قبل الحفظ</div>
            <ul className="space-y-1">
              <li>⏱️ المدة المتوقعة: {minutesLabel(preview.duration_minutes)}</li>
              {preview.unit === 'count' && <li>👕 {num(preview.outfits)} لبس · 📍 {num(preview.locations)} لوكيشن</li>}
              {preview.unit === 'count' && (
                <li className={cn(preview.scripts_short && 'font-semibold text-amber-700')}>
                  {preview.scripts_short ? '⚠️' : '📝'} السكربتات المتوافق عليها: {preview.approved_scripts} من {qty}
                </li>
              )}
              {preview.covers_until && <li>📅 الحجز ده هيغطي النشر لحد {day(preview.covers_until)}</li>}
              <li className={cn(!preview.enough && 'font-semibold text-red-700')}>
                {preview.enough ? '✔' : '✘'} المتاح بعد الحجوزات التانية: {preview.unit === 'hours' ? fmtHours(preview.available) : num(preview.available)}
              </li>
            </ul>
          </div>
        )}
        <Button onClick={save} disabled={pending || !clientId || !studioId}>{pending ? '...' : 'احجز'}</Button>
      </div>
    </Modal>
  );
}
