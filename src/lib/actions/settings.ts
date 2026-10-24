'use server';

import { headers } from 'next/headers';
import { createClient as createStatelessClient } from '@supabase/supabase-js';
import { createClient } from '@/lib/supabase/server';
import { fail, type ActionResult } from '@/lib/db';
import type { Json } from '@/types/database';

export async function updateSetting(key: string, fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const raw = String(fd.get('value') ?? '').trim();
  const value: Json = raw !== '' && !Number.isNaN(Number(raw)) ? Number(raw) : raw;
  const { error } = await supabase.from('settings').update({ value }).eq('key', key);
  if (error) return fail(error);
  return { ok: true, message: 'اتحفظ' };
}

/** دعوة: حساب جديد بلينك دخول على الإيميل + صف في staff */
export async function inviteStaff(fd: FormData): Promise<ActionResult> {
  const email = String(fd.get('email') || '').trim().toLowerCase();
  const name = String(fd.get('name') || '').trim();
  const roles = fd.getAll('roles').map(String);
  const phone = String(fd.get('phone_wa') || '').trim() || undefined;
  if (!email || !name || roles.length === 0) return fail('اكتب الإيميل والاسم واختار دور واحد على الأقل');

  const h = await headers();
  const origin = h.get('origin') ?? `https://${h.get('host')}`;
  // client منفصل من غير cookies علشان جلسة الأدمن متتأثرش
  const anon = createStatelessClient(process.env.NEXT_PUBLIC_SUPABASE_URL!, process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY!, {
    auth: { persistSession: false, autoRefreshToken: false, detectSessionInUrl: false, flowType: 'implicit' },
  });
  const { error: inv } = await anon.auth.signInWithOtp({ email, options: { shouldCreateUser: true, emailRedirectTo: `${origin}/auth/callback` } });
  if (inv) return fail(`الدعوة ماتبعتتش: ${inv.message}`);

  const supabase = await createClient();
  const { error } = await supabase.rpc('add_staff', { p_email: email, p_name: name, p_roles: roles, p_phone: phone });
  if (error) return fail(error);
  return { ok: true, message: `اتبعتت دعوة لـ ${email}` };
}

export async function addExistingStaff(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.rpc('add_staff', {
    p_email: String(fd.get('email') || ''),
    p_name: String(fd.get('name') || ''),
    p_roles: fd.getAll('roles').map(String),
    p_phone: String(fd.get('phone_wa') || '') || undefined,
  });
  if (error) return fail(error);
  return { ok: true, message: 'اتضاف للفريق' };
}

export async function updateStaff(id: string, fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const roles = fd.getAll('roles').map(String);
  if (roles.length === 0) return fail('اختار دور واحد على الأقل');
  const { error } = await supabase.from('staff').update({
    name: String(fd.get('name') || '').trim(),
    phone_wa: String(fd.get('phone_wa') || '').trim() || null,
    roles,
    active: fd.get('active') === 'on',
  }).eq('id', id);
  if (error) return fail(error);
  return { ok: true, message: 'اتحفظ' };
}

export async function setBonusStatus(id: string, status: 'approved' | 'rejected' | 'paid' | 'pending'): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('bonus_payouts').update({ status }).eq('id', id);
  if (error) return fail(error);
  return { ok: true, message: 'اتحدّث' };
}

export async function prepareBonuses(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const month = String(fd.get('month') || '');
  const { data, error } = await supabase.rpc('prepare_monthly_bonuses', { p_month: month ? `${month}-01` : undefined });
  if (error) return fail(error);
  return { ok: true, message: `اتحسب بونص ${data} موظف` };
}

export async function addStudio(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('studios').insert({ name: String(fd.get('name') || '').trim() });
  if (error) return fail(error);
  return { ok: true, message: 'اتضاف' };
}

export async function toggleStudio(id: string, active: boolean): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('studios').update({ active }).eq('id', id);
  if (error) return fail(error);
  return { ok: true };
}

export async function addMeter(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('utility_meters').insert({
    name: String(fd.get('name') || '').trim(), studio_id: String(fd.get('studio_id') || '') || null,
  });
  if (error) return fail(error);
  return { ok: true, message: 'اتضاف' };
}

export async function updateXpRule(code: string, fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('xp_rules').update({
    points: Number(fd.get('points')), active: fd.get('active') === 'on',
  }).eq('code', code);
  if (error) return fail(error);
  return { ok: true, message: 'اتحفظ' };
}

export async function addChallenge(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const roles = fd.getAll('roles').map(String);
  const { error } = await supabase.from('challenges').insert({
    name_ar: String(fd.get('name_ar') || '').trim(),
    description_ar: String(fd.get('description_ar') || '') || null,
    metric: String(fd.get('metric')) as 'xp',
    target: Number(fd.get('target')),
    reward_xp: Number(fd.get('reward_xp') || 0),
    roles: roles.length ? roles : null,
    ...(fd.get('starts_on') ? { starts_on: String(fd.get('starts_on')) } : {}),
    ...(fd.get('ends_on') ? { ends_on: String(fd.get('ends_on')) } : {}),
  });
  if (error) return fail(error);
  return { ok: true, message: 'التحدي اتضاف' };
}

export async function toggleChallenge(id: string, active: boolean): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('challenges').update({ active }).eq('id', id);
  if (error) return fail(error);
  return { ok: true };
}

export async function savePlan(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const items = JSON.parse(String(fd.get('items') || '[]')) as { type: string; qty: number }[];
  if (!items.length) return fail('ضيف خدمة واحدة على الأقل');
  const { data, error } = await supabase.from('plans').insert({
    name: String(fd.get('name') || '').trim(),
    base_price: Number(fd.get('base_price') || 0),
    validity_days: Number(fd.get('validity_days') || 30),
  }).select('id').single();
  if (error) return fail(error);
  const { error: e2 } = await supabase.from('plan_items').insert(items.map((i) => ({ plan_id: data.id, deliverable_type: i.type, quantity: i.qty })));
  if (e2) return fail(e2);
  return { ok: true, message: 'الباقة اتحفظت' };
}

export async function togglePlan(id: string, active: boolean): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('plans').update({ active }).eq('id', id);
  if (error) return fail(error);
  return { ok: true };
}

export async function updateChecklistItem(id: string, fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('checklist_template_items').update({
    text_ar: String(fd.get('text_ar') || '').trim(),
    sort: Number(fd.get('sort') || 0),
    is_required: fd.get('is_required') === 'on',
    client_facing: fd.get('client_facing') === 'on',
    role: String(fd.get('role') || '') || null,
  }).eq('id', id);
  if (error) return fail(error);
  return { ok: true, message: 'اتحفظ' };
}

export async function addChecklistItem(templateId: string, fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('checklist_template_items').insert({
    template_id: templateId,
    text_ar: String(fd.get('text_ar') || '').trim(),
    sort: Number(fd.get('sort') || 99),
    is_required: fd.get('is_required') === 'on',
    client_facing: fd.get('client_facing') === 'on',
    role: String(fd.get('role') || '') || null,
  });
  if (error) return fail(error);
  return { ok: true, message: 'اتضاف' };
}

export async function deleteChecklistItem(id: string): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('checklist_template_items').delete().eq('id', id);
  if (error) return fail(error.message.includes('foreign key') ? 'البند ده اتعلّم عليه قبل كده — شيل علامة "إجباري" بدل ما تمسحه' : error.message);
  return { ok: true, message: 'اتمسح' };
}
