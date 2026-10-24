import { SUPABASE_KEY, SUPABASE_URL } from '@/lib/supabase/env';
import { createBrowserClient } from '@supabase/ssr';
import type { Database } from '@/types/database';

export function createClient() {
  return createBrowserClient<Database>(
    SUPABASE_URL,
    SUPABASE_KEY,
  );
}
