'use server';

import { headers } from 'next/headers';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { createClient } from '@/lib/supabase/server';
import { fail, type ActionResult } from '@/lib/db';

const emailSchema = z.string().trim().email('اكتب إيميل صحيح');

export async function signInWithPassword(fd: FormData): Promise<ActionResult> {
  const email = emailSchema.safeParse(fd.get('email'));
  if (!email.success) return fail(email.error.issues[0].message);
  const password = String(fd.get('password') ?? '');
  if (!password) return fail('اكتب الباسورد');
  const supabase = await createClient();
  const { error } = await supabase.auth.signInWithPassword({ email: email.data, password });
  if (error) return fail(error.message === 'Invalid login credentials' ? 'الإيميل أو الباسورد غلط' : error.message);
  redirect('/');
}

export async function sendMagicLink(fd: FormData): Promise<ActionResult> {
  const email = emailSchema.safeParse(fd.get('email'));
  if (!email.success) return fail(email.error.issues[0].message);
  const h = await headers();
  const origin = h.get('origin') ?? `https://${h.get('host')}`;
  const supabase = await createClient();
  const { error } = await supabase.auth.signInWithOtp({
    email: email.data,
    options: { emailRedirectTo: `${origin}/auth/callback`, shouldCreateUser: false },
  });
  if (error) return fail(error.message);
  return { ok: true, message: 'بعتنالك لينك الدخول على الإيميل' };
}

export async function signOut() {
  const supabase = await createClient();
  await supabase.auth.signOut();
  redirect('/login');
}
