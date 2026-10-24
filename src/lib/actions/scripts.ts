'use server';

import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { fail, type ActionResult } from '@/lib/db';

type ScriptStatus = 'draft' | 'internal_review' | 'sent_to_client' | 'changes_requested' | 'approved';

export async function saveScript(id: string | null, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const title = String(fd.get('title') || '').trim();
  if (!title) return fail('اكتب عنوان السكربت');
  const body = String(fd.get('body') || '');
  if (id) {
    const { error } = await supabase.from('scripts').update({ title, body }).eq('id', id);
    if (error) return fail(error);
    return { ok: true, message: 'اتحفظ' };
  }
  const { data, error } = await supabase.from('scripts').insert({
    client_id: String(fd.get('client_id')), title, body, created_by: staff.id,
  }).select('id').single();
  if (error) return fail(error);
  return { ok: true, message: 'السكربت اتعمل', data: data.id };
}

export async function setScriptStatus(id: string, status: ScriptStatus, via?: string): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('scripts').update({ status, ...(via ? { approved_via: via } : {}) }).eq('id', id);
  if (error) return fail(error);
  return { ok: true, message: 'اتحدّث' };
}
