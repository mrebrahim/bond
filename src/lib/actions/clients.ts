'use server';

import { z } from 'zod';
import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { fail, type ActionResult } from '@/lib/db';

const itemsSchema = z.array(z.object({ type: z.string().min(1), qty: z.coerce.number().positive('الكمية لازم تبقى أكبر من صفر') }))
  .min(1, 'ضيف خدمة واحدة على الأقل');

const scheduleSchema = z.array(z.object({
  deliverable_type: z.enum(['reel', 'design']),
  weekday: z.number().int().min(0).max(6),
  publish_time: z.string().nullable(),
  platforms: z.array(z.string()),
}));

const wizardSchema = z.object({
  client: z.object({
    name: z.string().trim().min(1, 'اكتب اسم العميل'),
    business_name: z.string().trim().optional().nullable(),
    phone_wa: z.string().trim().regex(/^20\d{10}$/, 'رقم الواتساب لازم يبقى 20 + 10 أرقام'),
    contact_person: z.string().trim().optional().nullable(),
    industry: z.string().trim().optional().nullable(),
    account_manager_id: z.string().uuid().optional().nullable(),
    code: z.string().trim().optional().nullable(),
    platforms: z.array(z.string()),
    shoot_frequency: z.enum(['weekly', 'biweekly', 'monthly', 'on_demand']).nullable(),
    preferred_shoot_day: z.number().int().min(0).max(6).nullable(),
    reels_per_session_avg: z.number().int().positive().nullable(),
    needs_approval: z.boolean(),
    approval_sla_hours: z.number().int().positive(),
    caption_tone: z.string().trim().optional().nullable(),
    brand_assets_url: z.string().trim().optional().nullable(),
    buffer_weeks: z.number().positive(),
  }),
  schedule: scheduleSchema,
  subscription: z.object({
    plan_id: z.string().uuid().nullable(),
    name: z.string().trim().min(1, 'اكتب اسم الباقة'),
    price: z.number().nonnegative(),
    validity_days: z.number().int().positive(),
    start: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    overage_rate: z.number().positive().nullable(),
    items: itemsSchema,
  }).nullable(),
});
export type WizardPayload = z.infer<typeof wizardSchema>;

export async function createClientWizard(payload: WizardPayload): Promise<ActionResult> {
  const parsed = wizardSchema.safeParse(payload);
  if (!parsed.success) return fail(parsed.error.issues[0].message);
  const { client, schedule, subscription } = parsed.data;
  const staff = await getStaff();
  const supabase = await createClient();

  const { data: row, error } = await supabase
    .from('clients')
    .insert({ ...client, code: client.code || null, account_manager_id: client.account_manager_id || null })
    .select('id')
    .single();
  if (error) return fail(error);

  if (schedule.length) {
    const { error: e2 } = await supabase.from('publishing_schedule').insert(schedule.map((s) => ({ ...s, client_id: row.id })));
    if (e2) return fail(e2);
  }

  if (subscription) {
    const { error: e3 } = await supabase.rpc('create_subscription', {
      p_client: row.id,
      p_name: subscription.name,
      p_price: subscription.price,
      p_items: subscription.items,
      p_start: subscription.start,
      p_validity_days: subscription.validity_days,
      p_overage_rate: subscription.overage_rate ?? undefined,
      p_plan: subscription.plan_id ?? undefined,
      p_by: staff.id,
    });
    if (e3) return { ok: false, error: `العميل اتضاف بس الاشتراك ماتعملش: ${e3.message}` };
  }
  return { ok: true, message: 'العميل اتضاف', data: row.id };
}

export async function checkScheduleDiff(perWeek: Record<string, number>, items: { type: string; qty: number }[], days: number) {
  const supabase = await createClient();
  const { data } = await supabase.rpc('schedule_package_diff', { p_per_week: perWeek, p_items: items, p_days: days });
  return (data ?? []) as { type: string; name: string; per_week: number; package_qty: number; needed: number; diff: number }[];
}

export async function newSubscription(clientId: string, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const items = itemsSchema.safeParse(JSON.parse(String(fd.get('items') || '[]')));
  if (!items.success) return fail(items.error.issues[0].message);
  const supabase = await createClient();
  const { error } = await supabase.rpc('create_subscription', {
    p_client: clientId,
    p_name: String(fd.get('name') || 'باقة'),
    p_price: Number(fd.get('price') || 0),
    p_items: items.data,
    p_start: String(fd.get('start') || '') || undefined,
    p_validity_days: Number(fd.get('validity_days') || 30),
    p_overage_rate: fd.get('overage_rate') ? Number(fd.get('overage_rate')) : undefined,
    p_by: staff.id,
  });
  if (error) return fail(error);
  return { ok: true, message: 'الاشتراك اتعمل — الفاتورة طلعت ومستنية الدفع' };
}

