import Link from 'next/link';
import { notFound } from 'next/navigation';
import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import {
  CLIENT_STATUS, CONFIRMATION, EVENT_LABELS, INVOICE_KIND, INVOICE_STATUS, PAYMENT_METHODS, PLATFORMS,
  SCRIPT_STATUS, SESSION_STATUS, SHOOT_FREQUENCY, STAGE_LABELS,
} from '@/lib/labels';
import { WEEKDAYS, cairoToday, day, dayTime, money, num, qty, shortDate, waLink } from '@/lib/format';
import {
  Badge, Card, CardTitle, Empty, Field, Health, Input, LinkButton, Progress, Select, Table, Tabs, Td, Textarea, Th, cn,
} from '@/components/ui';
import { ActionForm, SubmitButton } from '@/components/ui/client';
import { updateClient } from '@/lib/actions/clients';
import type { Row } from '@/lib/db';
import { CancelSubButton, ChangeSubButton, NewSubButton, PaymentButton, TopupButton } from './client-actions';
import { ScheduleEditor } from './schedule-editor';
import { OnboardingPanel } from './onboarding-panel';
import { loadChecklist } from '@/lib/checklists';
import { contractUrl } from '@/lib/actions/onboarding';

const TABS = [
  { key: 'onboarding', label: 'مانوال البداية' },
  { key: 'work', label: 'الشغل' },
  { key: 'sessions', label: 'الجلسات' },
  { key: 'content', label: 'بنك المحتوى' },
  { key: 'money', label: 'الفلوس' },
  { key: 'history', label: 'التاريخ' },
  { key: 'feedback', label: 'التقييمات والشكاوى' },
  { key: 'scripts', label: 'السكربتات' },
  { key: 'profile', label: 'البروفايل' },
];

