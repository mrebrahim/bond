'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useState } from 'react';
import clsx from 'clsx';
import type { NavItem } from '@/lib/roles';
import { ROLE_LABELS, type Role } from '@/lib/roles';
import { signOut } from '@/app/(auth)/login/actions';

export function Sidebar({ items, name, roles, inboxCount }: {
  items: NavItem[]; name: string; roles: string[]; inboxCount: number;
}) {
  const path = usePathname();
  const [open, setOpen] = useState(false);
  const isActive = (href: string) => path === href || path.startsWith(`${href}/`);

  const links = (
    <nav className="flex flex-col gap-0.5">
      {items.map((it) => (
        <Link
          key={it.href}
          href={it.href}
          onClick={() => setOpen(false)}
          className={clsx('flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium',
            isActive(it.href) ? 'bg-brand-50 text-brand-700' : 'text-slate-700 hover:bg-slate-100')}
        >
          <span className="w-5 text-center">{it.icon}</span>
          <span className="flex-1">{it.label}</span>
          {it.href === '/inbox' && inboxCount > 0 && (
            <span className="rounded-full bg-red-600 px-2 py-0.5 text-xs text-white tabular-nums">{inboxCount}</span>
          )}
        </Link>
      ))}
    </nav>
  );

  const footer = (
    <div className="mt-auto border-t border-slate-200 pt-3">
      <div className="px-3 text-sm font-semibold">{name}</div>
      <div className="px-3 text-xs text-slate-500">{roles.map((r) => ROLE_LABELS[r as Role] ?? r).join(' · ')}</div>
      <form action={signOut}>
        <button className="mt-2 min-h-9 px-3 text-sm text-slate-500 hover:text-red-600">خروج</button>
      </form>
    </div>
  );

  return (
    <>
      {/* موبايل: هيدر + منيو */}
      <header className="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white/95 px-4 py-2 backdrop-blur lg:hidden">
        <button type="button" onClick={() => setOpen(true)} className="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-xl hover:bg-slate-100" aria-label="المنيو">☰</button>
        <span className="font-bold">Digital Solution</span>
        <Link href="/inbox" className="relative flex min-h-11 min-w-11 items-center justify-center text-xl" aria-label="التنبيهات">
          🔔
          {inboxCount > 0 && <span className="absolute end-1 top-1 rounded-full bg-red-600 px-1.5 text-[10px] text-white">{inboxCount}</span>}
        </Link>
      </header>
      {open && (
        <div className="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" onClick={() => setOpen(false)}>
          <aside className="flex h-full w-72 flex-col gap-3 bg-white p-3" onClick={(e) => e.stopPropagation()}>
            <div className="px-3 py-2 text-lg font-bold">Digital Solution</div>
            {links}
            {footer}
          </aside>
        </div>
      )}
      {/* ديسكتوب */}
      <aside className="sticky top-0 hidden h-screen w-60 shrink-0 flex-col gap-3 border-e border-slate-200 bg-white p-3 lg:flex">
        <div className="flex items-center gap-2 px-3 py-2">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">◎</span>
          <span className="font-bold">Digital Solution</span>
        </div>
        <div className="flex-1 overflow-y-auto">{links}</div>
        {footer}
      </aside>
    </>
  );
}
