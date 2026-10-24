import { redirect } from 'next/navigation';
import { getStaff } from '@/lib/auth';
import { homeFor } from '@/lib/roles';

export default async function Home() {
  const staff = await getStaff();
  redirect(homeFor(staff.roles));
}
