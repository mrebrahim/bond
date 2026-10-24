import Link from 'next/link';
import { createClient } from '@/lib/supabase/server';
import { getStaff } from '@/lib/auth';
import { CAN, hasRole } from '@/lib/roles';
import { SCRIPT_STATUS } from '@/lib/labels';
import { day } from '@/lib/format';
import { Badge, Card, CardTitle, Empty, Field, Input, LinkButton, PageHeader, Select, Textarea, cn } from '@/components/ui';
import { ActionButton, ActionForm, SubmitButton } from '@/components/ui/client';
import { saveScript, setScriptStatus } from '@/lib/actions/scripts';

export default async function ScriptsPage({ searchParams }: { searchParams: Promise<Record<string, string | undefined>> }) {
  const sp = await searchParams;
  const staff = await getStaff();
  const supabase = await createClient();
  const canEdit = hasRole(staff.roles, CAN.scripts);

  let q = supabase.from('scripts').select('id, title, status, revision_count, created_at, client_id, clients(name)');
  if (sp.client) q = q.eq('client_id', sp.client);
  if (sp.status) q = q.eq('status', sp.status as 'draft');
  const [{ data: list }, { data: clients }, { data: current }] = await Promise.all([
    q.order('created_at', { ascending: false }).limit(200),
    supabase.from('clients').select('id, name').eq('status', 'active').order('name'),
    sp.id ? supabase.from('scripts').select('*, clients(name)').eq('id', sp.id).maybeSingle() : Promise.resolve({ data: null }),
  ]);

  return (
    <div>
      <PageHeader title="السكربتات" actions={canEdit && <LinkButton href={`/scripts?new=1${sp.client ? `&client=${sp.client}` : ''}`} variant="primary">+ سكربت</LinkButton>} />
      <div className="grid gap-4 lg:grid-cols-[1fr_1.3fr]">
        <div>
          <form action="/scripts" className="mb-3 flex gap-2">
            <Select name="client" defaultValue={sp.client ?? ''}><option value="">كل العملاء</option>{(clients ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select>
            <Select name="status" defaultValue={sp.status ?? ''}><option value="">كل الحالات</option>{Object.entries(SCRIPT_STATUS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}</Select>
            <button className="min-h-11 rounded-lg bg-slate-800 px-4 text-sm text-white">فلتر</button>
          </form>
          {!list?.length ? <Empty icon="📝">مفيش سكربتات</Empty> : (
            <div className="flex flex-col gap-2">
              {list.map((s) => (
                <Link key={s.id} href={`/scripts?id=${s.id}${sp.client ? `&client=${sp.client}` : ''}`}
                  className={cn('rounded-lg border bg-white p-3 hover:border-brand-400', sp.id === s.id ? 'border-brand-500' : 'border-slate-200')}>
                  <div className="flex items-center justify-between gap-2">
                    <b className="text-sm">{s.title}</b>
                    <Badge className={SCRIPT_STATUS[s.status].cls}>{SCRIPT_STATUS[s.status].label}</Badge>
                  </div>
                  <div className="text-xs text-slate-500">{s.clients?.name} · {day(s.created_at)}{s.revision_count ? ` · ${s.revision_count} تعديل` : ''}</div>
                </Link>
              ))}
            </div>
          )}
        </div>

        {(current || sp.new) && canEdit && (
          <Card>
            <CardTitle>{current ? current.clients?.name : 'سكربت جديد'}</CardTitle>
            <ActionForm key={current?.id ?? 'new'} action={saveScript.bind(null, current?.id ?? null)} reset={!current} className="flex flex-col gap-3">
              {!current && (
                <Field label="العميل">
                  <Select name="client_id" defaultValue={sp.client ?? ''} required>
                    <option value="">—</option>
                    {(clients ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </Select>
                </Field>
              )}
              <Field label="العنوان"><Input name="title" defaultValue={current?.title ?? ''} required /></Field>
              <Field label="السكربت"><Textarea name="body" rows={14} defaultValue={current?.body ?? ''} className="leading-7" /></Field>
              {current?.status === 'approved' && <p className="text-xs text-amber-700">أي تعديل بعد الموافقة بيزوّد عدد التعديلات وبيرجعه للمراجعة.</p>}
              <SubmitButton>احفظ</SubmitButton>
            </ActionForm>
            {current && (
              <div className="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                {current.status === 'draft' && <ActionButton action={setScriptStatus.bind(null, current.id, 'internal_review')}>مراجعة داخلية</ActionButton>}
                {['draft', 'internal_review', 'changes_requested'].includes(current.status) && (
                  <ActionButton variant="primary" action={setScriptStatus.bind(null, current.id, 'sent_to_client')}>ابعت للعميل</ActionButton>
                )}
                {current.status === 'sent_to_client' && (
                  <>
                    <ActionButton action={setScriptStatus.bind(null, current.id, 'changes_requested')}>طلب تعديل</ActionButton>
                    <ActionButton variant="success" action={setScriptStatus.bind(null, current.id, 'approved', 'whatsapp')}>اتوافق (واتساب)</ActionButton>
                    <ActionButton variant="success" action={setScriptStatus.bind(null, current.id, 'approved', 'in_person')}>اتوافق (حضوري)</ActionButton>
                  </>
                )}
                {current.sent_at && <span className="self-center text-xs text-slate-500">اتبعت {day(current.sent_at)}</span>}
                {current.approved_at && <span className="self-center text-xs text-emerald-700">اتوافق {day(current.approved_at)}</span>}
              </div>
            )}
          </Card>
        )}
      </div>
    </div>
  );
}
