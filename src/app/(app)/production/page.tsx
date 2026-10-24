import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { PageHeader } from '@/components/ui';
import { Board, type Card } from './board';

export default async function ProductionPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const sp = await searchParams;
  const staff = await getStaff();
  const supabase = await createClient();
  const since = new Date(Date.now() - 14 * 864e5).toISOString();

  const [{ data: types }, { data: rows }, { data: team }, { data: activeClients }] = await Promise.all([
    supabase.from('deliverable_types').select('code, name_ar, stages').order('sort'),
    supabase.from('deliverables')
      .select('id, title, stage, deliverable_type, client_id, assignee_id, revision_count, stage_changed_at, post_url, clients(name), staff!deliverables_assignee_id_fkey(name)')
      .or(`stage.not.in.(published,delivered),stage_changed_at.gte.${since}`)
      .order('stage_changed_at', { ascending: true })
      .limit(500),
    supabase.from('staff').select('id, name, roles').eq('active', true).order('name'),
    supabase.from('clients').select('id, name').eq('status', 'active').order('name'),
  ]);

  const cards: Card[] = (rows ?? []).map((r) => ({
    id: r.id, title: r.title, stage: r.stage, type: r.deliverable_type, client_id: r.client_id,
    client: r.clients?.name ?? '', assignee_id: r.assignee_id, assignee: r.staff?.name ?? null,
    revisions: r.revision_count, since: r.stage_changed_at, post_url: r.post_url,
  }));
  const isLead = staff.roles.includes('media_lead') || staff.roles.includes('admin');

  return (
    <div>
      <PageHeader title="لوحة الإنتاج" subtitle="حرّك الكارت بين المراحل — المانوال الإجباري بيقفل الانتقال" />
      <Board
        types={(types ?? []).filter((t) => t.stages.length > 0).map((t) => ({ code: t.code, name: t.name_ar, stages: t.stages }))}
        cards={cards}
        team={(team ?? []).filter((t) => t.roles.some((r) => ['editor', 'designer', 'studio', 'media_lead'].includes(r))).map((t) => ({ id: t.id, name: t.name }))}
        me={staff.id}
        initialMine={sp.mine === '1' && !isLead}
        canMove={hasRole(staff.roles, CAN.stage)}
        clients={activeClients ?? []}
      />
    </div>
  );
}
