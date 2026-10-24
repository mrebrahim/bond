import Link from 'next/link';
import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { INVOICE_KIND, INVOICE_STATUS } from '@/lib/labels';
import { addDays, cairoToday, day, money } from '@/lib/format';
import { Badge, Empty, LinkButton, PageHeader, Stat, Table, Td, Th } from '@/components/ui';
import { PaymentButton } from '../clients/[id]/client-actions';

export default async function FinancePage({ searchParams }: { searchParams: Promise<{ filter?: string; page?: string }> }) {
  const sp = await searchParams;
  const filter = sp.filter ?? 'unpaid';
  const page = Math.max(1, Number(sp.page || 1));
  const staff = await getStaff();
  const supabase = await createClient();
  const { data: overdueSetting } = await supabase.from('settings').select('value').eq('key', 'payment_overdue_days').maybeSingle();
  const overdueBefore = addDays(cairoToday(), -Number(overdueSetting?.value ?? 3));

  let q = supabase.from('invoices').select('*, clients(id, name, code)', { count: 'exact' });
  if (filter === 'unpaid') q = q.in('status', ['unpaid', 'partial']);
  if (filter === 'overdue') q = q.in('status', ['unpaid', 'partial']).lt('due_date', overdueBefore);
  const [{ data: invoices, count }, { data: kpi }] = await Promise.all([
    q.order('due_date', { ascending: filter !== 'all' }).range((page - 1) * 50, page * 50 - 1),
    supabase.from('v_dashboard_kpis').select('collected_this_month, outstanding_amount, overdue_amount').maybeSingle(),
  ]);
  const canPay = hasRole(staff.roles, CAN.payment);

  return (
    <div>
      <PageHeader title="الفلوس" />
      <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Stat label="المحصّل الشهر ده" value={money(kpi?.collected_this_month)} tone="green" />
        <Stat label="مستحق (مش مدفوع)" value={money(kpi?.outstanding_amount)} href="/finance?filter=unpaid" />
        <Stat label="متأخر" value={money(kpi?.overdue_amount)} tone="red" href="/finance?filter=overdue" />
      </div>
      <div className="mb-3 flex gap-2">
        {[['unpaid', 'غير مدفوعة'], ['overdue', 'متأخرة'], ['all', 'الكل']].map(([k, v]) => (
          <LinkButton key={k} size="sm" variant={filter === k ? 'primary' : 'secondary'} href={`/finance?filter=${k}`}>{v}</LinkButton>
        ))}
      </div>
      {!invoices?.length ? <Empty icon="💰">مفيش فواتير هنا</Empty> : (
        <Table>
          <thead><tr><Th>العميل</Th><Th>الوصف</Th><Th>المبلغ</Th><Th>المدفوع</Th><Th>الاستحقاق</Th><Th>الحالة</Th><Th /></tr></thead>
          <tbody>
            {invoices.map((i) => (
              <tr key={i.id}>
                <Td><Link href={`/clients/${i.clients?.id}?tab=money`} className="font-medium text-brand-700 hover:underline">{i.clients?.name}</Link></Td>
                <Td>{i.description}<div className="text-xs text-slate-400">{INVOICE_KIND[i.kind]}</div></Td>
                <Td className="tabular-nums">{money(i.amount)}</Td>
                <Td className="tabular-nums">{money(i.paid_amount)}</Td>
                <Td className={i.status !== 'paid' && i.status !== 'void' && i.due_date < overdueBefore ? 'font-semibold text-red-600' : ''}>{day(i.due_date)}</Td>
                <Td><Badge className={INVOICE_STATUS[i.status].cls}>{INVOICE_STATUS[i.status].label}</Badge></Td>
                <Td>{canPay && (i.status === 'unpaid' || i.status === 'partial') && (
                  <PaymentButton invoices={[{ id: i.id, description: i.description, amount: Number(i.amount), paid_amount: Number(i.paid_amount) }]} invoiceId={i.id} label="دفع" />
                )}</Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
      {(count ?? 0) > 50 && (
        <div className="mt-4 flex justify-center gap-2">
          {page > 1 && <LinkButton size="sm" href={`/finance?filter=${filter}&page=${page - 1}`}>السابق</LinkButton>}
          {page * 50 < (count ?? 0) && <LinkButton size="sm" href={`/finance?filter=${filter}&page=${page + 1}`}>التالي</LinkButton>}
        </div>
      )}
    </div>
  );
}
