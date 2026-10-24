'use server';

import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { fail, type ActionResult } from '@/lib/db';

export async function moveStage(id: string, stage: string, extra?: { post_url?: string; platforms?: string[] }): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const { error } = await supabase.from('deliverables').update({
    stage,
    updated_by: staff.id,
    ...(extra?.post_url ? { post_url: extra.post_url } : {}),
    ...(extra?.platforms ? { platforms: extra.platforms } : {}),
  }).eq('id', id);
  if (error) return fail(error);
  return { ok: true, message: stage === 'published' ? 'اتنشر — رسالة التقييم هتروح للعميل' : 'اتنقل' };
}

export async function assignDeliverable(id: string, assigneeId: string | null): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const { error } = await supabase.from('deliverables').update({ assignee_id: assigneeId, updated_by: staff.id }).eq('id', id);
  if (error) return fail(error);
  return { ok: true, message: 'اتغيّر المسؤول' };
}

export async function renameDeliverable(id: string, fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('deliverables').update({ title: String(fd.get('title') || '') || null }).eq('id', id);
  if (error) return fail(error);
  return { ok: true, message: 'اتحفظ' };
}

/** تصميم جديد في الدورة النشطة للعميل */
export async function createDesign(fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const clientId = String(fd.get('client_id'));
  const type = String(fd.get('deliverable_type') || 'design');
  const { data: bal } = await supabase.from('v_cycle_balance').select('cycle_id')
    .eq('client_id', clientId).eq('deliverable_type', type).eq('cycle_status', 'active').maybeSingle();
  if (!bal?.cycle_id) return fail('العميل مالوش باقة نشطة فيها الخدمة دي');
  const { error } = await supabase.from('deliverables').insert({
    client_id: clientId,
    cycle_id: bal.cycle_id,
    deliverable_type: type,
    title: String(fd.get('title') || '') || null,
    assignee_id: String(fd.get('assignee_id') || '') || null,
    stage: type === 'design' ? 'brief' : 'filmed',
    updated_by: staff.id,
  });
  if (error) return fail(error);
  return { ok: true, message: 'اتضاف' };
}

/** بنود المانوال اللي بتقفل الانتقال للمرحلة دي (للـ drawer لما الداتابيز ترفض) */
export async function deliverableChecklist(id: string, toStage: string) {
  const { loadChecklist, gateStagesFor } = await import('@/lib/checklists');
  const supabase = await createClient();
  const { data: d } = await supabase.from('deliverables').select('deliverable_type').eq('id', id).single();
  if (!d) return [];
  return loadChecklist(supabase, gateStagesFor(toStage), d.deliverable_type, { deliverable_id: id });
}
