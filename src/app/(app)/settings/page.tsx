import { createClient } from '@/lib/supabase/server';
import { requirePageRole } from '@/lib/auth';
import { ROLES, ROLE_LABELS } from '@/lib/roles';
import { money, num } from '@/lib/format';
import { Badge, Card, CardTitle, Empty, Field, Input, Select, Table, Tabs, Td, Textarea, Th, PageHeader } from '@/components/ui';
import { ActionButton, ActionForm, SubmitButton } from '@/components/ui/client';
import {
  addChallenge, addChecklistItem, addExistingStaff, addMeter, addStudio, deleteChecklistItem, inviteStaff, prepareBonuses,
  setBonusStatus, toggleChallenge, togglePlan, toggleStudio, updateChecklistItem, updateSetting, updateStaff, updateXpRule,
} from '@/lib/actions/settings';
import { PlanForm } from './plan-form';
import { WhatsappPanel } from './whatsapp-panel';

const TABS = [
  { key: 'general', label: 'عام' }, { key: 'plans', label: 'الباقات' }, { key: 'manual', label: 'المانوال' },
  { key: 'xp', label: 'النقاط' }, { key: 'challenges', label: 'التحديات' }, { key: 'team', label: 'الفريق' },
  { key: 'bonus', label: 'البونص' }, { key: 'studios', label: 'الاستوديوهات والعدادات' }, { key: 'whatsapp', label: 'الواتساب' },
];
const STAGES: Record<string, string> = {
  onboarding: 'أول جلسة', script: 'سكربت', pre_shoot: 'قبل التصوير', shoot: 'يوم التصوير', post_shoot: 'بعد التصوير',
  editing: 'المونتاج', design: 'التصميم', publishing: 'النشر',
};
const METRICS: Record<string, string> = {
  sessions_done: 'جلسات خلصت', deliverables_approved: 'شغل اتوافق عليه', deliverables_published: 'بوستات اتنشرت في ميعادها',
  top_feedback: 'تقييمات 5', scripts_approved: 'سكربتات اتوافق عليها', xp: 'نقط',
};

function RolesPicker({ selected = [] }: { selected?: string[] }) {
  return (
    <div className="flex flex-wrap gap-2">
      {ROLES.map((r) => (
        <label key={r} className="flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs">
          <input type="checkbox" name="roles" value={r} defaultChecked={selected.includes(r)} />{ROLE_LABELS[r]}
        </label>
      ))}
    </div>
  );
}

export default async function SettingsPage({ searchParams }: { searchParams: Promise<{ tab?: string }> }) {
  await requirePageRole([]);
  const tab = (await searchParams).tab ?? 'general';
  return (
    <div>
      <PageHeader title="الإعدادات" />
      <Tabs tabs={TABS} active={tab} base="/settings" />
      {tab === 'general' && <General />}
      {tab === 'plans' && <Plans />}
      {tab === 'manual' && <Manual />}
      {tab === 'xp' && <Xp />}
      {tab === 'challenges' && <Challenges />}
      {tab === 'team' && <Team />}
      {tab === 'bonus' && <Bonus />}
      {tab === 'studios' && <Studios />}
      {tab === 'whatsapp' && <Whatsapp />}
    </div>
  );
}

async function General() {
  const supabase = await createClient();
  const { data } = await supabase.from('settings').select('*').order('key');
  return (
    <Card>
      <div className="flex flex-col divide-y divide-slate-100">
        {(data ?? []).map((s) => (
          <ActionForm key={s.key} action={updateSetting.bind(null, s.key)} reset={false} className="flex flex-wrap items-center gap-3 py-3">
            <div className="min-w-60 flex-1">
              <div className="text-sm font-medium">{s.description_ar}</div>
              <div className="text-xs text-slate-400" dir="ltr">{s.key}</div>
            </div>
            {typeof s.value === 'string' && s.value.length > 60
              ? <Textarea name="value" defaultValue={s.value} rows={8} className="w-full sm:w-[28rem]" />
              : <Input name="value" defaultValue={typeof s.value === 'object' ? JSON.stringify(s.value) : String(s.value)} dir="ltr" className="w-48" />}
            <SubmitButton size="sm" variant="secondary">احفظ</SubmitButton>
          </ActionForm>
        ))}
      </div>
    </Card>
  );
}

