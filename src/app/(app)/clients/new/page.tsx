import { createClient } from '@/lib/supabase/server';
import { requirePageRole } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { cairoToday } from '@/lib/format';
import { PageHeader } from '@/components/ui';
import { NewClientWizard } from './wizard';

export default async function NewClientPage() {
  const staff = await requirePageRole(CAN.editClient);
  const supabase = await createClient();
  const [{ data: ams }, { data: types }, { data: plans }] = await Promise.all([
    supabase.from('staff').select('id, name').contains('roles', ['account_manager']).eq('active', true).order('created_at'),
    supabase.from('deliverable_types').select('code, name_ar, unit_kind').order('sort'),
    supabase.from('plans').select('id, name, base_price, validity_days, plan_items(deliverable_type, quantity)').eq('active', true).order('base_price'),
  ]);
  return (
    <div className="mx-auto max-w-3xl">
      <PageHeader title="عميل جديد" />
      <NewClientWizard ams={ams ?? []} types={types ?? []} plans={plans ?? []} today={cairoToday()} canPay={hasRole(staff.roles, CAN.payment)} />
    </div>
  );
}
