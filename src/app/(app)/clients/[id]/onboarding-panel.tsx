'use client';

import { useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Field, Input, cn } from '@/components/ui';
import { ActionForm, Modal, SubmitButton } from '@/components/ui/client';
import { Checklist, type ChecklistGroup } from '@/components/checklist/checklist';
import {
  createWhatsappGroup, linkWhatsappGroup, sendClientMessage, uploadContract, type ClientMessage,
} from '@/lib/actions/onboarding';
import type { ActionResult } from '@/lib/db';

const SEND: Record<string, { key: ClientMessage; label: string }> = {
  send_welcome: { key: 'welcome', label: 'ابعت الترحيب' },
  send_rules: { key: 'client_rules', label: 'ابعت القواعد' },
  send_booking: { key: 'booking_request', label: 'ابعت رسالة الحجز' },
  send_outfits: { key: 'outfits_reminder', label: 'ابعت تنبيه اللبس' },
  send_access_request: { key: 'access_request', label: 'ابعت طلب الأكسس' },
};

export function OnboardingPanel({ clientId, groups, group, contract, canManage }: {
  clientId: string;
  groups: ChecklistGroup[];
  group: { jid: string | null; link: string | null };
  contract: { url: string | null };
  canManage: boolean;
}) {
  const router = useRouter();
  const [pending, start] = useTransition();
  const run = (fn: () => Promise<ActionResult>) => start(async () => {
    const r = await fn();
    if (r.ok) toast.success(r.message ?? 'تم'); else toast.error(r.error);
    router.refresh();
  });
  const btn = 'shrink-0 rounded-md border border-brand-300 bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 hover:bg-brand-100 disabled:opacity-50';
  const items = groups.flatMap((g) => g.items);
  const req = items.filter((i) => i.is_required);
  const reqDone = req.filter((i) => i.done).length;

  const renderAction = (action: string) => {
    if (!canManage) return null;
    if (SEND[action]) {
      return (
        <button type="button" disabled={pending} className={btn}
          onClick={() => run(() => sendClientMessage(clientId, SEND[action].key))}>
          📤 {SEND[action].label}
        </button>
      );
    }
    if (action === 'create_group') {
      return group.jid ? (
        group.link ? <a href={group.link} target="_blank" rel="noreferrer" className={btn}>فتح الجروب</a> : null
      ) : (
        <span className="flex shrink-0 gap-1">
          <button type="button" disabled={pending} className={btn}
            onClick={() => { if (window.confirm('هعمل جروب فيه العميل والـ AM وتيم ليدر الميديا — تمام؟')) run(() => createWhatsappGroup(clientId)); }}>
            ➕ اعمل الجروب
          </button>
          <Modal trigger="🔗 عندي لينك" title="ربط جروب موجود" triggerSize="sm" triggerClassName="!min-h-0 !px-2 !py-1 !text-xs">
            <ActionForm action={linkWhatsappGroup.bind(null, clientId)} className="flex flex-col gap-3">
              <Field label="لينك دعوة الجروب" hint="رقم السيستم لازم يكون عضو في الجروب">
                <Input name="link" dir="ltr" placeholder="https://chat.whatsapp.com/..." required />
              </Field>
              <SubmitButton>اربط</SubmitButton>
            </ActionForm>
          </Modal>
        </span>
      );
    }
    if (action === 'upload_contract') {
      return (
        <span className="flex shrink-0 gap-1">
          {contract.url && <a href={contract.url} target="_blank" rel="noreferrer" className={btn}>📄 العقد</a>}
          <Modal trigger={contract.url ? 'تغيير' : '⬆️ ارفع العقد'} title="صورة التعاقد" triggerSize="sm" triggerClassName="!min-h-0 !px-2 !py-1 !text-xs">
            <ActionForm action={uploadContract.bind(null, clientId)} className="flex flex-col gap-3">
              <Field label="صورة أو PDF (لحد 10 ميجا)"><Input name="file" type="file" accept="image/*,application/pdf" required /></Field>
              <SubmitButton>ارفع</SubmitButton>
            </ActionForm>
          </Modal>
          <button type="button" disabled={pending} className={btn} onClick={() => run(() => sendClientMessage(clientId, 'contract_request'))}>📤 اطلبه</button>
        </span>
      );
    }
    return null;
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm">
        <div className="flex-1">
          <div className="font-semibold">جروب الواتساب</div>
          <div className={cn('text-xs', group.jid ? 'text-emerald-700' : 'text-amber-700')}>
            {group.jid ? 'متربط ✅ — كل رسايل العميل بتروح الجروب' : 'مفيش جروب — الرسايل بتروح على الخاص'}
          </div>
        </div>
        <div className="text-end">
          <div className="text-xs text-slate-500">الإجباري</div>
          <div className={cn('text-lg font-bold tabular-nums', reqDone === req.length ? 'text-emerald-600' : 'text-slate-800')}>{reqDone}/{req.length}</div>
        </div>
      </div>
      <Checklist groups={groups} target={{ client_id: clientId }} renderAction={renderAction} />
    </div>
  );
}
