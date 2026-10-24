'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { createClient } from '@/lib/supabase/client';
import { ALERT_LABELS } from '@/lib/labels';

// Realtime: أي تنبيه جديد يخصني (الـ RLS بيفلتر) → refresh + صوت للعاجل
export function AlertsListener() {
  const router = useRouter();
  useEffect(() => {
    const supabase = createClient();
    const channel = supabase
      .channel('alerts-feed')
      .on('postgres_changes', { event: 'INSERT', schema: 'public', table: 'alerts' }, (payload) => {
        const a = payload.new as { alert_key: string; priority: string; audience: string };
        if (a.audience === 'client') return;
        if (a.priority === 'urgent') {
          beep();
          toast.error(ALERT_LABELS[a.alert_key] ?? a.alert_key, { duration: 10000 });
        }
        router.refresh();
      })
      .subscribe();
    return () => { supabase.removeChannel(channel); };
  }, [router]);
  return null;
}

function beep() {
  try {
    const ctx = new AudioContext();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.frequency.value = 880;
    o.connect(g); g.connect(ctx.destination);
    g.gain.setValueAtTime(0.2, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
    o.start(); o.stop(ctx.currentTime + 0.6);
  } catch { /* المتصفح ممكن يمنع الصوت قبل أي تفاعل */ }
}