export default async function ClientProfile({ params, searchParams }: {
  params: Promise<{ id: string }>; searchParams: Promise<{ tab?: string }>;
}) {
  const { id } = await params;
  const tabParam = (await searchParams).tab;
  const staff = await getStaff();
  const supabase = await createClient();

  const [{ data: client }, { data: ov }, { data: balances }, { data: subs }, { data: types }, { data: unpaid }] = await Promise.all([
    supabase.from('clients').select('*').eq('id', id).maybeSingle(),
    supabase.from('v_client_overview').select('*').eq('client_id', id).maybeSingle(),
    supabase.from('v_cycle_balance').select('*').eq('client_id', id).order('start_date').order('deliverable_type'),
    supabase.from('subscriptions').select('*, subscription_items(deliverable_type, quantity)').eq('client_id', id).eq('status', 'active').order('created_at', { ascending: false }),
    supabase.from('deliverable_types').select('code, name_ar, unit_kind').order('sort'),
    supabase.from('invoices').select('id, description, amount, paid_amount').eq('client_id', id).in('status', ['unpaid', 'partial']).order('due_date'),
  ]);
  if (!client) notFound();
  let tab = tabParam;
  if (!tab) {
    const [{ count: required }, { count: done }] = await Promise.all([
      supabase.from('checklist_template_items').select('id, checklist_templates!inner(stage)', { count: 'exact', head: true })
        .eq('is_required', true).eq('checklist_templates.stage', 'client_onboarding'),
      supabase.from('checklist_runs').select('id, checklist_template_items!inner(is_required, checklist_templates!inner(stage))', { count: 'exact', head: true })
        .eq('client_id', id).eq('checklist_template_items.is_required', true).eq('checklist_template_items.checklist_templates.stage', 'client_onboarding'),
    ]);
    tab = (done ?? 0) < (required ?? 0) ? 'onboarding' : 'work';
  }

  const activeCycle = (balances ?? []).find((b) => b.cycle_status === 'active');
  const pendingCycle = (balances ?? []).find((b) => b.cycle_status === 'pending_payment');
  const sub = subs?.[0];
  const canSub = hasRole(staff.roles, CAN.subscription);
  const canPay = hasRole(staff.roles, CAN.payment);
  const canEdit = hasRole(staff.roles, CAN.editClient);
  const daysLeft = ov?.days_left;
  const cards = (balances ?? []).filter((b) => b.cycle_id === (activeCycle?.cycle_id ?? pendingCycle?.cycle_id));

  return (
    <div>
      {/* الهيدر */}
      <Card className="mb-4">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-xl font-bold sm:text-2xl">{client.name}</h1>
              <Badge>{client.code}</Badge>
              <Badge className={client.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}>{CLIENT_STATUS[client.status]}</Badge>
            </div>
            <div className="mt-1 text-sm text-slate-500">
              {client.business_name && <>{client.business_name} · </>}
              الـ AM: {ov?.account_manager ?? '—'}
              {client.contact_person && <> · المسؤول: {client.contact_person}</>}
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-4 text-sm">
              <span>Health: <Health score={ov?.health_score ?? null} /></span>
              {sub && <span>الباقة: <b>{sub.name}</b> ({money(sub.price)})</span>}
              {daysLeft !== null && daysLeft !== undefined && (
                <span className={cn('font-semibold', daysLeft <= 3 ? 'text-red-600' : 'text-slate-700')}>باقي {daysLeft} يوم</span>
              )}
              {pendingCycle && !activeCycle && <Badge className="bg-amber-100 text-amber-800">مستني الدفع علشان يتفعل</Badge>}
              <span>دفع: <b>{money(ov?.total_paid)}</b></span>
              {Number(ov?.outstanding) > 0 && <span className="text-red-600">عليه: <b>{money(ov?.outstanding)}</b></span>}
            </div>
          </div>
          <a href={waLink(client.phone_wa)} target="_blank" rel="noreferrer" className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700">
            واتساب <span dir="ltr">{client.phone_wa}</span>
          </a>
        </div>
        <div className="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
          {hasRole(staff.roles, CAN.book) && activeCycle && <LinkButton href={`/calendar?book=${client.id}`} variant="primary">حجز جلسة</LinkButton>}
          {canSub && activeCycle && <TopupButton cycleId={activeCycle.cycle_id!} types={types ?? []} />}
          {canSub && sub && (
            <ChangeSubButton subId={sub.id} types={types ?? []} price={Number(sub.price)} validity={sub.validity_days}
              current={sub.subscription_items.map((i) => ({ type: i.deliverable_type!, qty: Number(i.quantity) }))} />
          )}
          {canSub && !sub && <NewSubButton clientId={client.id} types={types ?? []} today={cairoToday()} />}
          {canPay && <PaymentButton invoices={unpaid ?? []} />}
          {hasRole(staff.roles, CAN.cancelSubscription) && sub?.auto_renew && <CancelSubButton subId={sub.id} />}
          {sub && !sub.auto_renew && <Badge className="bg-red-50 text-red-700">مش هيجدد</Badge>}
        </div>
      </Card>

      {/* كروت الرصيد */}
      {cards.length > 0 && (
        <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          {cards.map((b) => (
            <Card key={b.deliverable_type} className="p-3">
              <div className="text-xs text-slate-500">{b.name_ar}</div>
              {b.unit_kind === 'flat' ? (
                <div className="mt-1 text-sm font-semibold text-emerald-700">✔ شغالة</div>
              ) : (
                <>
                  <div className="mt-1 text-lg font-bold tabular-nums">باقي {qty(b.remaining, b.unit_kind)}</div>
                  <Progress value={Number(b.used)} max={Number(b.total)} tone={Number(b.used_pct) >= 80 ? 'amber' : undefined} />
                  <div className="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{qty(b.used, b.unit_kind)} / {qty(b.total, b.unit_kind)}</span>
                    {Number(b.reserved) > 0 && <span className="text-blue-700">محجوز {qty(b.reserved, b.unit_kind)}</span>}
                  </div>
                </>
              )}
            </Card>
          ))}
        </div>
      )}

      <Tabs tabs={TABS} active={tab} base={`/clients/${id}`} />
      {tab === 'work' && <WorkTab clientId={id} />}
      {tab === 'sessions' && <SessionsTab clientId={id} />}
      {tab === 'content' && <ContentTab clientId={id} />}
      {tab === 'money' && <MoneyTab clientId={id} canPay={canPay} />}
      {tab === 'history' && <HistoryTab clientId={id} />}
      {tab === 'feedback' && <FeedbackTab clientId={id} />}
      {tab === 'scripts' && <ScriptsTab clientId={id} />}
      {tab === 'onboarding' && <OnboardingTab client={client} canManage={hasRole(staff.roles, CAN.editClient)} />}
      {tab === 'profile' && (
        <ProfileTab clientId={id} client={client} canEdit={canEdit} />
      )}
    </div>
  );
}

