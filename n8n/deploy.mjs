// رفع/تحديث الـ workflows على n8n من الـ API
//
// Windows CMD:
//   set N8N_URL=https://comp.digitalsolutionegy.com
//   set N8N_API_KEY=...            (n8n → Settings → n8n API)
//   set APP_URL=https://bond-chi-tawny.vercel.app   (اختياري — ده الافتراضي)
//   set FROM_EMAIL=notifications@digitalsolutionegy.com
//   set EVO_URL=https://evolutoin-api-evolution-api.4tqpgc.easypanel.host
//   set EVO_API_KEY=...            (اختياري: بيعمل credential الـ Evolution لو مش موجود)
//   set CRED_PG=<id>  set CRED_SMTP=<id>  set CRED_SHEETS=<id>   (اختياري: id الـ credential من الرابط في n8n)
//   node n8n\deploy.mjs            (أضف --activate علشان يشغّلهم)
//
// بيدوّر على الـ workflow بالاسم: لو موجود بيعمله update، لو لأ بيعمله create.
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = dirname(fileURLToPath(import.meta.url));
const env = process.env;
const base = (env.N8N_URL || '').replace(/\/$/, '');
if (!base || !env.N8N_API_KEY) {
  console.error('لازم N8N_URL و N8N_API_KEY');
  process.exit(1);
}
const activate = process.argv.includes('--activate');

async function api(method, path, body) {
  const res = await fetch(`${base}/api/v1${path}`, {
    method,
    headers: { 'X-N8N-API-KEY': env.N8N_API_KEY, 'Content-Type': 'application/json', accept: 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${path} → ${res.status}: ${text.slice(0, 400)}`);
  return text ? JSON.parse(text) : {};
}

// قيم الـ Config node
const CONFIG = {
  evoUrl: env.EVO_URL,
  instanceClients: env.EVO_INSTANCE_CLIENTS,
  instanceTeam: env.EVO_INSTANCE_TEAM,
  fromEmail: env.FROM_EMAIL,
  appUrl: env.APP_URL,
};
function patchConfig(code) {
  let out = code;
  for (const [k, v] of Object.entries(CONFIG)) {
    if (!v) continue;
    out = out.replace(new RegExp(`(\\b${k}: )'[^']*'`), `$1'${v.replace(/'/g, "\\'")}'`);
  }
  return out;
}

// الـ credentials: id حقيقي لو اتبعت، غير كده بنشيله (تختاره من n8n بعدين)
const CRED_IDS = { postgres: env.CRED_PG, smtp: env.CRED_SMTP, googleSheetsTriggerOAuth2Api: env.CRED_SHEETS, httpHeaderAuth: env.CRED_EVO };

async function ensureEvolutionCredential() {
  if (CRED_IDS.httpHeaderAuth || !env.EVO_API_KEY) return;
  const c = await api('POST', '/credentials', {
    name: 'Evolution API key (header: apikey)', type: 'httpHeaderAuth', data: { name: 'apikey', value: env.EVO_API_KEY },
  });
  CRED_IDS.httpHeaderAuth = c.id;
  console.log('✔ credential Evolution اتعمل', c.id);
}

function prepare(wf) {
  const nodes = wf.nodes.map((n) => {
    const node = structuredClone(n);
    if (node.name === 'Config' && node.parameters.jsCode) node.parameters.jsCode = patchConfig(node.parameters.jsCode);
    if (node.credentials) {
      const creds = {};
      for (const [type, ref] of Object.entries(node.credentials)) {
        if (CRED_IDS[type]) creds[type] = { id: CRED_IDS[type], name: ref.name };
      }
      if (Object.keys(creds).length) node.credentials = creds; else delete node.credentials;
    }
    return node;
  });
  return { name: wf.name, nodes, connections: wf.connections, settings: { executionOrder: 'v1', timezone: 'Africa/Cairo' } };
}

async function existingByName() {
  const map = new Map();
  let cursor;
  do {
    const page = await api('GET', `/workflows?limit=100${cursor ? `&cursor=${cursor}` : ''}`);
    for (const w of page.data ?? []) map.set(w.name, w.id);
    cursor = page.nextCursor;
  } while (cursor);
  return map;
}

await ensureEvolutionCredential();
const existing = await existingByName();
for (const file of readdirSync(dir).filter((f) => /^\d\d_.*\.json$/.test(f)).sort()) {
  const wf = prepare(JSON.parse(readFileSync(join(dir, file), 'utf8')));
  const id = existing.get(wf.name);
  const saved = id ? await api('PUT', `/workflows/${id}`, wf) : await api('POST', '/workflows', wf);
  console.log(`${id ? '↻ اتحدّث' : '✚ اتعمل'}  ${wf.name}  (${saved.id})`);
  if (activate) {
    try {
      await api('POST', `/workflows/${saved.id}/activate`);
      console.log('   ▶ شغال');
    } catch (e) {
      console.log('   ⚠️ ماتشغلش — غالبًا ناقص credential:', e.message.slice(0, 160));
    }
  }
}
const missing = Object.entries(CRED_IDS).filter(([, v]) => !v).map(([k]) => k);
if (missing.length) console.log(`\nافتح الـ nodes اللي عليها ⚠️ في n8n واختار الـ credential (${missing.join(', ')})`);
