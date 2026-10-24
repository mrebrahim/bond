import { createClient } from '@/lib/supabase/server';
import { requirePageRole } from '@/lib/auth';
import { money, num, pct } from '@/lib/format';
import { Card, CardTitle, Empty, PageHeader, Table, Td, Th } from '@/components/ui';
import { MrrChart, UtilizationChart } from './charts';

const TYPE: Record<string, string> = { reel: 'ريلز', design: 'تصميم', trainer_hours: 'ساعات', trainer_video_edit: 'فيديو مدرب' };

function Export({ view }: { view: string }) {
  return <a href={`/reports/export?view=${view}`} className="text-xs text-brand-700 underline">CSV ⬇</a>;
}

export default async function ReportsPage() {
  await requirePageRole([]);
  const supabase = await createClient();
  const [{ data: mrr }, { data: ret }, { data: util }, { data: fb }, { data: rev }] = await Promise.all([
    supabase.from('v_mrr_monthly').select('*').order('month'),
    supabase.from('v_retention_monthly').select('*').order('month', { ascending: false }),
    supabase.from('v_utilization_monthly').select('*').order('month'),
    supabase.from('v_feedback_by_staff').select('*').order('month', { ascending: false }),
    supabase.from('v_client_overview').select('client_id, name, total_paid, outstanding').gt('total_paid', 0).order('total_paid', { ascending: false }).limit(30),
  ]);

  return (
    <div>
      <PageHeader title="التقارير" />
      <div className="grid gap-4 xl:grid-cols-2">
        <Card>
          <CardTitle action={<Export view="v_mrr_monthly" />}>MRR آخر 12 شهر (لقطة أول كل شهر)</CardTitle>
          <MrrChart data={(mrr ?? []).map((m) => ({ month: (m.month ?? '').slice(0, 7), mrr: Number(m.mrr), clients: Number(m.active_clients) }))} />
        </Card>
        <Card>
          <CardTitle action={<Export view="v_utilization_monthly" />}>الاستهلاك والضايع</CardTitle>
          {!util?.length ? <Empty>لسه مفيش دورات خلصت</Empty> : (
            <UtilizationChart data={util.map((u) => ({ label: `${(u.month ?? '').slice(0, 7)} ${TYPE[u.deliverable_type ?? ''] ?? u.deliverable_type}`, used: Number(u.used), forfeited: Number(u.forfeited) }))} />
          )}
        </Card>
        <Card>
          <CardTitle action={<Export view="v_retention_monthly" />}>Churn و NRR و GRR</CardTitle>
          {!ret?.length ? <Empty>لسه مفيش بيانات</Empty> : (
            <Table>
              <thead><tr><Th>الشهر</Th><Th>عملاء أول الشهر</Th><Th>مشيوا</Th><Th>Logo churn</Th><Th>GRR</Th><Th>NRR</Th></tr></thead>
              <tbody>
                {ret.map((r) => (
                  <tr key={r.month}><Td>{r.month?.slice(0, 7)}</Td><Td>{num(r.clients_start)}</Td><Td>{num(r.churned_clients)}</Td>
                    <Td className={Number(r.logo_churn_pct) >= 8 ? 'font-bold text-red-600' : ''}>{pct(r.logo_churn_pct)}</Td><Td>{pct(r.grr_pct)}</Td><Td>{pct(r.nrr_pct)}</Td></tr>
                ))}
              </tbody>
            </Table>
          )}
        </Card>
        <Card>
          <CardTitle action={<Export view="v_feedback_by_staff" />}>تقييم المصورين والمونتيرين</CardTitle>
          {!fb?.length ? <Empty>لسه مفيش تقييمات</Empty> : (
            <Table>
              <thead><tr><Th>الشهر</Th><Th>الاسم</Th><Th>تصوير</Th><Th>مونتاج</Th><Th>الردود</Th></tr></thead>
              <tbody>
                {fb.map((f) => (
                  <tr key={`${f.month}-${f.staff_id}`}><Td>{f.month?.slice(0, 7)}</Td><Td>{f.name}</Td><Td>{num(f.avg_filming)}</Td><Td>{num(f.avg_editing)}</Td><Td>{num(f.responses)}</Td></tr>
                ))}
              </tbody>
            </Table>
          )}
        </Card>
        <Card className="xl:col-span-2">
          <CardTitle action={<Export view="v_client_overview" />}>الإيراد لكل عميل</CardTitle>
          {!rev?.length ? <Empty>لسه مفيش مدفوعات</Empty> : (
            <Table>
              <thead><tr><Th>العميل</Th><Th>دفع</Th><Th>عليه</Th></tr></thead>
              <tbody>{rev.map((r) => <tr key={r.client_id}><Td>{r.name}</Td><Td className="tabular-nums">{money(r.total_paid)}</Td><Td className="tabular-nums">{money(r.outstanding)}</Td></tr>)}</tbody>
            </Table>
          )}
        </Card>
      </div>
    </div>
  );
}