async function OnboardingTab({ client, canManage }: { client: Row<'clients'>; canManage: boolean }) {
  const supabase = await createClient();
  const groups = await loadChecklist(supabase, ['client_onboarding'], 'reel', { client_id: client.id });
  if (!groups.length) return <Empty>مفيش مانوال للعميل الجديد — ضيفه من الإعدادات ← المانوال</Empty>;
  return (
    <OnboardingPanel
      clientId={client.id}
      groups={groups}
      group={{ jid: client.wa_group_jid, link: client.wa_group_link }}
      contract={{ url: client.contract_path ? await contractUrl(client.contract_path) : null }}
      canManage={canManage}
    />
  );
}

async function WorkTab({ clientId }: { clientId: string }) {
  const supabase = await createClient();
  const { data } = await supabase.from('v_deliverable_credits').select('*').eq('client_id', clientId).order('created_at', { ascending: false }).limit(100);
  if (!data?.length) return <Empty icon="🎞️">لسه مفيش ريلز أو تصميمات</Empty>;
  return (
    <Table>
      <thead><tr><Th>العنوان</Th><Th>المرحلة</Th><Th>صوّر</Th><Th>مونتاج / تصميم</Th><Th>نشر</Th><Th>التقييم</Th></tr></thead>
      <tbody>
        {data.map((d) => (
          <tr key={d.deliverable_id}>
            <Td>
              <div className="font-medium">{d.title ?? '—'}</div>
              <div className="text-xs text-slate-400">{d.deliverable_type === 'design' ? 'تصميم' : 'ريل'}{d.revision_count ? ` · ${d.revision_count} تعديل` : ''}</div>
            </Td>
            <Td><Badge>{STAGE_LABELS[d.stage ?? ''] ?? d.stage}</Badge></Td>
            <Td>{d.photographer ?? '—'}</Td>
            <Td>{d.editor ?? '—'}</Td>
            <Td>{d.post_url ? <a href={d.post_url} target="_blank" rel="noreferrer" className="text-brand-700 underline">{d.publisher ?? 'لينك'}</a> : '—'}</Td>
            <Td className="text-xs">
              {d.feedback_at ? <>تصوير {d.filming_score ?? '—'} · مونتاج {d.editing_score ?? '—'} · لوكيشن {d.location_score ?? '—'}</> : '—'}
            </Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}

async function SessionsTab({ clientId }: { clientId: string }) {
  const supabase = await createClient();
  const { data } = await supabase.from('studio_sessions')
    .select('id, booked_start, status, confirmation, deliverable_type, planned_qty, actual_qty, late_cancel, studios(name), staff!studio_sessions_photographer_id_fkey(name)')
    .eq('client_id', clientId).order('booked_start', { ascending: false }).limit(100);
  if (!data?.length) return <Empty icon="📅">لسه مفيش جلسات</Empty>;
  return (
    <Table>
      <thead><tr><Th>الميعاد</Th><Th>الحالة</Th><Th>العدد</Th><Th>الاستوديو / المصور</Th><Th /></tr></thead>
      <tbody>
        {data.map((s) => (
          <tr key={s.id}>
            <Td>{dayTime(s.booked_start)}</Td>
            <Td className="flex flex-wrap gap-1">
              <Badge className={SESSION_STATUS[s.status].cls}>{SESSION_STATUS[s.status].label}</Badge>
              {s.status === 'booked' && <Badge className={CONFIRMATION[s.confirmation].cls}>{CONFIRMATION[s.confirmation].label}</Badge>}
              {s.late_cancel && <Badge className="bg-red-100 text-red-700">إلغاء متأخر</Badge>}
            </Td>
            <Td className="tabular-nums">{qty(s.actual_qty ?? s.planned_qty, s.deliverable_type === 'trainer_hours' ? 'hours' : 'count')}</Td>
            <Td>{s.studios?.name} · {s.staff?.name ?? '—'}</Td>
            <Td><Link href={`/sessions/${s.id}`} className="text-sm text-brand-700 hover:underline">فتح</Link></Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}

const BOARD_CLS: Record<string, string> = {
  published: 'bg-emerald-500', ready: 'bg-emerald-400', in_pipeline: 'bg-amber-400', empty: 'bg-red-500', missed: 'bg-slate-800',
};

async function ContentTab({ clientId }: { clientId: string }) {
  const supabase = await createClient();
  const [{ data: runway }, { data: slots }] = await Promise.all([
    supabase.from('v_content_runway').select('*').eq('client_id', clientId),
    supabase.from('v_slot_board').select('*').eq('client_id', clientId).order('slot_date'),
  ]);
  return (
    <div className="flex flex-col gap-4">
      {!runway?.length ? <Empty icon="🗂️">مفيش جدول نشر للعميل ده — ضيفه من تبويب البروفايل</Empty> : (
        <div className="grid gap-3 sm:grid-cols-2">
          {runway.map((r) => {
            const late = r.shoot_deadline && (!r.next_booked_session || r.next_booked_session.slice(0, 10) > r.shoot_deadline);
            return (
              <Card key={r.deliverable_type}>
                <CardTitle>{r.deliverable_type === 'reel' ? '🎬 ريلز' : '🎨 تصميمات'}</CardTitle>
                <div className="grid grid-cols-3 gap-2 text-center text-sm">
                  <div><div className="text-2xl font-bold text-emerald-600">{r.ready}</div>جاهز</div>
                  <div><div className="text-2xl font-bold text-amber-600">{r.in_pipeline}</div>في المونتاج</div>
                  <div><div className="text-2xl font-bold">{r.slots_next_7d}</div>مواعيد الأسبوع</div>
                </div>
                <div className="mt-3 space-y-1 text-sm">
                  <div>أول ميعاد فاضي: <b>{day(r.first_gap_date)}</b></div>
                  {r.deliverable_type === 'reel' && (
                    <div className={cn(late && 'font-semibold text-red-600')}>
                      لازم يصوّر قبل: {day(r.shoot_deadline)} · الجلسة الجاية: {r.next_booked_session ? dayTime(r.next_booked_session) : 'مفيش'}
                    </div>
                  )}
                </div>
              </Card>
            );
          })}
        </div>
      )}
      {!!slots?.length && (
        <Card>
          <CardTitle>مواعيد النشر</CardTitle>
          <div className="flex flex-wrap gap-2">
            {slots.map((s) => (
              <div key={s.slot_id} className="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2 py-1 text-xs" title={s.board_status ?? ''}>
                <span className={cn('h-2.5 w-2.5 rounded-full', BOARD_CLS[s.board_status ?? 'empty'])} />
                {shortDate(s.slot_date)} {s.deliverable_type === 'design' ? '🎨' : '🎬'}
              </div>
            ))}
          </div>
          <div className="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">
            <span>🟢 فيه محتوى</span><span>🟡 في المونتاج</span><span>🔴 فاضي</span><span>⚫ فات</span>
          </div>
        </Card>
      )}
    </div>
  );
}

async function MoneyTab({ clientId, canPay }: { clientId: string; canPay: boolean }) {
  const supabase = await createClient();
  const [{ data: invoices }, { data: payments }] = await Promise.all([
    supabase.from('invoices').select('*').eq('client_id', clientId).order('created_at', { ascending: false }),
    supabase.from('payments').select('*, staff(name)').eq('client_id', clientId).order('paid_at', { ascending: false }),
  ]);
  const unpaid = (invoices ?? []).filter((i) => i.status === 'unpaid' || i.status === 'partial');
  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardTitle>الفواتير</CardTitle>
        {!invoices?.length ? <Empty>مفيش فواتير</Empty> : (
          <Table>
            <thead><tr><Th>الوصف</Th><Th>النوع</Th><Th>المبلغ</Th><Th>المدفوع</Th><Th>الاستحقاق</Th><Th>الحالة</Th><Th /></tr></thead>
            <tbody>
              {invoices.map((i) => (
                <tr key={i.id}>
                  <Td>{i.description}</Td>
                  <Td>{INVOICE_KIND[i.kind]}</Td>
                  <Td className="tabular-nums">{money(i.amount)}</Td>
                  <Td className="tabular-nums">{money(i.paid_amount)}</Td>
                  <Td>{day(i.due_date)}</Td>
                  <Td><Badge className={INVOICE_STATUS[i.status].cls}>{INVOICE_STATUS[i.status].label}</Badge></Td>
                  <Td>{canPay && (i.status === 'unpaid' || i.status === 'partial') && <PaymentButton invoices={unpaid} invoiceId={i.id} label="دفع" />}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
      <Card>
        <CardTitle>المدفوعات</CardTitle>
        {!payments?.length ? <Empty>مفيش مدفوعات</Empty> : (
          <Table>
            <thead><tr><Th>التاريخ</Th><Th>المبلغ</Th><Th>الطريقة</Th><Th>المرجع</Th><Th>استلم</Th></tr></thead>
            <tbody>
              {payments.map((p) => (
                <tr key={p.id}>
                  <Td>{dayTime(p.paid_at)}</Td>
                  <Td className="tabular-nums">{money(p.amount)}</Td>
                  <Td>{PAYMENT_METHODS[p.method]}</Td>
                  <Td><span dir="ltr">{p.reference ?? "—"}</span></Td>
                  <Td>{p.staff?.name ?? '—'}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
    </div>
  );
}

async function HistoryTab({ clientId }: { clientId: string }) {
  const supabase = await createClient();
  const [{ data: cycles }, { data: events }, { data: dtypes }] = await Promise.all([
    supabase.from('cycles').select('id, cycle_no, start_date, end_date, price, status, subscriptions(name), cycle_entitlements(deliverable_type, granted, bonus, used, forfeited)')
      .eq('client_id', clientId).order('start_date', { ascending: false }),
    supabase.from('subscription_events').select('*').eq('client_id', clientId).order('created_at', { ascending: false }),
    supabase.from('deliverable_types').select('code, name_ar, unit_kind'),
  ]);
  const dt = new Map((dtypes ?? []).map((d) => [d.code, d]));
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardTitle>الدورات</CardTitle>
        {!cycles?.length ? <Empty>مفيش دورات</Empty> : (
          <div className="flex flex-col gap-3">
            {cycles.map((c) => (
              <div key={c.id} className="rounded-lg border border-slate-200 p-3">
                <div className="flex justify-between text-sm">
                  <b>{c.subscriptions?.name} — دورة {c.cycle_no}</b>
                  <Badge>{c.status === 'active' ? 'نشطة' : c.status === 'expired' ? 'خلصت' : c.status === 'pending_payment' ? 'مستنية دفع' : 'ملغية'}</Badge>
                </div>
                <div className="text-xs text-slate-500">{shortDate(c.start_date)} → {shortDate(c.end_date)} · {money(c.price)}</div>
                <div className="mt-2 grid gap-1 text-xs">
                  {c.cycle_entitlements.filter((e) => dt.get(e.deliverable_type ?? '')?.unit_kind !== 'flat').map((e) => {
                    const u = dt.get(e.deliverable_type ?? '')?.unit_kind;
                    return (
                      <div key={e.deliverable_type} className="flex justify-between">
                        <span>{dt.get(e.deliverable_type ?? '')?.name_ar}</span>
                        <span className="tabular-nums">
                          استخدم {qty(e.used, u)} من {qty(Number(e.granted) + Number(e.bonus), u)}
                          {Number(e.forfeited) > 0 && <span className="text-red-600"> · ضاع {qty(e.forfeited, u)}</span>}
                        </span>
                      </div>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
      <Card>
        <CardTitle>أحداث الاشتراك</CardTitle>
        {!events?.length ? <Empty>مفيش أحداث</Empty> : (
          <ul className="flex flex-col gap-2 text-sm">
            {events.map((e) => (
              <li key={e.id} className="flex items-start justify-between gap-2 border-b border-slate-100 pb-2">
                <div>
                  <b>{EVENT_LABELS[e.event_type]}</b>
                  {e.note && <div className="text-xs text-slate-500">{e.note}</div>}
                </div>
                <div className="text-end text-xs text-slate-500">
                  {day(e.effective_date)}
                  {Number(e.mrr_delta) !== 0 && <div className={Number(e.mrr_delta) > 0 ? 'text-emerald-600' : 'text-red-600'}>MRR {Number(e.mrr_delta) > 0 ? '+' : ''}{money(e.mrr_delta)}</div>}
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}

async function FeedbackTab({ clientId }: { clientId: string }) {
  const supabase = await createClient();
  const [{ data: fb }, { data: complaints }] = await Promise.all([
    supabase.from('feedback').select('*, deliverables(title)').eq('client_id', clientId).order('submitted_at', { ascending: false }),
    supabase.from('v_complaints').select('*').eq('client_id', clientId).order('created_at', { ascending: false }),
  ]);
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardTitle>التقييمات</CardTitle>
        {!fb?.length ? <Empty>لسه مفيش تقييمات</Empty> : (
          <ul className="flex flex-col gap-2 text-sm">
            {fb.map((f) => (
              <li key={f.id} className="border-b border-slate-100 pb-2">
                <div className="flex justify-between"><b>{f.deliverables?.title ?? '—'}</b><span className="text-xs text-slate-500">{day(f.submitted_at)}</span></div>
                <div className="text-xs">تصوير {f.filming_score ?? '—'} · مونتاج {f.editing_score ?? '—'} · لوكيشن {f.location_score ?? '—'}</div>
                {f.comment && <div className="mt-1 text-slate-600">“{f.comment}”</div>}
              </li>
            ))}
          </ul>
        )}
      </Card>
      <Card>
        <CardTitle>الشكاوى</CardTitle>
        {!complaints?.length ? <Empty>مفيش شكاوى 🎉</Empty> : (
          <ul className="flex flex-col gap-2 text-sm">
            {complaints.map((c) => (
              <li key={c.alert_id} className="border-b border-slate-100 pb-2">
                <div className="flex justify-between">
                  <Badge className={c.is_open ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'}>{c.is_open ? 'مفتوحة' : 'اتقفلت'}</Badge>
                  <span className="text-xs text-slate-500">{dayTime(c.created_at)}</span>
                </div>
                {c.comment && <div className="mt-1">“{c.comment}”</div>}
                {!c.is_open && <div className="mt-1 text-xs text-slate-600">{c.handled_by_name}: {c.resolution} (بعد {num(c.wait_minutes)} دقيقة)</div>}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}

async function ScriptsTab({ clientId }: { clientId: string }) {
  const supabase = await createClient();
  const { data } = await supabase.from('scripts').select('id, title, status, revision_count, created_at').eq('client_id', clientId).order('created_at', { ascending: false });
  if (!data?.length) return <Empty icon="📝">لسه مفيش سكربتات — <Link className="text-brand-700 underline" href={`/scripts?client=${clientId}`}>اكتب واحد</Link></Empty>;
  return (
    <Table>
      <thead><tr><Th>العنوان</Th><Th>الحالة</Th><Th>التعديلات</Th><Th /></tr></thead>
      <tbody>
        {data.map((s) => (
          <tr key={s.id}>
            <Td>{s.title}</Td>
            <Td><Badge className={SCRIPT_STATUS[s.status].cls}>{SCRIPT_STATUS[s.status].label}</Badge></Td>
            <Td>{s.revision_count}</Td>
            <Td><Link className="text-sm text-brand-700 hover:underline" href={`/scripts?id=${s.id}`}>فتح</Link></Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}

async function ProfileTab({ clientId, client, canEdit }: {
  clientId: string;
  client: Row<'clients'>;
  canEdit: boolean;
}) {
  const supabase = await createClient();
  const [{ data: schedule }, { data: ams }] = await Promise.all([
    supabase.from('publishing_schedule').select('deliverable_type, weekday, publish_time, platforms').eq('client_id', clientId).eq('active', true),
    supabase.from('staff').select('id, name').contains('roles', ['account_manager']).eq('active', true),
  ]);
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardTitle>بيانات العميل وأسئلة الـ onboarding</CardTitle>
        <ActionForm action={updateClient.bind(null, clientId)} reset={false} className="grid gap-3 sm:grid-cols-2">
          <fieldset disabled={!canEdit} className="contents">
            <Field label="الاسم"><Input name="name" defaultValue={client.name} required /></Field>
            <Field label="اسم البيزنس"><Input name="business_name" defaultValue={client.business_name ?? ''} /></Field>
            <Field label="الواتساب"><Input name="phone_wa" dir="ltr" defaultValue={client.phone_wa} required /></Field>
            <Field label="المسؤول"><Input name="contact_person" defaultValue={client.contact_person ?? ''} /></Field>
            <Field label="المجال"><Input name="industry" defaultValue={client.industry ?? ''} /></Field>
            <Field label="الـ AM">
              <Select name="account_manager_id" defaultValue={client.account_manager_id ?? ''}>
                <option value="">—</option>
                {(ams ?? []).map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
              </Select>
            </Field>
            <Field label="المنصات" className="sm:col-span-2">
              <div className="flex flex-wrap gap-3">
                {Object.entries(PLATFORMS).map(([k, v]) => (
                  <label key={k} className="flex items-center gap-1.5 text-sm">
                    <input type="checkbox" name="platforms" value={k} defaultChecked={client.platforms.includes(k)} className="h-4 w-4" />{v}
                  </label>
                ))}
              </div>
            </Field>
            <Field label="بيصوّر كل قد إيه">
              <Select name="shoot_frequency" defaultValue={client.shoot_frequency ?? ''}>
                <option value="">—</option>
                {Object.entries(SHOOT_FREQUENCY).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </Select>
            </Field>
            <Field label="اليوم المفضل">
              <Select name="preferred_shoot_day" defaultValue={client.preferred_shoot_day ?? ''}>
                <option value="">—</option>
                {WEEKDAYS.map((d, i) => <option key={d} value={i}>{d}</option>)}
              </Select>
            </Field>
            <Field label="متوسط الريلز في الجلسة"><Input name="reels_per_session_avg" type="number" defaultValue={client.reels_per_session_avg ?? ''} /></Field>
            <Field label="الحد الأدنى للمخزون (أسابيع)"><Input name="buffer_weeks" type="number" step={0.5} defaultValue={client.buffer_weeks} /></Field>
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="needs_approval" defaultChecked={client.needs_approval} className="h-4 w-4" /> محتاج موافقة قبل النشر</label>
            <Field label="بيرد في كام ساعة"><Input name="approval_sla_hours" type="number" defaultValue={client.approval_sla_hours} /></Field>
            <Field label="نبرة الكابشن" className="sm:col-span-2"><Textarea name="caption_tone" defaultValue={client.caption_tone ?? ''} /></Field>
            <Field label="لينك البراند" className="sm:col-span-2"><Input name="brand_assets_url" dir="ltr" defaultValue={client.brand_assets_url ?? ''} /></Field>
            <Field label="ملاحظات" className="sm:col-span-2"><Textarea name="notes" defaultValue={client.notes ?? ''} /></Field>
            {canEdit && <SubmitButton className="sm:col-span-2">احفظ</SubmitButton>}
          </fieldset>
        </ActionForm>
      </Card>
      <Card>
        <CardTitle>جدول النشر</CardTitle>
        <ScheduleEditor clientId={clientId} rows={schedule ?? []} platforms={client.platforms} canEdit={canEdit} />
      </Card>
    </div>
  );
}
