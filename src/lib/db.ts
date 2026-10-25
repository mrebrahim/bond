import type { Database } from '@/types/database';

type PublicSchema = Database['public'];
export type Row<T extends keyof PublicSchema['Tables']> = PublicSchema['Tables'][T]['Row'];
export type View<T extends keyof PublicSchema['Views']> = PublicSchema['Views'][T]['Row'];

/** شكل عنصر في balances (jsonb) جوه v_client_overview */
export type BalanceItem = {
  type: string;
  name: string;
  unit: 'count' | 'hours' | 'flat';
  total: number;
  used: number;
  remaining: number;
  reserved: number;
};

export type ActionResult = { ok: true; message?: string; data?: unknown } | { ok: false; error: string; data?: unknown };

export function fail(error: { message: string } | string): ActionResult {
  return { ok: false, error: typeof error === 'string' ? error : error.message };
}
