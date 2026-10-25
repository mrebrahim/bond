'use server';

import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { fail, type ActionResult } from '@/lib/db';

export type ClientMessage = 'welcome' | 'client_rules' | 'booking_request' | 'outfits_reminder' | 'contract_request' | 'access_request';

/** رسالة من مانوال العميل الجديد — بتروح جروب العميل (أو الخاص لو مفيش جروب) */
export async function sendClientMessage(clientId: string, key: ClientMessage): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.rpc('send_client_message', { p_client: clientId, p_key: key });
  if (error) return fail(error);
  const { data: enabled } = await supabase.from('settings').select('value').eq('key', 'outbox_enabled').maybeSingle();
  return {
    ok: true,
    message: String(enabled?.value) === 'true' ? 'الرسالة هتتبعت خلال دقيقة ✅' : 'الرسالة اتحطت في الطابور — إرسال الواتساب متقفل حاليًا (outbox_enabled)',
  };
}

/** بينادي Edge Function wa-group بـ JWT الموظف */
async function waGroup(body: Record<string, unknown>): Promise<ActionResult> {
  const supabase = await createClient();
  const { data, error } = await supabase.functions.invoke('wa-group', { body });
  if (error) {
    const ctx = (error as { context?: Response }).context;
    const msg = ctx ? await ctx.json().then((j: { error?: string }) => j.error).catch(() => null) : null;
    return fail(msg ?? error.message);
  }
  return { ok: true, message: (data as { message?: string })?.message, data };
}

export async function createWhatsappGroup(clientId: string): Promise<ActionResult> {
  return waGroup({ action: 'create', client_id: clientId });
}

export async function linkWhatsappGroup(clientId: string, fd: FormData): Promise<ActionResult> {
  return waGroup({ action: 'link', client_id: clientId, link: String(fd.get('link') ?? '') });
}

export async function whatsappStatus(): Promise<ActionResult> {
  return waGroup({ action: 'status' });
}

export async function whatsappSetupWebhook(): Promise<ActionResult> {
  return waGroup({ action: 'setup' });
}

/** صورة التعاقد → Storage (contracts) + علامة في المانوال */
export async function uploadContract(clientId: string, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const file = fd.get('file');
  if (!(file instanceof File) || file.size === 0) return fail('اختار صورة أو PDF للعقد');
  if (file.size > 10 * 1024 * 1024) return fail('الملف أكبر من 10 ميجا');
  const ext = file.name.split('.').pop()?.toLowerCase() || 'jpg';
  const path = `${clientId}/${Date.now()}.${ext}`;
  const { error: up } = await supabase.storage.from('contracts').upload(path, file, { contentType: file.type });
  if (up) return fail(`الملف مارفعش: ${up.message}`);
  const { error } = await supabase.from('clients').update({ contract_path: path }).eq('id', clientId);
  if (error) return fail(error);
  const { data: item } = await supabase.from('checklist_template_items').select('id').eq('action', 'upload_contract').maybeSingle();
  if (item) await supabase.from('checklist_runs').insert({ item_id: item.id, client_id: clientId, done_by: staff.id });
  return { ok: true, message: 'العقد اترفع ✅' };
}

export async function contractUrl(path: string): Promise<string | null> {
  const supabase = await createClient();
  const { data } = await supabase.storage.from('contracts').createSignedUrl(path, 60 * 10);
  return data?.signedUrl ?? null;
}
