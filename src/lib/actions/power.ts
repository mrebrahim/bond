'use server';

import { createClient } from '@/lib/supabase/server';
import { fail, type ActionResult } from '@/lib/db';

export async function recordReading(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const meterId = String(fd.get('meter_id'));
  let photoPath: string | undefined;
  const photo = fd.get('photo');
  if (photo instanceof File && photo.size > 0) {
    const ext = photo.name.split('.').pop() || 'jpg';
    photoPath = `${meterId}/${Date.now()}.${ext}`;
    const { error: up } = await supabase.storage.from('meter-photos').upload(photoPath, photo, { contentType: photo.type });
    if (up) return fail(`الصورة مارفعتش: ${up.message}`);
  }
  const { error } = await supabase.rpc('record_meter_reading', {
    p_meter: meterId,
    p_balance: Number(fd.get('balance')),
    p_tier: fd.get('tier') ? Number(fd.get('tier')) : undefined,
    p_kwh: fd.get('kwh') ? Number(fd.get('kwh')) : undefined,
    p_photo: photoPath,
  });
  if (error) return fail(error);
  return { ok: true, message: 'القراية اتسجلت' };
}

export async function addRecharge(fd: FormData): Promise<ActionResult> {
  const supabase = await createClient();
  const { error } = await supabase.from('meter_recharges').insert({
    meter_id: String(fd.get('meter_id')),
    amount: Number(fd.get('amount')),
    note: String(fd.get('note') || '') || null,
  });
  if (error) return fail(error);
  return { ok: true, message: 'الشحن اتسجل' };
}
