import { cache } from 'react';
import { redirect } from 'next/navigation';
import { createClient } from '@/lib/supabase/server';
import { hasRole, type Role } from '@/lib/roles';

export type CurrentStaff = { id: string; name: string; roles: string[]; email: string | null };

/** الموظف الحالي — لو مش داخل يروح /login، ولو مالوش صف في staff يروح /pending */
export const getStaff = cache(async (): Promise<CurrentStaff> => {
  const supabase = await createClient();
  const { data: claims } = await supabase.auth.getClaims();
  const uid = claims?.claims?.sub;
  if (!uid) redirect('/login');
  const { data: staff } = await supabase.from('staff').select('id, name, roles, active').eq('id', uid).maybeSingle();
  if (!staff || !staff.active) redirect('/pending');
  return { id: staff.id, name: staff.name, roles: staff.roles, email: (claims.claims.email as string) ?? null };
});

/** للصفحات المقفولة على أدوار معينة (الـ RLS هو الحماية، ده للتجربة) */
export async function requirePageRole(allowed: Role[]): Promise<CurrentStaff> {
  const staff = await getStaff();
  if (!hasRole(staff.roles, allowed)) redirect('/');
  return staff;
}
