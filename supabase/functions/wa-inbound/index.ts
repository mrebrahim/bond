// ردود العملاء من Evolution (webhook MESSAGES_UPSERT) — "1"/"2" للجلسة و"قرأت" للقواعد
// العنوان: /functions/v1/wa-inbound?token=<settings.wa_webhook_token> (wa-group action=setup بيظبطه)
import { createClient } from 'npm:@supabase/supabase-js@2.45.4';

const EVO_URL = (Deno.env.get('EVO_URL') ?? 'https://evolutoin-api-evolution-api.4tqpgc.easypanel.host').replace(/\/$/, '');
const EVO_KEY = Deno.env.get('EVO_API_KEY');
const supabase = createClient(Deno.env.get('SUPABASE_URL')!, Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!, { auth: { persistSession: false } });

Deno.serve(async (req) => {
  const token = new URL(req.url).searchParams.get('token');
  const { data: tok } = await supabase.from('settings').select('value').eq('key', 'wa_webhook_token').maybeSingle();
  if (!token || token !== String(tok?.value ?? '')) return new Response('forbidden', { status: 403 });

  const b = await req.json().catch(() => ({}));
  const data = b.data ?? {};
  const event = String(b.event ?? '').toLowerCase().replace('_', '.');
  // الرسالة ممكن تيجي ملفوفة (رسايل مختفية / view once / رد على رسالة)
  const m = data.message?.ephemeralMessage?.message ?? data.message?.viewOnceMessage?.message ?? data.message ?? {};
  const chat: string = data.key?.remoteJid ?? '';
  const sender: string = String(data.key?.participant ?? data.participant ?? chat).split('@')[0];
  const text: string = m.conversation ?? m.extendedTextMessage?.text ?? m.imageMessage?.caption ?? '';
  console.log('wa-inbound', JSON.stringify({ event, fromMe: data.key?.fromMe, chat, sender, text: text.slice(0, 60), keys: Object.keys(m) }));
  if (event !== 'messages.upsert' || data.key?.fromMe) return Response.json({ ok: true, ignored: 'event' });
  if (!chat || !text) return Response.json({ ok: true, ignored: 'no_text' });

  const { data: r, error } = await supabase.rpc('handle_wa_reply', { p_chat: chat, p_sender: sender, p_text: text });
  console.log('wa-inbound result', JSON.stringify(r ?? error));
  if (error) return Response.json({ error: error.message }, { status: 500 });
  if (r?.reply && EVO_KEY) {
    await fetch(`${EVO_URL}/message/sendText/${b.instance ?? Deno.env.get('EVO_INSTANCE') ?? 'dse-clients'}`, {
      method: 'POST', headers: { apikey: EVO_KEY, 'Content-Type': 'application/json' },
      body: JSON.stringify({ number: chat, text: r.reply }),
    }).catch(() => undefined);
  }
  return Response.json({ ok: true, result: r });
});