export async function updateClient(clientId: string, fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const str = (k: string) => (fd.get(k) === null ? undefined : String(fd.get(k)).trim() || null);
  const { error } = await supabase.from('clients').update({
    name: String(fd.get('name') || '').trim(),
    business_name: str('business_name'),
    phone_wa: String(fd.get('phone_wa') || '').trim(),
    contact_person: str('contact_person'),
    industry: str('industry'),
    account_manager_id: str('account_manager_id'),
    platforms: fd.getAll('platforms').map(String),
    shoot_frequency: (str('shoot_frequency') as 'weekly' | null) ?? null,
    preferred_shoot_day: fd.get('preferred_shoot_day') ? Number(fd.get('preferred_shoot_day')) : null,
    reels_per_session_avg: fd.get('reels_per_session_avg') ? Number(fd.get('reels_per_session_avg')) : null,
    needs_approval: fd.get('needs_approval') === 'on',
    approval_sla_hours: Number(fd.get('approval_sla_hours') || 48),
    caption_tone: str('caption_tone'),
    brand_assets_url: str('brand_assets_url'),
    buffer_weeks: Number(fd.get('buffer_weeks') || 1),
    notes: str('notes'),
  }).eq('id', clientId);
  if (error) return fail(error);
  return { ok: true, message: 'اتحفظ' };
}

export async function saveSchedule(clientId: string, rows: z.infer<typeof scheduleSchema>): Promise<ActionResult> {
  const parsed = scheduleSchema.safeParse(rows);
  if (!parsed.success) return fail(parsed.error.issues[0].message);
  const supabase = await createClient();
  const { error } = await supabase.from('publishing_schedule').delete().eq('client_id', clientId);
  if (error) return fail(error);
  if (parsed.data.length) {
    const { error: e2 } = await supabase.from('publishing_schedule').insert(parsed.data.map((r) => ({ ...r, client_id: clientId })));
    if (e2) return fail(e2);
  }
  return { ok: true, message: 'جدول النشر اتحفظ' };
}

export async function addTopup(cycleId: string, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const { error } = await supabase.rpc('add_topup', {
    p_cycle: cycleId,
    p_type: String(fd.get('type')),
    p_qty: Number(fd.get('qty')),
    p_price: Number(fd.get('price') || 0),
    p_by: staff.id,
  });
  if (error) return fail(error);
  return { ok: true, message: 'الرصيد زاد والفاتورة طلعت' };
}

export async function changeSubscription(subId: string, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const rawItems = String(fd.get('items') || '');
  let items: { type: string; qty: number }[] | undefined;
  if (rawItems) {
    const p = itemsSchema.safeParse(JSON.parse(rawItems));
    if (!p.success) return fail(p.error.issues[0].message);
    items = p.data;
  }
  const supabase = await createClient();
  const { error } = await supabase.rpc('change_subscription', {
    p_sub: subId,
    p_event: String(fd.get('event')),
    p_new_price: Number(fd.get('price')),
    p_new_items: items,
    p_new_validity: fd.get('validity_days') ? Number(fd.get('validity_days')) : undefined,
    p_note: String(fd.get('note') || '') || undefined,
    p_by: staff.id,
  });
  if (error) return fail(error);
  return { ok: true, message: 'اتسجل — هيبدأ من الدورة الجاية' };
}

export async function cancelSubscription(subId: string, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const { error } = await supabase.rpc('cancel_subscription', {
    p_sub: subId, p_reason: String(fd.get('reason') || '') || undefined, p_by: staff.id,
  });
  if (error) return fail(error);
  return { ok: true, message: 'الاشتراك مش هيتجدد' };
}

export async function recordPayment(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const amount = Number(fd.get('amount'));
  if (!(amount > 0)) return fail('اكتب المبلغ');
  const { error } = await supabase.from('payments').insert({
    invoice_id: String(fd.get('invoice_id')),
    amount,
    method: String(fd.get('method')) as 'cash',
    reference: String(fd.get('reference') || '') || null,
  });
  if (error) return fail(error);
  return { ok: true, message: 'الدفع اتسجل' };
}
