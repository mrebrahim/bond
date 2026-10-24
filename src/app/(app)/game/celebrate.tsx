'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { createClient } from '@/lib/supabase/client';

const COLORS = ['#1d5cf0', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'];

// احتفال لما تحدي يخلص (Realtime على xp_ledger)
export function Celebrate({ me }: { me: string }) {
  const router = useRouter();
  const [burst, setBurst] = useState(0);
  useEffect(() => {
    const supabase = createClient();
    const ch = supabase.channel('xp-feed')
      .on('postgres_changes', { event: 'INSERT', schema: 'public', table: 'xp_ledger', filter: `staff_id=eq.${me}` }, (p) => {
        const row = p.new as { rule_code: string; points: number; note: string | null };
        if (row.rule_code === 'challenge_reward') {
          setBurst((b) => b + 1);
          toast.success(`🎉 خلصت تحدي "${row.note ?? ''}" وخدت ${row.points} نقطة`);
        } else if (row.points > 0) {
          toast.success(`+${row.points} نقطة`);
        }
        router.refresh();
      })
      .subscribe();
    return () => { supabase.removeChannel(ch); };
  }, [me, router]);

  useEffect(() => {
    if (!burst) return;
    const t = setTimeout(() => setBurst(0), 3500);
    return () => clearTimeout(t);
  }, [burst]);

  if (!burst) return null;
  return (
    <div className="pointer-events-none fixed inset-0 z-50 overflow-hidden" aria-hidden>
      <style>{'@keyframes fall{to{transform:translateY(110vh) rotate(720deg);opacity:.6}}'}</style>
      {Array.from({ length: 80 }, (_, i) => (
        <span key={`${burst}-${i}`} style={{
          position: 'absolute', top: -20, left: `${(i * 37) % 100}%`, width: 8, height: 14,
          background: COLORS[i % COLORS.length], borderRadius: 2,
          animation: `fall ${2 + (i % 5) * 0.3}s ${(i % 10) * 0.08}s ease-in forwards`,
        }} />
      ))}
    </div>
  );
}
