// جروبات الواتساب للعملاء — بيتنادى من السيستم بـ JWT الموظف
//   { action: 'create', client_id }        → يعمل جروب (العميل + الـ AM + تيم ليدر الميديا) ويحفظ اللينك
//   { action: 'link', client_id, link }    → يربط جروب موجود من لينك الدعوة
//   { action: 'status' } / { action: 'setup' } (أدمن) → حالة الرقم / ظبط استقبال الردود
import { createClient } from 'npm:@supabase/supabase-js@2.45.4';

const EVO_URL = (Deno.env.get('EVO_URL') ?? 'https://evolutoin-api-evolution-api.4tqpgc.easypanel.host').replace(/\/$/, '');
const EVO_KEY = Deno.env.get('EVO_API_KEY');
const INSTANCE = Deno.env.get('EVO_INSTANCE') ?? 'dse-clients';
const SUPABASE_URL = Deno.env.get('SUPABASE_URL')!;
const admin = createClient(SUPABASE_URL, Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!, { auth: { persistSession: false } });

const fail = (error: string, status = 400) => Response.json({ error }, { status });

async function evo(method: string, path: string, body?: unknown) {
  const res = await fetch(`${EVO_URL}${path}`, {
    method, headers: { apikey: EVO_KEY!, 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let json: Record<string, unknown> = {};
  try { json = JSON.parse(text); } catch { /* */ }
  if (!res.ok) throw new Error(`Evolution ${res.status}: ${text.slice(0, 300)}`);
  return json;
}

async function markCreateGroupDone(clientId: string, uid: string | null) {
  const { data: item } = await admin.from('checklist_template_items').select('id').eq('action', 'create_group').maybeSingle();
  // الـ unique index جزئي (client_id is not null) فـ upsert مش بيشتغل — insert ولو موجود بيتجاهل الـ duplicate
  if (item) await admin.from('checklist_runs').insert({ item_id: item.id, client_id: clientId, done_by: uid, note: 'الجروب اتعمل من السيستم' });
}

Deno.serve(async (req) => {
  if (!EVO_KEY) return fail('EVO_API_KEY مش متحط في الـ secrets', 500);
  const body = await req.json().catch(() => ({}));
  let uid: string | null = null, isAdmin = false, canManage = false;
  const sysToken = req.headers.get('x-dse-token');
  if (sysToken) {
    // نداء من جوه السيستم (SQL / pg_net) بالكود السري اللي في settings.wa_webhook_token
    const { data: tok } = await admin.from('settings').select('value').eq('key', 'wa_webhook_token').maybeSingle();
    if (sysToken !== String(tok?.value ?? '')) return fail('غير مصرح', 401);
    isAdmin = canManage = true;
  } else {
    // صلاحيات الموظف من الـ JWT بتاعه
    const user = createClient(SUPABASE_URL, Deno.env.get('SUPABASE_ANON_KEY')!, {
      global: { headers: { Authorization: req.headers.get('Authorization') ?? '' } }, auth: { persistSession: false },
    });
    const { data: claims } = await user.auth.getUser();
    uid = claims?.user?.id ?? null;
    if (!uid) return fail('غير مصرح', 401);
    isAdmin = (await user.rpc('has_role', { p_roles: [] })).data === true;
    canManage = (await user.rpc('has_role', { p_roles: ['sales', 'support', 'account_manager'] })).data === true;
  }

  try {
    if (body.action === 'instances') {
      if (!isAdmin) return fail('للأدمن بس', 403);
      const list = await evo('GET', '/instance/fetchInstances') as unknown as Record<string, unknown>[];
      return Response.json({ using: INSTANCE, instances: (Array.isArray(list) ? list : []).map((i) => {
        const x = (i.instance ?? i) as Record<string, unknown>;
        return { name: x.instanceName ?? x.name, state: x.connectionStatus ?? x.status ?? x.state, number: x.ownerJid ?? x.owner ?? x.number };
      }) });
    }
    if (body.action === 'status') {
      const s = await evo('GET', `/instance/connectionState/${INSTANCE}`);
      return Response.json({ instance: INSTANCE, state: (s.instance as { state?: string })?.state ?? s.state ?? 'unknown' });
    }
    if (body.action === 'webhook') {
      if (!isAdmin) return fail('للأدمن بس', 403);
      return Response.json({ instance: INSTANCE, webhook: await evo('GET', `/webhook/find/${INSTANCE}`), settings: await evo('GET', `/settings/find/${INSTANCE}`) });
    }
    if (body.action === 'messages') {
      if (!isAdmin) return fail('للأدمن بس', 403);
      const r = await evo('POST', `/chat/findMessages/${INSTANCE}`, { where: { key: { remoteJid: body.jid } }, limit: 10 });
      return Response.json(r);
    }
    if (body.action === 'setup') {
      if (!isAdmin) return fail('للأدمن بس', 403);
      const { data: tok } = await admin.from('settings').select('value').eq('key', 'wa_webhook_token').maybeSingle();
      const url = `${SUPABASE_URL}/functions/v1/wa-inbound?token=${tok?.value}`;
      await evo('POST', `/webhook/set/${INSTANCE}`, { webhook: { enabled: true, url, byEvents: false, base64: false, events: ['MESSAGES_UPSERT'] } });
      // لازم رسايل الجروبات متتجاهلش — الردود (قرأت / 1 / 2) بتيجي من جروب العميل
      const cur = await evo('GET', `/settings/find/${INSTANCE}`);
      if (cur.groupsIgnore !== false) {
        const keep = ['rejectCall', 'msgCall', 'alwaysOnline', 'readMessages', 'readStatus', 'syncFullHistory'];
        await evo('POST', `/settings/set/${INSTANCE}`, { ...Object.fromEntries(keep.map((k) => [k, cur[k] ?? (k === 'msgCall' ? '' : false)])), groupsIgnore: false });
      }
      return Response.json({ ok: true, message: 'استقبال الردود اتظبط' });
    }
    if (!canManage) return fail('العملية دي مش متاحة لدورك', 403);
    const { data: c } = await admin.from('clients').select('id, name, phone_wa, account_manager_id').eq('id', body.client_id).maybeSingle();
    if (!c) return fail('العميل مش موجود', 404);

    if (body.action === 'link') {
      const code = String(body.link ?? '').trim().split('/').pop()?.split('?')[0] ?? '';
      if (!/^[A-Za-z0-9]{10,}$/.test(code)) return fail('ده مش لينك جروب واتساب صحيح (https://chat.whatsapp.com/...)');
      const info = await evo('GET', `/group/inviteInfo/${INSTANCE}?inviteCode=${code}`);
      const jid = String(info.id ?? '');
      if (!jid.endsWith('@g.us')) return fail('مقدرتش أوصل للجروب — اتأكد إن رقم السيستم عضو فيه');
      await admin.from('clients').update({ wa_group_jid: jid, wa_group_link: `https://chat.whatsapp.com/${code}` }).eq('id', c.id);
      await markCreateGroupDone(c.id, uid);
      return Response.json({ ok: true, message: 'الجروب اتربط', jid });
    }

    if (body.action === 'create') {
      const { data: team } = await admin.from('staff').select('phone_wa, roles, id').eq('active', true);
      const phones = new Set<string>([c.phone_wa]);
      for (const s of team ?? []) {
        if (s.phone_wa && (s.id === c.account_manager_id || (s.roles as string[]).includes('media_lead'))) phones.add(s.phone_wa);
      }
      const g = await evo('POST', `/group/create/${INSTANCE}`, {
        subject: `${c.name} × Digital Solution`.slice(0, 100),
        description: 'جروب المتابعة مع Digital Solution — مواعيد التصوير والمحتوى والتحديثات',
        participants: [...phones],
      });
      const jid = String(g.id ?? g.groupJid ?? (g.group as { id?: string })?.id ?? '');
      if (!jid.endsWith('@g.us')) throw new Error(`الجروب اتعمل بس مقدرتش أقرا الـ id: ${JSON.stringify(g).slice(0, 200)}`);
      let link: string | null = null;
      try {
        const inv = await evo('GET', `/group/inviteCode/${INSTANCE}?groupJid=${encodeURIComponent(jid)}`);
        link = String(inv.inviteUrl ?? (inv.inviteCode ? `https://chat.whatsapp.com/${inv.inviteCode}` : '')) || null;
      } catch { /* اللينك مش ضروري */ }
      await admin.from('clients').update({ wa_group_jid: jid, wa_group_link: link }).eq('id', c.id);
      await markCreateGroupDone(c.id, uid);
      return Response.json({ ok: true, message: 'الجروب اتعمل ✅', jid, link });
    }
    return fail('action غير معروف');
  } catch (e) {
    return fail(String((e as Error)?.message ?? e), 502);
  }
});
