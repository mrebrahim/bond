import { getStaff } from '@/lib/auth';
import { navFor } from '@/lib/roles';
import { createClient } from '@/lib/supabase/server';
import { Sidebar } from '@/components/shell/sidebar';
import { AlertsListener } from '@/components/shell/alerts-listener';

export default async function AppLayout({ children }: { children: React.ReactNode }) {
  const staff = await getStaff();
  const supabase = await createClient();
  const { count } = await supabase
    .from('alerts')
    .select('id', { count: 'exact', head: true })
    .is('handled_at', null)
    .neq('audience', 'client')
    .gte('created_at', new Date(Date.now() - 14 * 864e5).toISOString());

  return (
    <div className="min-h-screen lg:flex">
      <Sidebar items={navFor(staff.roles)} name={staff.name} roles={staff.roles} inboxCount={count ?? 0} />
      <main className="min-w-0 flex-1 px-4 pb-24 pt-4 sm:px-6 lg:pb-8 lg:pt-6">{children}</main>
      <AlertsListener />
    </div>
  );
}
