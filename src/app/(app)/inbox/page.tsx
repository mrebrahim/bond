import Link from 'next/link';
import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { ALERT_LABELS, AUDIENCE_LABELS } from '@/lib/labels';
import { ago, day, dayTime, minutesLabel, waLink } from '@/lib/format';
import type { Json } from '@/types/database';
import { Badge, Card, Empty, Field, LinkButton, PageHeader, Table, Td, Textarea, Th, cn } from '@/components/ui';
import { ActionForm, Modal, SubmitButton } from '@/components/ui/client';
import { resolveAlert } from '@/lib/actions/inbox';

type P = Record<string, Json | undefined>;

function summary(key: string, p: P): string | null {
  const s = (k: string) => (p[k] === null || p[k] === undefined ? '' : String(p[k]));
  switch (key) {
    case 'client_unhappy': case 'feedback_low': case 'revision_requested':
      return [s('comment') && `“${s('comment')}”`, `تصوير ${s('filming') || '—'} · مونتاج ${s('editing') || '—'} · لوكيشن ${s('location') || '—'}`].filter(Boolean).join(' · ');
    case 'complaint_escalated': return `مستني من ${s('wait_minutes')} دقيقة — ${s('comment')}`;
    case 'payment_overdue': return `${s('description')} — ${s('amount')} ج.م`;
    case 'usage_high': case 'usage_full_early': return `${s('name')}: ${s('used') || ''}/${s('total') || ''}`;
    case 'editing_late': return s('title');
    case 'power_low': return `الرصيد ${s('balance')} ج.م`;
    case 'content_runway_low': return `أول ميعاد فاضي ${s('first_gap_date')} — لازم يصوّر قبل ${s('shoot_deadline')}`;
    case 'client_inactive':
      return [`مصوّرش من ${s('days')} يوم`, s('last_session') ? `آخر تصوير ${day(s('last_session'))}` : 'مصوّرش خالص في الباقة دي',
        `فاضله ${s('remaining')}`,
        p.content_out ? '⚠️ المحتوى خلص — مفيش حاجة جاهزة ننزلها على السوشيال' : `جاهز للنشر ${s('ready')}`,
        s('first_gap_date') && `أول ميعاد نشر فاضي ${day(s('first_gap_date'))}`].filter(Boolean).join(' · ');
    default: return null;
  }
}

export default async function InboxPage({ searchParams }: { searchParams: Promise<{ filter?: string; tab?: string }> }) {
  const sp = await searchParams;
  const tab = sp.tab ?? 'alerts';
  const staff = await getStaff();
  const canComplaint = hasRole(staff.roles, CAN.complaint);

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader title="التنبيهات والشكاوى" actions={
        <>
          <LinkButton size="sm" variant={tab === 'alerts' ? 'primary' : 'secondary'} href="/inbox">التنبيهات</LinkButton>
          <LinkButton size="sm" variant={tab === 'complaints' ? 'primary' : 'secondary'} href="/inbox?tab=complaints">الشكاوى</LinkButton>
        </>
      } />
      {tab === 'complaints' ? <Complaints canComplaint={canComplaint} /> : <Alerts filter={sp.filter} canComplaint={canComplaint} />}
    </div>
  );
}

