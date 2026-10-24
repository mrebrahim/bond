import type { createClient } from '@/lib/supabase/server';
import type { ChecklistGroup } from '@/components/checklist/checklist';

type Supa = Awaited<ReturnType<typeof createClient>>;

/** بنود المانوال لمراحل معينة + اللي اتعلّم منها */
export async function loadChecklist(
  supabase: Supa, stages: string[], type: string, target: { session_id?: string; deliverable_id?: string },
): Promise<ChecklistGroup[]> {
  const [{ data: templates }, { data: runs }] = await Promise.all([
    supabase.from('checklist_templates')
      .select('id, name, stage, applies_to, checklist_template_items(id, text_ar, is_required, client_facing, sort)')
      .eq('active', true).in('stage', stages),
    target.session_id
      ? supabase.from('checklist_runs').select('item_id, done').eq('session_id', target.session_id)
      : supabase.from('checklist_runs').select('item_id, done').eq('deliverable_id', target.deliverable_id!),
  ]);
  const done = new Set((runs ?? []).filter((r) => r.done).map((r) => r.item_id));
  return (templates ?? [])
    .filter((t) => !t.applies_to || t.applies_to.includes(type))
    .sort((a, b) => stages.indexOf(a.stage) - stages.indexOf(b.stage))
    .map((t) => ({
      id: t.id,
      name: t.name,
      items: [...t.checklist_template_items].sort((a, b) => a.sort - b.sort)
        .map((i) => ({ id: i.id, text_ar: i.text_ar, is_required: i.is_required, client_facing: i.client_facing, done: done.has(i.id) })),
    }));
}

/** المرحلة اللي بيتشيك عليها قبل ما الـ deliverable يتنقل (نفس trg_deliverable_before) */
export function gateStagesFor(toStage: string): string[] {
  if (toStage === 'approved') return ['editing', 'design'];
  if (toStage === 'delivered') return ['editing'];
  if (toStage === 'published') return ['publishing'];
  return [];
}