async function Whatsapp() {
  const supabase = await createClient();
  const { data } = await supabase.from('settings').select('key, value').in('key', ['outbox_enabled', 'wa_messages_per_day']);
  const get = (k: string) => String(data?.find((x) => x.key === k)?.value ?? '');
  return <WhatsappPanel enabled={get('outbox_enabled') === 'true'} perDay={get('wa_messages_per_day') || '3'} />;
}

async function Plans() {
  const supabase = await createClient();
  const [{ data: plans }, { data: types }] = await Promise.all([
    supabase.from('plans').select('*, plan_items(deliverable_type, quantity)').order('base_price'),
    supabase.from('deliverable_types').select('code, name_ar, unit_kind').order('sort'),
  ]);
  const name = new Map((types ?? []).map((t) => [t.code, t.name_ar]));
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardTitle>القوالب</CardTitle>
        {!plans?.length ? <Empty>مفيش قوالب لسه</Empty> : (
          <ul className="flex flex-col gap-2">
            {plans.map((p) => (
              <li key={p.id} className="flex items-start justify-between gap-2 rounded-lg border border-slate-200 p-3 text-sm">
                <div>
                  <b>{p.name}</b> · {money(p.base_price)} · {p.validity_days} يوم
                  <div className="text-xs text-slate-500">{p.plan_items.map((i) => `${name.get(i.deliverable_type ?? '')} ${num(i.quantity)}`).join(' · ')}</div>
                </div>
                <ActionButton size="sm" action={togglePlan.bind(null, p.id, !p.active)}>{p.active ? 'إيقاف' : 'تشغيل'}</ActionButton>
              </li>
            ))}
          </ul>
        )}
      </Card>
      <Card><CardTitle>قالب جديد</CardTitle><PlanForm types={types ?? []} /></Card>
    </div>
  );
}

async function Manual() {
  const supabase = await createClient();
  const { data } = await supabase.from('checklist_templates').select('*, checklist_template_items(*)').order('stage');
  return (
    <div className="flex flex-col gap-4">
      {(data ?? []).map((t) => (
        <Card key={t.id}>
          <CardTitle>{t.name} <Badge className="ms-2 bg-slate-100 text-slate-600">{STAGES[t.stage]}</Badge></CardTitle>
          <div className="flex flex-col gap-2">
            {[...t.checklist_template_items].sort((a, b) => a.sort - b.sort).map((i) => (
              <ActionForm key={i.id} action={updateChecklistItem.bind(null, i.id)} reset={false} className="flex flex-wrap items-center gap-2">
                <Input name="sort" type="number" defaultValue={i.sort} className="w-16" />
                <Input name="text_ar" defaultValue={i.text_ar} className="min-w-60 flex-1" />
                <Select name="role" defaultValue={i.role ?? ''} className="w-36"><option value="">—</option>{ROLES.map((r) => <option key={r} value={r}>{ROLE_LABELS[r]}</option>)}</Select>
                <label className="flex items-center gap-1 text-xs"><input type="checkbox" name="is_required" defaultChecked={i.is_required} />⛔ إجباري</label>
                <label className="flex items-center gap-1 text-xs"><input type="checkbox" name="client_facing" defaultChecked={i.client_facing} />👤 للعميل</label>
                <SubmitButton size="sm" variant="secondary">احفظ</SubmitButton>
                <ActionButton size="sm" variant="ghost" action={deleteChecklistItem.bind(null, i.id)} confirm="تمسح البند؟">✕</ActionButton>
              </ActionForm>
            ))}
            <ActionForm action={addChecklistItem.bind(null, t.id)} className="flex flex-wrap items-center gap-2 border-t border-dashed border-slate-200 pt-2">
              <Input name="text_ar" placeholder="بند جديد" className="min-w-60 flex-1" required />
              <label className="flex items-center gap-1 text-xs"><input type="checkbox" name="is_required" />⛔ إجباري</label>
              <label className="flex items-center gap-1 text-xs"><input type="checkbox" name="client_facing" />👤 للعميل</label>
              <SubmitButton size="sm">أضف</SubmitButton>
            </ActionForm>
          </div>
        </Card>
      ))}
    </div>
  );
}