async function Alerts({ filter, canComplaint }: { filter?: string; canComplaint: boolean }) {
  const supabase = await createClient();
  let q = supabase.from('alerts')
    .select('id, alert_key, audience, priority, payload, created_at, handled_at, resolution, client_id, clients(name, phone_wa)')
    .neq('audience', 'client')
    .gte('created_at', new Date(Date.now() - 30 * 864e5).toISOString());
  if (filter === 'sales') q = q.in('audience', ['sales', 'admin']);
  const { data } = await q.order('handled_at', { ascending: false, nullsFirst: true }).order('priority', { ascending: false }).order('created_at', { ascending: false }).limit(200);

  // تنبيه واحد لكل حدث (الحدث ممكن يتبعت لأكتر من جمهور)
  const seen = new Set<string>();
  const list = (data ?? []).filter((a) => { const k = `${a.alert_key}:${a.client_id}:${a.created_at}`; if (seen.has(k)) return false; seen.add(k); return true; });
  if (!list.length) return <Empty icon="🔔">مفيش تنبيهات — كله تمام</Empty>;

  return (
    <div className="flex flex-col gap-2">
      {list.map((a) => {
        const p = (a.payload ?? {}) as P;
        const urgent = a.priority === 'urgent' && !a.handled_at;
        const isComplaint = a.alert_key === 'client_unhappy' || a.alert_key === 'complaint_escalated';
        return (
          <Card key={a.id} className={cn('p-3', urgent && 'border-red-300 bg-red-50', a.handled_at && 'opacity-60')}>
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <b>{ALERT_LABELS[a.alert_key] ?? a.alert_key}</b>
                  <Badge>{AUDIENCE_LABELS[a.audience]}</Badge>
                  {a.clients && <Link href={`/clients/${a.client_id}`} className="text-sm text-brand-700 hover:underline">{a.clients.name}</Link>}
                </div>
                {summary(a.alert_key, p) && <div className="mt-1 text-sm text-slate-700">{summary(a.alert_key, p)}</div>}
                {isComplaint && (p.photographer || p.editor) && <div className="text-xs text-slate-500">المصور: {String(p.photographer ?? '—')} · المونتير: {String(p.editor ?? '—')}</div>}
                {typeof p.post_url === 'string' && p.post_url && <a href={p.post_url} target="_blank" rel="noreferrer" className="text-xs text-brand-700 underline">لينك البوست</a>}
                <div className="mt-1 text-xs text-slate-500">{a.handled_at ? `اتقفل: ${a.resolution}` : `مستني من ${ago(a.created_at)}`}</div>
              </div>
              {!a.handled_at && (
                <div className="flex gap-2">
                  {a.clients?.phone_wa && <a href={waLink(a.clients.phone_wa)} target="_blank" rel="noreferrer" className="inline-flex min-h-11 items-center rounded-lg border border-emerald-300 px-3 text-sm text-emerald-700">واتساب</a>}
                  {a.clients?.phone_wa && <a href={`tel:+${a.clients.phone_wa}`} className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm">اتصال</a>}
                  {(!isComplaint || canComplaint) && (
                    <Modal trigger={isComplaint ? 'كلمته' : 'تم'} title={isComplaint ? 'حصل إيه مع العميل؟' : 'قفل التنبيه'} triggerVariant={isComplaint ? 'primary' : 'secondary'}>
                      <ActionForm action={resolveAlert.bind(null, a.id)} className="flex flex-col gap-3">
                          <Field label="حصل إيه *"><Textarea name="resolution" required /></Field>
                          <SubmitButton>اقفل</SubmitButton>
                        </ActionForm>
                    </Modal>
                  )}
                </div>
              )}
            </div>
          </Card>
        );
      })}
    </div>
  );
}

async function Complaints({ canComplaint }: { canComplaint: boolean }) {
  const supabase = await createClient();
  const { data } = await supabase.from('v_complaints').select('*').order('is_open', { ascending: false }).order('created_at', { ascending: false }).limit(200);
  if (!data?.length) return <Empty icon="🎉">مفيش شكاوى</Empty>;
  return (
    <Table>
      <thead><tr><Th>العميل</Th><Th>قال إيه</Th><Th>الحالة</Th><Th>وقت الرد</Th><Th>مين كلمه</Th><Th /></tr></thead>
      <tbody>
        {data.map((c) => (
          <tr key={c.alert_id} className={cn(c.is_open && 'bg-red-50')}>
            <Td><Link href={`/clients/${c.client_id}?tab=feedback`} className="font-medium text-brand-700">{c.client_name}</Link><div className="text-xs text-slate-400">{dayTime(c.created_at)}</div></Td>
            <Td className="max-w-xs text-xs">{c.comment ?? '—'}<div className="text-slate-400">تصوير {c.filming_score ?? '—'} · مونتاج {c.editing_score ?? '—'}</div></Td>
            <Td>
              <Badge className={c.is_open ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'}>{c.is_open ? 'مفتوحة' : 'اتقفلت'}</Badge>
              {c.escalated && <Badge className="ms-1 bg-red-600 text-white">اتصعّدت</Badge>}
            </Td>
            <Td className={cn('tabular-nums', !c.within_sla && 'font-semibold text-red-600')}>{minutesLabel(c.wait_minutes)}</Td>
            <Td className="text-xs">{c.handled_by_name ? <>{c.handled_by_name}: {c.resolution}</> : '—'}</Td>
            <Td>{c.is_open && canComplaint && (
              <Modal trigger="كلمته" title="حصل إيه مع العميل؟" triggerVariant="primary" triggerSize="sm">
                <ActionForm action={resolveAlert.bind(null, c.alert_id!)} className="flex flex-col gap-3">
                    <Field label="حصل إيه *"><Textarea name="resolution" required /></Field>
                    <SubmitButton>اقفل</SubmitButton>
                  </ActionForm>
              </Modal>
            )}</Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}
