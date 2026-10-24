import Link from 'next/link';
import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { CLIENT_STATUS } from '@/lib/labels';
import type { BalanceItem } from '@/lib/db';
import { money, qty } from '@/lib/format';
import { Badge, Empty, Health, Input, LinkButton, PageHeader, Select, Table, Td, Th, cn } from '@/components/ui';

const PAGE = 50;

export default async function ClientsPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const sp = await searchParams;
  const staff = await getStaff();
  const supabase = await createClient();
  const page = Math.max(1, Number(sp.page || 1));

  let q = supabase.from('v_client_overview').select('*', { count: 'exact' });
  if (sp.status) q = q.eq('status', sp.status); else q = q.neq('status', 'churned');
  if (sp.am) q = q.eq('account_manager', sp.am);
  if (sp.health === 'low') q = q.lt('health_score', 50);
  if (sp.health === 'mid') q = q.gte('health_score', 50).lt('health_score', 70);
  if (sp.expiring) q = q.lte('days_left', Number(sp.expiring)).gte('days_left', 0);
  if (sp.owing) q = q.gt('outstanding', 0);
  if (sp.q) {
    const term = sp.q.trim();
    if (/^\d+$/.test(term)) {
      const { data: ids } = await supabase.from('clients').select('id').ilike('phone_wa', `%${term}%`);
      q = q.in('client_id', (ids ?? []).map((r) => r.id));
    } else {
      q = q.or(`name.ilike.%${term}%,code.ilike.%${term}%`);
    }
  }
  const { data: rows, count } = await q.order('days_left', { ascending: true, nullsFirst: false }).range((page - 1) * PAGE, page * PAGE - 1);
  const { data: ams } = await supabase.from('staff').select('name').contains('roles', ['account_manager']).eq('active', true);

  const qs = (patch: Record<string, string | undefined>) => {
    const p = new URLSearchParams(Object.entries({ ...sp, ...patch }).filter(([, v]) => v) as [string, string][]);
    return `/clients?${p.toString()}`;
  };

  return (
    <div>
      <PageHeader
        title="العملاء"
        subtitle={`${count ?? 0} عميل`}
        actions={hasRole(staff.roles, CAN.editClient) && <LinkButton href="/clients/new" variant="primary">+ عميل جديد</LinkButton>}
      />
      <form className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" action="/clients">
        <Input name="q" defaultValue={sp.q} placeholder="بحث بالاسم أو الكود أو الرقم" className="col-span-2" />
        <Select name="status" defaultValue={sp.status ?? ''}>
          <option value="">الحالة: الكل (غير اللي مشيوا)</option>
          {Object.entries(CLIENT_STATUS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </Select>
        <Select name="am" defaultValue={sp.am ?? ''}>
          <option value="">الـ AM: الكل</option>
          {(ams ?? []).map((a) => <option key={a.name}>{a.name}</option>)}
        </Select>
        <Select name="health" defaultValue={sp.health ?? ''}>
          <option value="">Health: الكل</option>
          <option value="low">🔴 أقل من 50</option>
          <option value="mid">🟡 50–69</option>
        </Select>
        <Select name="expiring" defaultValue={sp.expiring ?? ''}>
          <option value="">بتخلص: أي وقت</option>
          <option value="3">خلال 3 أيام</option>
          <option value="7">خلال 7 أيام</option>
          <option value="14">خلال 14 يوم</option>
        </Select>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" name="owing" value="1" defaultChecked={!!sp.owing} className="h-4 w-4" /> عليه فلوس
        </label>
        <button className="min-h-11 rounded-lg bg-slate-800 px-4 text-sm text-white">فلتر</button>
      </form>

      {!rows?.length ? (
        <Empty icon="👥">مفيش عملاء بالفلتر ده</Empty>
      ) : (
        <Table>
          <thead>
            <tr><Th>العميل</Th><Th>الـ AM</Th><Th>أيام باقية</Th><Th>الرصيد</Th><Th>عليه</Th><Th>Health</Th></tr>
          </thead>
          <tbody>
            {rows.map((r) => {
              const bal = ((r.balances ?? []) as BalanceItem[]).filter((b) => b.unit !== 'flat');
              return (
                <tr key={r.client_id} className="hover:bg-slate-50">
                  <Td>
                    <Link href={`/clients/${r.client_id}`} className="font-medium text-brand-700 hover:underline">{r.name}</Link>
                    <div className="text-xs text-slate-400">{r.code} · {CLIENT_STATUS[r.status ?? ''] ?? r.status}</div>
                  </Td>
                  <Td className="text-slate-600">{r.account_manager ?? '—'}</Td>
                  <Td>
                    {r.days_left === null ? <span className="text-slate-400">مفيش باقة نشطة</span> : (
                      <span className={cn('tabular-nums', r.days_left <= 3 && 'font-bold text-red-600')}>{r.days_left} يوم</span>
                    )}
                  </Td>
                  <Td className="text-xs">
                    {bal.length ? bal.map((b) => `${b.name} ${qty(b.remaining, b.unit)}/${qty(b.total, b.unit)}`).join(' · ') : '—'}
                  </Td>
                  <Td>
                    {Number(r.outstanding) > 0
                      ? <Badge className={Number(r.overdue_invoices) > 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'}>{money(r.outstanding)}</Badge>
                      : <span className="text-slate-400">—</span>}
                  </Td>
                  <Td><Health score={r.health_score} /></Td>
                </tr>
              );
            })}
          </tbody>
        </Table>
      )}
      {(count ?? 0) > PAGE && (
        <div className="mt-4 flex justify-center gap-2">
          {page > 1 && <LinkButton href={qs({ page: String(page - 1) })} size="sm">السابق</LinkButton>}
          <span className="px-3 py-2 text-sm text-slate-500">صفحة {page} من {Math.ceil((count ?? 0) / PAGE)}</span>
          {page * PAGE < (count ?? 0) && <LinkButton href={qs({ page: String(page + 1) })} size="sm">التالي</LinkButton>}
        </div>
      )}
    </div>
  );
}