async function Xp() {
  const supabase = await createClient();
  const { data } = await supabase.from('xp_rules').select('*').order('points', { ascending: false });
  return (
    <Card>
      {(data ?? []).map((r) => (
        <ActionForm key={r.code} action={updateXpRule.bind(null, r.code)} reset={false} className="flex flex-wrap items-center gap-3 border-b border-slate-100 py-2 last:border-0">
          <div className="min-w-60 flex-1 text-sm">{r.name_ar}<div className="text-xs text-slate-400" dir="ltr">{r.code}</div></div>
          <Input name="points" type="number" defaultValue={r.points} className="w-24" />
          <label className="flex items-center gap-1 text-sm"><input type="checkbox" name="active" defaultChecked={r.active} /> شغالة</label>
          <SubmitButton size="sm" variant="secondary">احفظ</SubmitButton>
        </ActionForm>
      ))}
    </Card>
  );
}

async function Challenges() {
  const supabase = await createClient();
  const { data } = await supabase.from('challenges').select('*').order('created_at', { ascending: false });
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardTitle>التحديات</CardTitle>
        {!data?.length ? <Empty>مفيش تحديات</Empty> : (
          <ul className="flex flex-col gap-2 text-sm">
            {data.map((c) => (
              <li key={c.id} className="flex items-start justify-between gap-2 rounded-lg border border-slate-200 p-3">
                <div>
                  <b>{c.name_ar}</b> · {METRICS[c.metric]} {num(c.target)} → +{c.reward_xp} XP
                  <div className="text-xs text-slate-500">{c.starts_on} → {c.ends_on}{c.roles ? ` · ${c.roles.map((r) => ROLE_LABELS[r as keyof typeof ROLE_LABELS]).join('، ')}` : ' · الكل'}</div>
                </div>
                <ActionButton size="sm" action={toggleChallenge.bind(null, c.id, !c.active)}>{c.active ? 'إيقاف' : 'تشغيل'}</ActionButton>
              </li>
            ))}
          </ul>
        )}
      </Card>
      <Card>
        <CardTitle>تحدي جديد</CardTitle>
        <ActionForm action={addChallenge} className="flex flex-col gap-3">
          <Field label="الاسم"><Input name="name_ar" required /></Field>
          <Field label="الوصف"><Input name="description_ar" /></Field>
          <div className="grid grid-cols-3 gap-3">
            <Field label="المقياس"><Select name="metric">{Object.entries(METRICS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</Select></Field>
            <Field label="الهدف"><Input name="target" type="number" min={1} required /></Field>
            <Field label="المكافأة (XP)"><Input name="reward_xp" type="number" min={0} defaultValue={50} /></Field>
            <Field label="من"><Input name="starts_on" type="date" dir="ltr" /></Field>
            <Field label="لحد"><Input name="ends_on" type="date" dir="ltr" /></Field>
          </div>
          <Field label="لمين (فاضي = الكل)"><RolesPicker /></Field>
          <SubmitButton>أضف</SubmitButton>
        </ActionForm>
      </Card>
    </div>
  );
}

async function Team() {
  const supabase = await createClient();
  const { data } = await supabase.from('staff').select('*').order('created_at');
  return (
    <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
      <div className="flex flex-col gap-3">
        {(data ?? []).map((s) => (
          <Card key={s.id}>
            <ActionForm action={updateStaff.bind(null, s.id)} reset={false} className="flex flex-col gap-3">
              <div className="grid grid-cols-2 gap-3">
                <Field label="الاسم"><Input name="name" defaultValue={s.name} /></Field>
                <Field label="الواتساب"><Input name="phone_wa" dir="ltr" defaultValue={s.phone_wa ?? ''} /></Field>
              </div>
              <RolesPicker selected={s.roles} />
              <div className="flex items-center justify-between">
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="active" defaultChecked={s.active} /> نشط</label>
                <SubmitButton size="sm" variant="secondary">احفظ</SubmitButton>
              </div>
            </ActionForm>
          </Card>
        ))}
      </div>
      <div className="flex flex-col gap-4">
        <Card>
          <CardTitle>دعوة حساب جديد</CardTitle>
          <ActionForm action={inviteStaff} className="flex flex-col gap-3">
            <Field label="الإيميل"><Input name="email" type="email" dir="ltr" required /></Field>
            <Field label="الاسم"><Input name="name" required /></Field>
            <Field label="الواتساب"><Input name="phone_wa" dir="ltr" placeholder="20XXXXXXXXXX" /></Field>
            <RolesPicker />
            <SubmitButton>ابعت الدعوة</SubmitButton>
          </ActionForm>
          <p className="mt-2 text-xs text-slate-500">بيوصله لينك دخول على الإيميل. لازم يكون التسجيل بالإيميل شغال في Supabase Auth.</p>
        </Card>
        <Card>
          <CardTitle>ضيف حساب موجود</CardTitle>
          <ActionForm action={addExistingStaff} className="flex flex-col gap-3">
            <Field label="الإيميل"><Input name="email" type="email" dir="ltr" required /></Field>
            <Field label="الاسم"><Input name="name" required /></Field>
            <Field label="الواتساب"><Input name="phone_wa" dir="ltr" /></Field>
            <RolesPicker />
            <SubmitButton variant="secondary">أضف</SubmitButton>
          </ActionForm>
        </Card>
      </div>
    </div>
  );
}

async function Bonus() {
  const supabase = await createClient();
  const { data } = await supabase.from('bonus_payouts').select('*, staff!bonus_payouts_staff_id_fkey(name)').order('month', { ascending: false }).limit(100);
  const STATUS: Record<string, string> = { pending: 'مستني', approved: 'اتعتمد', rejected: 'اترفض', paid: 'اتصرف' };
  return (
    <div className="flex flex-col gap-4">
      <Card>
        <ActionForm action={prepareBonuses} className="flex flex-wrap items-end gap-3">
          <Field label="الشهر"><Input name="month" type="month" dir="ltr" /></Field>
          <SubmitButton>احسب البونص</SubmitButton>
          <span className="text-xs text-slate-500">فاضي = الشهر اللي فات. الـ job اليومي بيحسبه لوحده يوم 1.</span>
        </ActionForm>
      </Card>
      {!data?.length ? <Empty>لسه مفيش بونص</Empty> : (
        <Table>
          <thead><tr><Th>الشهر</Th><Th>الموظف</Th><Th>XP</Th><Th>المبلغ</Th><Th>الحالة</Th><Th /></tr></thead>
          <tbody>
            {data.map((b) => (
              <tr key={b.id}>
                <Td>{b.month.slice(0, 7)}</Td><Td>{b.staff?.name}</Td><Td>{num(b.xp)}</Td><Td>{money(b.amount)}</Td>
                <Td><Badge>{STATUS[b.status]}</Badge></Td>
                <Td className="flex gap-1">
                  {b.status === 'pending' && <>
                    <ActionButton size="sm" variant="success" action={setBonusStatus.bind(null, b.id, 'approved')}>اعتماد</ActionButton>
                    <ActionButton size="sm" variant="ghost" action={setBonusStatus.bind(null, b.id, 'rejected')}>رفض</ActionButton>
                  </>}
                  {b.status === 'approved' && <ActionButton size="sm" variant="primary" action={setBonusStatus.bind(null, b.id, 'paid')}>اتصرف</ActionButton>}
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  );
}

async function Studios() {
  const supabase = await createClient();
  const [{ data: studios }, { data: meters }] = await Promise.all([
    supabase.from('studios').select('*').order('name'),
    supabase.from('utility_meters').select('*, studios(name)').order('name'),
  ]);
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardTitle>الاستوديوهات</CardTitle>
        <ul className="mb-3 flex flex-col gap-2 text-sm">
          {(studios ?? []).map((s) => (
            <li key={s.id} className="flex items-center justify-between">
              <span className={s.active ? '' : 'text-slate-400 line-through'}>{s.name}</span>
              <ActionButton size="sm" action={toggleStudio.bind(null, s.id, !s.active)}>{s.active ? 'إيقاف' : 'تشغيل'}</ActionButton>
            </li>
          ))}
        </ul>
        <ActionForm action={addStudio} className="flex gap-2"><Input name="name" placeholder="استوديو جديد" required /><SubmitButton>أضف</SubmitButton></ActionForm>
      </Card>
      <Card>
        <CardTitle>العدادات</CardTitle>
        <ul className="mb-3 flex flex-col gap-2 text-sm">
          {(meters ?? []).map((m) => <li key={m.id}>{m.name} <span className="text-xs text-slate-500">{m.studios?.name}</span></li>)}
        </ul>
        <ActionForm action={addMeter} className="flex gap-2">
          <Input name="name" placeholder="عداد جديد" required />
          <Select name="studio_id" className="w-40"><option value="">—</option>{(studios ?? []).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</Select>
          <SubmitButton>أضف</SubmitButton>
        </ActionForm>
      </Card>
    </div>
  );
}
