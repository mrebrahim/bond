'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button, Card, CardTitle, cn } from '@/components/ui';
import { whatsappSetupWebhook, whatsappStatus } from '@/lib/actions/onboarding';
import { updateSetting } from '@/lib/actions/settings';

export function WhatsappPanel({ enabled, perDay }: { enabled: boolean; perDay: string }) {
  const router = useRouter();
  const [pending, start] = useTransition();
  const [state, setState] = useState<string | null>(null);
  const toggle = () => start(async () => {
    const fd = new FormData();
    fd.set('value', enabled ? 'false' : 'true');
    const r = await updateSetting('outbox_enabled', fd);
    if (r.ok) toast.success(enabled ? 'الإرسال وقف' : 'الإرسال اشتغل'); else toast.error(r.error);
    router.refresh();
  });
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardTitle>إرسال الرسايل للعملاء</CardTitle>
        <div className={cn('mb-3 rounded-lg p-3 text-sm', enabled ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800')}>
          {enabled ? '✅ شغال — الرسايل بتتبعت كل دقيقة (للجروب لو موجود، وإلا الخاص)' : '⛔ متقفل — الرسايل بتستنى في الطابور ومحدش بيبعت'}
        </div>
        <Button variant={enabled ? 'danger' : 'success'} disabled={pending} onClick={toggle}>{enabled ? 'وقّف الإرسال' : 'شغّل الإرسال'}</Button>
        <ul className="mt-4 list-disc space-y-1 ps-5 text-xs text-slate-500">
          <li>أقصى {perDay} رسايل أوتوماتيك لنفس العميل في اليوم (من الإعدادات ← عام ← wa_messages_per_day). رسايل المانوال اللي الفريق بيبعتها بإيده مش بتتحسب.</li>
          <li>كل رسالة بتتبعت مرة واحدة بس، و3 ثواني بين كل رسالة والتانية.</li>
        </ul>
      </Card>
      <Card>
        <CardTitle>رقم السيستم (Evolution)</CardTitle>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" disabled={pending} onClick={() => start(async () => {
            const r = await whatsappStatus();
            if (r.ok) setState(String((r.data as { state?: string })?.state)); else toast.error(r.error);
          })}>شيّك على الاتصال</Button>
          <Button variant="secondary" disabled={pending} onClick={() => start(async () => {
            const r = await whatsappSetupWebhook();
            if (r.ok) toast.success(r.message ?? 'تم'); else toast.error(r.error);
          })}>ظبّط استقبال ردود العملاء</Button>
        </div>
        {state && (
          <p className={cn('mt-3 text-sm', state === 'open' ? 'text-emerald-700' : 'text-red-700')}>
            {state === 'open' ? '✅ الرقم متصل' : `⚠️ الحالة: ${state} — افتح Evolution واعمل scan للـ QR`}
          </p>
        )}
        <p className="mt-3 text-xs text-slate-500">
          استقبال الردود بيخلّي العميل يأكد الجلسة بـ 1 أو 2، ويأكد قراية القواعد بـ &quot;قرأت&quot;. محتاج تدوسه مرة واحدة بس.
        </p>
      </Card>
    </div>
  );
}
