'use client';

import { useEffect, useMemo, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button, Card, Field, Input, Select, Textarea, cn } from '@/components/ui';
import { ItemsEditor, type DType, type Item } from '@/components/clients/items-editor';
import { checkScheduleDiff, createClientWizard, type WizardPayload } from '@/lib/actions/clients';
import { PAYMENT_METHODS, PLATFORMS, SHOOT_FREQUENCY } from '@/lib/labels';
import { WEEKDAYS } from '@/lib/format';

type Plan = { id: string; name: string; base_price: number; validity_days: number; plan_items: { deliverable_type: string | null; quantity: number }[] };
type Slot = { on: boolean; time: string; platforms: string[] };
type Grid = Record<'reel' | 'design', Slot[]>;

const STEPS = ['البيانات', 'بروفايل المحتوى', 'جدول النشر', 'الباقة'];
const emptyGrid = (): Grid => ({
  reel: Array.from({ length: 7 }, () => ({ on: false, time: '20:00', platforms: [] })),
  design: Array.from({ length: 7 }, () => ({ on: false, time: '14:00', platforms: [] })),
});

export function NewClientWizard({ ams, types, plans, today, canPay }: { ams: { id: string; name: string }[]; types: DType[]; plans: Plan[]; today: string; canPay: boolean }) {
  const router = useRouter();
  const [step, setStep] = useState(0);
  const [pending, start] = useTransition();

  const [info, setInfo] = useState({ name: '', business_name: '', phone_wa: '20', contact_person: '', industry: '', account_manager_id: ams[0]?.id ?? '', code: '' });
  const [profile, setProfile] = useState({
    platforms: [] as string[], shoot_frequency: 'weekly', preferred_shoot_day: '', reels_per_session_avg: '',
    needs_approval: true, approval_sla_hours: 48, caption_tone: '', brand_assets_url: '', buffer_weeks: 1,
  });
  const [grid, setGrid] = useState<Grid>(emptyGrid);
  const [withSub, setWithSub] = useState(true);
  const [sub, setSub] = useState({ plan_id: '', name: '', price: 0, validity_days: 30, start: today, overage_rate: '' });
  const [items, setItems] = useState<Item[]>([{ type: 'reel', qty: 10 }]);
  const [paid, setPaid] = useState(canPay);
  const [pay, setPay] = useState({ amount: '', method: 'instapay', reference: '' });
  const [diff, setDiff] = useState<Awaited<ReturnType<typeof checkScheduleDiff>>>([]);

  const perWeek = useMemo(() => ({
    reel: grid.reel.filter((s) => s.on).length,
    design: grid.design.filter((s) => s.on).length,
  }), [grid]);

  // تحذير لايف: نفس منطق v_schedule_package_check (محسوب في الداتابيز)
  useEffect(() => {
    if (step !== 3 || !withSub) return;
    const t = setTimeout(() => { checkScheduleDiff(perWeek, items, sub.validity_days).then(setDiff); }, 300);
    return () => clearTimeout(t);
  }, [step, withSub, perWeek, items, sub.validity_days]);

  const phoneOk = /^20\d{10}$/.test(info.phone_wa);
  const canNext = step !== 0 || (info.name.trim() && phoneOk);
  const hasHours = items.some((i) => i.type === 'trainer_hours');

  function applyPlan(id: string) {
    const p = plans.find((x) => x.id === id);
    setSub((s) => ({ ...s, plan_id: id, ...(p ? { name: p.name, price: Number(p.base_price), validity_days: p.validity_days } : {}) }));
    if (p) setItems(p.plan_items.filter((i) => i.deliverable_type).map((i) => ({ type: i.deliverable_type!, qty: Number(i.quantity) })));
  }

  function submit() {
    const payload: WizardPayload = {
      client: {
        ...info,
        account_manager_id: info.account_manager_id || null,
        platforms: profile.platforms,
        shoot_frequency: (profile.shoot_frequency || null) as WizardPayload['client']['shoot_frequency'],
        preferred_shoot_day: profile.preferred_shoot_day === '' ? null : Number(profile.preferred_shoot_day),
        reels_per_session_avg: profile.reels_per_session_avg === '' ? null : Number(profile.reels_per_session_avg),
        needs_approval: profile.needs_approval,
        approval_sla_hours: Number(profile.approval_sla_hours),
        caption_tone: profile.caption_tone,
        brand_assets_url: profile.brand_assets_url,
        buffer_weeks: Number(profile.buffer_weeks),
      },
      schedule: (['reel', 'design'] as const).flatMap((t) => grid[t].map((s, wd) => ({ s, wd })).filter(({ s }) => s.on)
        .map(({ s, wd }) => ({ deliverable_type: t, weekday: wd, publish_time: s.time || null, platforms: s.platforms.length ? s.platforms : profile.platforms }))),
      subscription: withSub ? {
        plan_id: sub.plan_id || null, name: sub.name || 'باقة', price: Number(sub.price), validity_days: Number(sub.validity_days),
        start: sub.start, overage_rate: hasHours && sub.overage_rate ? Number(sub.overage_rate) : null, items,
      } : null,
      payment: withSub && canPay && paid ? {
        amount: Number(pay.amount === '' ? sub.price : pay.amount), method: pay.method as NonNullable<WizardPayload['payment']>['method'], reference: pay.reference,
      } : null,
    };
    start(async () => {
      const res = await createClientWizard(payload);
      if (!res.ok) {
        toast.error(res.error);
        if (res.data) router.push(`/clients/${res.data as string}`);
        return;
      }
      toast.success(res.message ?? 'تم');
      router.push(`/clients/${res.data as string}`);
    });
  }

  const toggle = (arr: string[], v: string) => (arr.includes(v) ? arr.filter((x) => x !== v) : [...arr, v]);

  return (
    <div>
      <ol className="mb-5 grid grid-cols-4 gap-2 text-xs">
        {STEPS.map((s, i) => (
          <li key={s} className={cn('rounded-lg border px-2 py-2 text-center', i === step ? 'border-brand-500 bg-brand-50 font-semibold text-brand-700' : i < step ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500')}>
            {i + 1}. {s}
          </li>
        ))}
      </ol>

      <Card className="p-5">
        {step === 0 && (
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="اسم العميل *"><Input value={info.name} onChange={(e) => setInfo({ ...info, name: e.target.value })} /></Field>
            <Field label="اسم البيزنس"><Input value={info.business_name} onChange={(e) => setInfo({ ...info, business_name: e.target.value })} /></Field>
            <Field label="الواتساب *" hint={phoneOk ? undefined : <span className="text-red-600">20 + 10 أرقام (مثلًا 201012345678)</span>}>
              <Input dir="ltr" inputMode="numeric" value={info.phone_wa} onChange={(e) => setInfo({ ...info, phone_wa: e.target.value.replace(/\D/g, '') })} />
            </Field>
            <Field label="المسؤول"><Input value={info.contact_person} onChange={(e) => setInfo({ ...info, contact_person: e.target.value })} /></Field>
            <Field label="المجال"><Input value={info.industry} onChange={(e) => setInfo({ ...info, industry: e.target.value })} placeholder="مطعم، جيم، عيادة..." /></Field>
            <Field label="الـ Account Manager">
              <Select value={info.account_manager_id} onChange={(e) => setInfo({ ...info, account_manager_id: e.target.value })}>
                <option value="">—</option>
                {ams.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
              </Select>
            </Field>
            <Field label="الكود" hint="سيبه فاضي ويتعمل لوحده (C001)"><Input dir="ltr" value={info.code} onChange={(e) => setInfo({ ...info, code: e.target.value })} /></Field>
          </div>
        )}

        {step === 1 && (
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="المنصات" className="sm:col-span-2">
              <div className="flex flex-wrap gap-2">
                {Object.entries(PLATFORMS).map(([k, v]) => (
                  <button type="button" key={k} onClick={() => setProfile({ ...profile, platforms: toggle(profile.platforms, k) })}
                    className={cn('min-h-11 rounded-lg border px-3 text-sm', profile.platforms.includes(k) ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-300')}>
                    {v}
                  </button>
                ))}
              </div>
            </Field>
            <Field label="بيصوّر كل قد إيه">
              <Select value={profile.shoot_frequency} onChange={(e) => setProfile({ ...profile, shoot_frequency: e.target.value })}>
                {Object.entries(SHOOT_FREQUENCY).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </Select>
            </Field>
            <Field label="اليوم المفضل للتصوير">
              <Select value={profile.preferred_shoot_day} onChange={(e) => setProfile({ ...profile, preferred_shoot_day: e.target.value })}>
                <option value="">—</option>
                {WEEKDAYS.map((d, i) => <option key={d} value={i}>{d}</option>)}
              </Select>
            </Field>
            <Field label="متوسط الريلز في الجلسة"><Input type="number" min={1} value={profile.reels_per_session_avg} onChange={(e) => setProfile({ ...profile, reels_per_session_avg: e.target.value })} /></Field>
            <Field label="الحد الأدنى للمخزون (أسابيع)"><Input type="number" min={0.5} step={0.5} value={profile.buffer_weeks} onChange={(e) => setProfile({ ...profile, buffer_weeks: Number(e.target.value) })} /></Field>
            <label className="flex min-h-11 items-center gap-2 text-sm">
              <input type="checkbox" className="h-5 w-5" checked={profile.needs_approval} onChange={(e) => setProfile({ ...profile, needs_approval: e.target.checked })} />
              محتاج موافقة قبل النشر
            </label>
            {profile.needs_approval && (
              <Field label="بيرد في كام ساعة"><Input type="number" min={1} value={profile.approval_sla_hours} onChange={(e) => setProfile({ ...profile, approval_sla_hours: Number(e.target.value) })} /></Field>
            )}
            <Field label="نبرة الكابشن" className="sm:col-span-2"><Textarea value={profile.caption_tone} onChange={(e) => setProfile({ ...profile, caption_tone: e.target.value })} placeholder="مصري خفيف، رسمي، إيموجي كتير..." /></Field>
            <Field label="لينك البراند (لوجو / ألوان)" className="sm:col-span-2"><Input dir="ltr" value={profile.brand_assets_url} onChange={(e) => setProfile({ ...profile, brand_assets_url: e.target.value })} /></Field>
          </div>
        )}

        {step === 2 && (
          <div className="flex flex-col gap-6">
            {(['reel', 'design'] as const).map((t) => (
              <div key={t}>
                <h3 className="mb-2 font-semibold">{t === 'reel' ? '🎬 ريلز' : '🎨 تصميمات'} <span className="text-sm font-normal text-slate-500">({perWeek[t]} في الأسبوع)</span></h3>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-7">
                  {WEEKDAYS.map((d, wd) => {
                    const s = grid[t][wd];
                    const set = (patch: Partial<Slot>) => setGrid({ ...grid, [t]: grid[t].map((x, i) => (i === wd ? { ...x, ...patch } : x)) });
                    return (
                      <div key={d} className={cn('flex items-center gap-2 rounded-lg border p-2 sm:flex-col', s.on ? 'border-brand-400 bg-brand-50' : 'border-slate-200')}>
                        <label className="flex min-h-11 flex-1 items-center gap-2 text-sm sm:flex-none">
                          <input type="checkbox" className="h-5 w-5" checked={s.on} onChange={(e) => set({ on: e.target.checked })} />{d}
                        </label>
                        {s.on && <Input type="time" dir="ltr" value={s.time} onChange={(e) => set({ time: e.target.value })} className="w-28 sm:w-full" />}
                      </div>
                    );
                  })}
                </div>
              </div>
            ))}
            <p className="text-xs text-slate-500">المنصات بتاخد اللي اخترته في البروفايل ({profile.platforms.map((p) => PLATFORMS[p]).join('، ') || 'لسه ماخترتش'}).</p>
          </div>
        )}

        {step === 3 && (
          <div className="flex flex-col gap-4">
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="h-5 w-5" checked={withSub} onChange={(e) => setWithSub(e.target.checked)} /> اعمل اشتراك دلوقتي
            </label>
            {withSub && (
              <>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="قالب (اختياري)">
                    <Select value={sub.plan_id} onChange={(e) => applyPlan(e.target.value)}>
                      <option value="">من الصفر</option>
                      {plans.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </Select>
                  </Field>
                  <Field label="اسم الباقة"><Input value={sub.name} onChange={(e) => setSub({ ...sub, name: e.target.value })} placeholder="Growth" /></Field>
                  <Field label="السعر (ج.م)"><Input type="number" min={0} value={sub.price} onChange={(e) => setSub({ ...sub, price: Number(e.target.value) })} /></Field>
                  <Field label="المدة (أيام)"><Input type="number" min={1} value={sub.validity_days} onChange={(e) => setSub({ ...sub, validity_days: Number(e.target.value) })} /></Field>
                  <Field label="تاريخ البداية"><Input type="date" dir="ltr" value={sub.start} onChange={(e) => setSub({ ...sub, start: e.target.value })} /></Field>
                  {hasHours && (
                    <Field label="سعر الساعة الزيادة (ج.م)"><Input type="number" min={0} value={sub.overage_rate} onChange={(e) => setSub({ ...sub, overage_rate: e.target.value })} /></Field>
                  )}
                </div>
                <Field label="الخدمات"><ItemsEditor types={types} items={items} onChange={setItems} /></Field>
                {canPay && (
                  <div className="rounded-lg border border-slate-200 p-3">
                    <label className="flex min-h-11 items-center gap-2 text-sm font-semibold">
                      <input type="checkbox" className="h-5 w-5" checked={paid} onChange={(e) => setPaid(e.target.checked)} /> 💰 العميل دفع دلوقتي
                    </label>
                    {paid && (
                      <div className="mt-2 grid gap-4 sm:grid-cols-3">
                        <Field label="المبلغ اللي اتدفع (ج.م)">
                          <Input type="number" min={1} value={pay.amount === '' ? sub.price : pay.amount} onChange={(e) => setPay({ ...pay, amount: e.target.value })} />
                        </Field>
                        <Field label="الطريقة">
                          <Select value={pay.method} onChange={(e) => setPay({ ...pay, method: e.target.value })}>
                            {Object.entries(PAYMENT_METHODS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                          </Select>
                        </Field>
                        <Field label="رقم العملية (اختياري)"><Input dir="ltr" value={pay.reference} onChange={(e) => setPay({ ...pay, reference: e.target.value })} /></Field>
                      </div>
                    )}
                  </div>
                )}
                {diff.map((d) => (
                  <div key={d.type} className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    ⚠️ الجدول ده محتاج {Math.round(d.needed)} {d.name} والباقة فيها {d.package_qty}
                  </div>
                ))}
              </>
            )}
          </div>
        )}
      </Card>

      <div className="mt-4 flex justify-between">
        <Button variant="secondary" onClick={() => setStep((s) => s - 1)} disabled={step === 0 || pending}>السابق</Button>
        {step < 3
          ? <Button onClick={() => setStep((s) => s + 1)} disabled={!canNext}>التالي</Button>
          : <Button onClick={submit} disabled={pending || !canNext}>{pending ? '...' : 'إنشاء'}</Button>}
      </div>
    </div>
  );
}
