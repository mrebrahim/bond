<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
/* Mobile-first · RTL-safe (logical properties بس) · من غير مكتبات تقيلة */
.dv{max-width:980px;margin-inline:auto}
.dv .dv-nav{display:flex;gap:6px;overflow-x:auto;padding-block-end:8px;margin-block-end:10px}
.dv .dv-nav a{flex:0 0 auto;padding:8px 14px;border-radius:999px;background:#fff;border:1px solid #e2e8f0;color:#334155;font-size:13px;white-space:nowrap}
.dv .dv-nav a.on{background:#0f172a;color:#fff;border-color:#0f172a}
.dv .dv-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;margin-block-end:10px}
.dv .dv-card h4,.dv .dv-card h5{margin-block:0 8px}
.dv .dv-row{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.dv .dv-muted{color:#64748b;font-size:12px}
.dv .dv-bar{height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;margin-block:6px}
.dv .dv-bar>div{height:100%;border-radius:99px}
.dv .dv-chip{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;color:#fff;white-space:nowrap}
.dv .dv-tag{display:inline-block;padding:1px 8px;border-radius:6px;font-size:11px;background:#f1f5f9;color:#334155;margin-inline-end:4px}
.dv .dv-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:48px;padding:0 16px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;font-weight:600;font-size:14px;cursor:pointer;text-decoration:none}
.dv .dv-btn:hover{text-decoration:none;background:#f8fafc;color:#0f172a}
.dv .dv-btn-primary{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
.dv .dv-btn-primary:hover{background:#1e40af;color:#fff}
.dv .dv-btn-wa{background:#16a34a;border-color:#16a34a;color:#fff}
.dv .dv-btn-wa:hover{background:#15803d;color:#fff}
.dv .dv-btn-sm{min-height:36px;padding:0 12px;font-size:13px}
.dv .dv-btn[disabled]{opacity:.45;pointer-events:none}
.dv .dv-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.dv .dv-out{min-height:52px;border-radius:10px;border:2px solid #e2e8f0;background:#fff;font-weight:600;font-size:13px;line-height:1.2;padding:4px;cursor:pointer}
.dv .dv-out.on{border-color:#1d4ed8;background:#eff6ff;color:#1d4ed8}
.dv .dv-out.lost.on{border-color:#dc2626;background:#fef2f2;color:#dc2626}
.dv .dv-in{width:100%;min-height:44px;border:1px solid #cbd5e1;border-radius:10px;padding:8px 10px;font-size:15px}
.dv .dv-quick{display:flex;gap:6px;flex-wrap:wrap;margin-block:6px}
.dv .dv-quick button{min-height:40px;border-radius:999px;border:1px solid #cbd5e1;background:#fff;padding:0 12px;font-size:13px}
.dv .dv-ring{--p:0;width:92px;height:92px;border-radius:50%;background:conic-gradient(#16a34a calc(var(--p)*1%),#e2e8f0 0);display:grid;place-items:center;flex:0 0 auto}
.dv .dv-ring>div{width:72px;height:72px;border-radius:50%;background:#fff;display:grid;place-items:center;text-align:center;font-weight:700;font-size:18px;line-height:1.1}
.dv .dv-ring small{display:block;font-size:10px;color:#64748b;font-weight:400}
.dv .dv-kpis{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
@media(min-width:768px){.dv .dv-kpis{grid-template-columns:repeat(4,1fr)}}
.dv .dv-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px}
.dv .dv-kpi b{display:block;font-size:22px}
.dv .dv-doc{border-inline-start:4px solid var(--c,#94a3b8)}
.dv .dv-doc.cool-orange{background:#fff7ed;border-color:#f97316}
.dv .dv-doc.cool-red{background:#fef2f2;border-color:#dc2626}
.dv .dv-toast{position:fixed;inset-inline:12px;bottom:16px;z-index:9999;max-width:520px;margin-inline:auto;background:#0f172a;color:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 10px 30px rgba(0,0,0,.25);animation:dvin .3s ease}
.dv .dv-toast b{color:#4ade80}
@keyframes dvin{from{transform:translateY(20px);opacity:0}to{transform:none;opacity:1}}
.dv .dv-celebrate{position:fixed;inset:0;z-index:9998;background:rgba(15,23,42,.55);display:grid;place-items:center}
.dv .dv-celebrate>div{background:#fff;border-radius:18px;padding:26px;text-align:center;max-width:320px}
.dv .dv-confetti{position:fixed;top:-10px;width:8px;height:14px;z-index:9999;animation:dvfall 2.4s linear forwards}
@keyframes dvfall{to{transform:translateY(105vh) rotate(540deg)}}
.dv table.dv-t{width:100%;border-collapse:collapse;font-size:13px}
.dv table.dv-t th,.dv table.dv-t td{padding:6px 8px;border-block-end:1px solid #f1f5f9;text-align:start}
.dv .dv-scroll{overflow-x:auto}
</style>
