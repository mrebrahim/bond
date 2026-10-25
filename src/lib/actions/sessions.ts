'use server';

import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { fail, type ActionResult } from '@/lib/db';
import { cairoToIso } from '@/lib/format';

export type BookingPreview = {
  unit: 'count' | 'hours' | null;
  cycle_id: string | null;
  cycle_status: string | null;
  cycle_end: string | null;
  available: number;
  remaining: number;
  reserved: number;
  enough: boolean;
  duration_minutes: number;
  outfits: number | null;
  locations: number | null;
  approved_scripts: number;
  scripts_short: boolean;
  covers_until: string | null;
};

export async function previewBooking(clientId: string, type: string, qty: number, startIso: string | null) {
  const supabase = await createClient();
  const { data, error } = await supabase.rpc('preview_booking', {
    p_client: clientId, p_type: type, p_qty: qty, p_start: startIso ?? undefined,
  });
  if (error) return null;
  return data as unknown as BookingPreview;
}

export async function clientBalances(clientId: string) {
  const supabase = await createClient();
  const { data } = await supabase
    .from('v_cycle_balance')
    .select('deliverable_type, name_ar, unit_kind, total, used, remaining, reserved, end_date, cycle_status')
    .eq('client_id', clientId)
    .in('unit_kind', ['count', 'hours']);
  return data ?? [];
}

export async function bookSession(fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const date = String(fd.get('date'));
  const hm = String(fd.get('time'));
  if (!date || !hm) return fail('اختار اليوم والساعة');
  const { data, error } = await supabase.from('studio_sessions').insert({
    client_id: String(fd.get('client_id')),
    deliverable_type: String(fd.get('deliverable_type') || 'reel'),
    studio_id: String(fd.get('studio_id')),
    photographer_id: String(fd.get('photographer_id') || '') || null,
    booked_start: cairoToIso(date, hm),
    planned_qty: Number(fd.get('planned_qty')),
    notes: String(fd.get('notes') || '') || null,
    created_by: staff.id,
  }).select('id').single();
  if (error) return fail(error);
  return { ok: true, message: 'الحجز اتسجل', data: data.id };
}

export async function rescheduleSession(sessionId: string, fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const date = String(fd.get('date'));
  const hm = String(fd.get('time'));
  const patch: { booked_start?: string; planned_qty?: number; studio_id?: string; photographer_id?: string | null; notes?: string | null } = {};
  if (date && hm) patch.booked_start = cairoToIso(date, hm);
  if (fd.get('planned_qty')) patch.planned_qty = Number(fd.get('planned_qty'));
  if (fd.get('studio_id')) patch.studio_id = String(fd.get('studio_id'));
  if (fd.has('photographer_id')) patch.photographer_id = String(fd.get('photographer_id') || '') || null;
  if (fd.has('notes')) patch.notes = String(fd.get('notes') || '') || null;
  const { error } = await supabase.from('studio_sessions').update(patch).eq('id', sessionId);
  if (error) return fail(error);
  return { ok: true, message: 'الجلسة اتعدلت' };
}

export async function cancelSession(sessionId: string, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const { data, error } = await supabase.rpc('cancel_session', {
    p_session: sessionId, p_reason: String(fd.get('reason') || '') || undefined, p_by: staff.id,
  });
  if (error) return fail(error);
  return { ok: true, message: data ? 'اتلغت — واتخصم ريل علشان إلغاء متأخر' : 'اتلغت من غير خصم' };
}

export async function checkIn(sessionId: string): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.rpc('check_in_session', { p_session: sessionId });
  if (error) return fail(error);
  return { ok: true, message: 'العميل وصل ✅' };
}

export async function noShow(sessionId: string): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const { data, error } = await supabase.rpc('mark_no_show', { p_session: sessionId, p_by: staff.id });
  if (error) return fail(error);
  return { ok: true, message: `اتسجل غياب — اتخصم ${data ?? 0}` };
}

export async function setConfirmation(sessionId: string, value: string): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.rpc('set_session_confirmation', { p_session: sessionId, p_confirmation: value });
  if (error) return fail(error);
  return { ok: true, message: 'اتحدّث' };
}

export async function completeSession(sessionId: string, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const date = String(fd.get('date') || '');
  const start = String(fd.get('actual_start') || '');
  const end = String(fd.get('actual_end') || '');
  const { data: qty, error } = await supabase.rpc('complete_session', {
    p_session: sessionId,
    p_actual_qty: fd.get('actual_qty') ? Number(fd.get('actual_qty')) : undefined,
    p_actual_start: date && start ? cairoToIso(date, start) : undefined,
    p_actual_end: date && end ? cairoToIso(date, end) : undefined,
    p_by: staff.id,
  });
  if (error) return fail(error);
  const { data: s } = await supabase.from('studio_sessions').select('cycle_id, deliverable_type').eq('id', sessionId).single();
  const { data: b } = s?.cycle_id
    ? await supabase.from('v_cycle_balance').select('remaining').eq('cycle_id', s.cycle_id).eq('deliverable_type', s.deliverable_type).maybeSingle()
    : { data: null };
  return { ok: true, message: `اتخصم ${qty} — باقي ${b?.remaining ?? '؟'}` };
}

/** علّم/شيل بند من المانوال */
export async function toggleChecklist(itemId: string, target: { session_id?: string; deliverable_id?: string; client_id?: string }, done: boolean): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  if (done) {
    const { error } = await supabase.from('checklist_runs').insert({ item_id: itemId, ...target, done_by: staff.id });
    if (error && !error.message.includes('duplicate')) return fail(error);
  } else {
    let q = supabase.from('checklist_runs').delete().eq('item_id', itemId);
    q = target.session_id ? q.eq('session_id', target.session_id)
      : target.client_id ? q.eq('client_id', target.client_id) : q.eq('deliverable_id', target.deliverable_id!);
    const { error } = await q;
    if (error) return fail(error);
  }
  return { ok: true };
}
