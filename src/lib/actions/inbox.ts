'use server';

import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { fail, type ActionResult } from '@/lib/db';

export async function resolveAlert(alertId: number, fd: FormData): Promise<ActionResult> {
  const staff = await getStaff();
  const supabase = await createClient();
  const { error } = await supabase.rpc('resolve_alert', {
    p_alert: alertId, p_resolution: String(fd.get('resolution') || ''), p_by: staff.id,
  });
  if (error) return fail(error);
  return { ok: true, message: 'اتقفل ✔' };
}
