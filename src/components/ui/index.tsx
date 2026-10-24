import Link from 'next/link';
import clsx from 'clsx';
import type { ComponentProps, ReactNode } from 'react';

export { clsx as cn };

const BTN = {
  primary: 'bg-brand-600 text-white hover:bg-brand-700 disabled:bg-brand-300',
  secondary: 'bg-white text-slate-800 border border-slate-300 hover:bg-slate-50 disabled:text-slate-400',
  danger: 'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300',
  success: 'bg-emerald-600 text-white hover:bg-emerald-700 disabled:bg-emerald-300',
  ghost: 'text-slate-700 hover:bg-slate-100',
} as const;
export type ButtonVariant = keyof typeof BTN;

export function buttonClass(variant: ButtonVariant = 'primary', size: 'sm' | 'md' | 'lg' = 'md') {
  return clsx(
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors disabled:cursor-not-allowed',
    size === 'sm' && 'min-h-9 px-3 text-sm',
    size === 'md' && 'min-h-11 px-4 text-sm',
    size === 'lg' && 'min-h-14 px-6 text-base',
    BTN[variant],
  );
}

export function Button({
  variant = 'primary', size = 'md', className, ...props
}: ComponentProps<'button'> & { variant?: ButtonVariant; size?: 'sm' | 'md' | 'lg' }) {
  return <button className={clsx(buttonClass(variant, size), className)} {...props} />;
}

export function LinkButton({
  variant = 'secondary', size = 'md', className, ...props
}: ComponentProps<typeof Link> & { variant?: ButtonVariant; size?: 'sm' | 'md' | 'lg' }) {
  return <Link className={clsx(buttonClass(variant, size), className)} {...props} />;
}

export function Card({ className, ...props }: ComponentProps<'div'>) {
  return <div className={clsx('rounded-xl border border-slate-200 bg-white p-4 shadow-sm', className)} {...props} />;
}

export function CardTitle({ children, action }: { children: ReactNode; action?: ReactNode }) {
  return (
    <div className="mb-3 flex items-center justify-between gap-2">
      <h2 className="text-base font-semibold text-slate-900">{children}</h2>
      {action}
    </div>
  );
}

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: ReactNode; actions?: ReactNode }) {
  return (
    <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  );
}

export function Badge({ className, ...props }: ComponentProps<'span'>) {
  return (
    <span
      className={clsx('inline-flex items-center gap-1 rounded-full border border-transparent px-2 py-0.5 text-xs font-medium', className ?? 'bg-slate-100 text-slate-700')}
      {...props}
    />
  );
}

export function Stat({ label, value, hint, href, tone }: {
  label: string; value: ReactNode; hint?: ReactNode; href?: string; tone?: 'red' | 'green' | 'amber';
}) {
  const body = (
    <Card className={clsx('h-full', href && 'transition hover:border-brand-400 hover:shadow')}>
      <div className="text-xs text-slate-500">{label}</div>
      <div className={clsx('mt-1 text-2xl font-bold tabular-nums',
        tone === 'red' && 'text-red-600', tone === 'green' && 'text-emerald-600', tone === 'amber' && 'text-amber-600')}>
        {value}
      </div>
      {hint && <div className="mt-1 text-xs text-slate-500">{hint}</div>}
    </Card>
  );
  return href ? <Link href={href}>{body}</Link> : body;
}

export function Progress({ value, max, tone }: { value: number; max: number; tone?: 'red' | 'amber' | 'green' }) {
  const p = max > 0 ? Math.min(100, Math.max(0, (value / max) * 100)) : 0;
  return (
    <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
      <div
        className={clsx('h-full rounded-full', tone === 'red' ? 'bg-red-500' : tone === 'amber' ? 'bg-amber-500' : tone === 'green' ? 'bg-emerald-500' : 'bg-brand-500')}
        style={{ width: `${p}%` }}
      />
    </div>
  );
}

export function Empty({ children, icon = '🗒️' }: { children: ReactNode; icon?: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-white/50 px-4 py-10 text-center text-sm text-slate-500">
      <span className="text-2xl">{icon}</span>
      {children}
    </div>
  );
}

const FIELD = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-200 disabled:bg-slate-100';

export function Field({ label, hint, children, className }: { label: string; hint?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <label className={clsx('flex flex-col gap-1 text-sm', className)}>
      <span className="font-medium text-slate-700">{label}</span>
      {children}
      {hint && <span className="text-xs text-slate-500">{hint}</span>}
    </label>
  );
}

export function Input({ className, ...props }: ComponentProps<'input'>) {
  return <input className={clsx(FIELD, 'min-h-11', className)} {...props} />;
}

export function Select({ className, ...props }: ComponentProps<'select'>) {
  return <select className={clsx(FIELD, 'min-h-11', className)} {...props} />;
}

export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
  return <textarea className={clsx(FIELD, className)} rows={3} {...props} />;
}

export function Tabs({ tabs, active, base }: { tabs: { key: string; label: string }[]; active: string; base: string }) {
  return (
    <div className="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
      {tabs.map((t) => {
        const href = `${base}${base.includes('?') ? '&' : '?'}tab=${t.key}`;
        return (
          <Link
            key={t.key}
            href={href}
            scroll={false}
            className={clsx('whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium',
              t.key === active ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800')}
          >
            {t.label}
          </Link>
        );
      })}
    </div>
  );
}

export function Table({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={clsx('overflow-x-auto rounded-xl border border-slate-200 bg-white', className)}>
      <table className="w-full text-sm">{children}</table>
    </div>
  );
}

export function Th({ children, className }: { children?: ReactNode; className?: string }) {
  return <th className={clsx('whitespace-nowrap bg-slate-50 px-3 py-2 text-start text-xs font-semibold text-slate-500', className)}>{children}</th>;
}

export function Td({ children, className }: { children?: ReactNode; className?: string }) {
  return <td className={clsx('border-t border-slate-100 px-3 py-2 align-middle', className)}>{children}</td>;
}

/** دايرة الـ health: أخضر ≥70، أصفر 50–69، أحمر <50 */
export function Health({ score }: { score: number | null }) {
  if (score === null || score === undefined) return <span className="text-slate-400">—</span>;
  const cls = score >= 70 ? 'bg-emerald-500' : score >= 50 ? 'bg-amber-400' : 'bg-red-500';
  return (
    <span className="inline-flex items-center gap-1.5 tabular-nums">
      <span className={clsx('inline-block h-3 w-3 rounded-full', cls)} />
      {Math.round(score)}
    </span>
  );
}
