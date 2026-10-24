'use client';

import { useRouter } from 'next/navigation';
import { createContext, useContext, useEffect, useRef, useState, useTransition, type ReactNode } from 'react';
import { toast } from 'sonner';
import clsx from 'clsx';
import type { ActionResult } from '@/lib/db';
import { buttonClass, type ButtonVariant } from './index';

// الـ Modal بيدّي close لأي ActionForm جواه (علشان صفحات السيرفر متبعتش functions)
const ModalClose = createContext<(() => void) | null>(null);

function report(res: ActionResult) {
  if (res.ok) {
    if (res.message) toast.success(res.message);
  } else {
    // رسايل الداتابيز عربي وجاهزة — بتتعرض زي ما هي
    toast.error(res.error);
  }
}

/** فورم بيبعت لـ server action، ويعرض النتيجة في toast، ويعمل refresh */
export function ActionForm({
  action, children, className, onSuccess, reset = true,
}: {
  action: (fd: FormData) => Promise<ActionResult>;
  children: ReactNode;
  className?: string;
  onSuccess?: (res: ActionResult) => void;
  reset?: boolean;
}) {
  const router = useRouter();
  const closeModal = useContext(ModalClose);
  const ref = useRef<HTMLFormElement>(null);
  const [pending, start] = useTransition();
  return (
    <form
      ref={ref}
      className={className}
      onSubmit={(e) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        start(async () => {
          const res = await action(fd);
          report(res);
          if (res.ok) {
            if (reset) ref.current?.reset();
            onSuccess?.(res);
            closeModal?.();
            router.refresh();
          }
        });
      }}
    >
      <fieldset disabled={pending} className="contents">{children}</fieldset>
    </form>
  );
}

export function SubmitButton({ children, variant = 'primary', size = 'md', className, disabled }: {
  children: ReactNode; variant?: ButtonVariant; size?: 'sm' | 'md' | 'lg'; className?: string; disabled?: boolean;
}) {
  return <button type="submit" disabled={disabled} className={clsx(buttonClass(variant, size), className)}>{children}</button>;
}

/** زرار بينادي action واحدة (مع تأكيد اختياري) */
export function ActionButton({
  action, children, variant = 'secondary', size = 'md', confirm, className, onDone,
}: {
  action: () => Promise<ActionResult>;
  children: ReactNode;
  variant?: ButtonVariant;
  size?: 'sm' | 'md' | 'lg';
  confirm?: string;
  className?: string;
  onDone?: (res: ActionResult) => void;
}) {
  const router = useRouter();
  const [pending, start] = useTransition();
  return (
    <button
      type="button"
      disabled={pending}
      className={clsx(buttonClass(variant, size), className)}
      onClick={() => {
        if (confirm && !window.confirm(confirm)) return;
        start(async () => {
          const res = await action();
          report(res);
          onDone?.(res);
          if (res.ok) router.refresh();
        });
      }}
    >
      {pending ? '...' : children}
    </button>
  );
}

/** Drawer/Modal بسيط — من تحت على الموبايل ومن الجنب على الديسكتوب */
export function Modal({
  trigger, title, children, triggerVariant = 'secondary', triggerSize = 'md', open: openProp, onOpenChange, triggerClassName,
}: {
  trigger?: ReactNode;
  title: string;
  children: ReactNode | ((close: () => void) => ReactNode);
  triggerVariant?: ButtonVariant;
  triggerSize?: 'sm' | 'md' | 'lg';
  open?: boolean;
  onOpenChange?: (o: boolean) => void;
  triggerClassName?: string;
}) {
  const [inner, setInner] = useState(false);
  const open = openProp ?? inner;
  const setOpen = onOpenChange ?? setInner;
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, setOpen]);
  const close = () => setOpen(false);
  return (
    <>
      {trigger && (
        <button type="button" className={clsx(buttonClass(triggerVariant, triggerSize), triggerClassName)} onClick={() => setOpen(true)}>
          {trigger}
        </button>
      )}
      {open && (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-stretch sm:justify-start" onClick={close}>
          <div
            className="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:max-h-none sm:max-w-lg sm:rounded-none"
            onClick={(e) => e.stopPropagation()}
            role="dialog"
            aria-label={title}
          >
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-semibold">{title}</h3>
              <button type="button" onClick={close} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="قفل">✕</button>
            </div>
            <ModalClose.Provider value={close}>
              {typeof children === 'function' ? children(close) : children}
            </ModalClose.Provider>
          </div>
        </div>
      )}
    </>
  );
}
