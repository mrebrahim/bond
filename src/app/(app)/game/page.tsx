import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { dayTime, money, num } from '@/lib/format';
import { Card, CardTitle, Empty, PageHeader, Progress, Table, Td, Th, cn } from '@/components/ui';
import { Celebrate } from './celebrate';

export default async function GamePage() {
  const staff = await getStaff();
  const supabase = await createClient();
  const [{ data: board }, { data: challenges }, { data: ledger }, { data: rules }] = await Promise.all([
    supabase.from('v_leaderboard').select('*').order('position'),
    supabase.from('v_challenge_progress').select('*').eq('staff_id', staff.id).order('ends_on'),
    supabase.from('xp_ledger').select('id, points, rule_code, note, created_at, clients(name), deliverables(title)').eq('staff_id', staff.id).order('created_at', { ascending: false }).limit(50),
    supabase.from('xp_rules').select('code, name_ar'),
  ]);
  const ruleName = new Map((rules ?? []).map((r) => [r.code, r.name_ar]));
  const mine = board?.find((b) => b.staff_id === staff.id);

  return (
    <div>
      <Celebrate me={staff.id} />
      <PageHeader title="التحديات" subtitle={mine ? `${mine.rank_icon} ${mine.rank_name} · ${num(mine.xp_month)} نقطة الشهر ده · ${num(mine.xp_total)} إجمالي` : undefined} />
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardTitle>🏆 لوحة الشرف — الشهر ده</CardTitle>
          {!board?.length ? <Empty>لسه مفيش نقط</Empty> : (
            <ol className="flex flex-col gap-2">
              {board.map((b) => (
                <li key={b.staff_id} className={cn('flex items-center gap-3 rounded-lg border p-2', b.staff_id === staff.id ? 'border-brand-400 bg-brand-50' : 'border-slate-100')}>
                  <span className="w-8 text-center text-lg font-bold">{b.position === 1 ? '🥇' : b.position === 2 ? '🥈' : b.position === 3 ? '🥉' : b.position}</span>
                  <span className="text-xl" title={b.rank_name ?? ''}>{b.rank_icon}</span>
                  <div className="flex-1">
                    <div className="font-semibold">{b.name}</div>
                    <div className="text-xs text-slate-500">{b.rank_name}{b.next_rank_xp ? ` · فاضل ${num(Number(b.next_rank_xp) - Number(b.xp_total))} للرتبة الجاية` : ''}</div>
                  </div>
                  <div className="text-end">
                    <div className="font-bold tabular-nums">{num(b.xp_month)} XP</div>
                    <div className="text-xs text-emerald-700">{money(b.expected_bonus)}</div>
                  </div>
                </li>
              ))}
            </ol>
          )}
        </Card>
        <Card>
          <CardTitle>🎯 تحدياتي</CardTitle>
          {!challenges?.length ? <Empty>مفيش تحديات شغالة ليك دلوقتي</Empty> : (
            <div className="flex flex-col gap-3">
              {challenges.map((c) => (
                <div key={c.challenge_id} className={cn('rounded-lg border p-3', c.completed ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200')}>
                  <div className="flex justify-between gap-2">
                    <b>{c.completed && '✔ '}{c.name_ar}</b>
                    <span className="text-sm text-violet-700">+{c.reward_xp} XP</span>
                  </div>
                  {c.description_ar && <div className="text-xs text-slate-500">{c.description_ar}</div>}
                  <div className="mt-2"><Progress value={Number(c.progress)} max={Number(c.target)} tone={c.completed ? 'green' : undefined} /></div>
                  <div className="mt-1 text-xs text-slate-500">{num(c.progress)} / {num(c.target)}</div>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>
      <Card className="mt-4">
        <CardTitle>📜 سجل نقاطي</CardTitle>
        {!ledger?.length ? <Empty>لسه مفيش نقط</Empty> : (
          <Table>
            <thead><tr><Th>جت منين</Th><Th>العميل / الشغل</Th><Th>النقط</Th><Th>إمتى</Th></tr></thead>
            <tbody>
              {ledger.map((l) => (
                <tr key={l.id}>
                  <Td>{l.rule_code === 'challenge_reward' ? `🎯 ${l.note}` : ruleName.get(l.rule_code) ?? l.rule_code}</Td>
                  <Td className="text-xs">{[l.clients?.name, l.deliverables?.title].filter(Boolean).join(' · ') || '—'}</Td>
                  <Td className={cn('font-bold tabular-nums', l.points < 0 ? 'text-red-600' : 'text-emerald-600')}>{l.points > 0 ? '+' : ''}{l.points}</Td>
                  <Td className="text-xs">{dayTime(l.created_at)}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
    </div>
  );
}
