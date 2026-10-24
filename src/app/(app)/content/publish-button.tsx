'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button, Field, Input } from '@/components/ui';
import { Modal } from '@/components/ui/client';
import { moveStage } from '@/lib/actions/production';

export function PublishButton({ deliverableId, title }: { deliverableId: string; title: string }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState('');
  const [pending, start] = useTransition();
  return (
    <>
      <Button size="sm" variant="success" onClick={() => setOpen(true)}>اتنشر</Button>
      <Modal title={`نشر: ${title}`} open={open} onOpenChange={setOpen}>
        <div className="flex flex-col gap-3">
          <Field label="لينك البوست *"><Input dir="ltr" type="url" value={url} onChange={(e) => setUrl(e.target.value)} /></Field>
          <Button disabled={!url || pending} onClick={() => start(async () => {
            const r = await moveStage(deliverableId, 'published', { post_url: url });
            if (r.ok) { toast.success(r.message ?? 'تم'); setOpen(false); router.refresh(); }
            else toast.error(r.error.includes('لازم تخلص البنود') ? `${r.error} — افتح لوحة الإنتاج وكمّل المانوال` : r.error);
          })}>تأكيد</Button>
        </div>
      </Modal>
    </>
  );
}
