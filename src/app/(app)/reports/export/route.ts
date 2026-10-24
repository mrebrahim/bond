import { NextResponse, type NextRequest } from 'next/server';
import { createClient } from '@/lib/supabase/server';

const ALLOWED = ['v_mrr_monthly', 'v_retention_monthly', 'v_utilization_monthly', 'v_feedback_by_staff', 'v_client_overview', 'v_complaints', 'v_leaderboard'] as const;
type V = (typeof ALLOWED)[number];

function cell(v: unknown): string {
  if (v === null || v === undefined) return '';
  const s = typeof v === 'object' ? JSON.stringify(v) : String(v);
  return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}

// Export CSV بصلاحيات المستخدم (الـ RLS بيحكم)
export async function GET(req: NextRequest) {
  const view = req.nextUrl.searchParams.get('view') as V;
  if (!ALLOWED.includes(view)) return NextResponse.json({ error: 'view مش مسموح' }, { status: 400 });
  const supabase = await createClient();
  const { data, error } = await supabase.from(view).select('*').limit(10000);
  if (error) return NextResponse.json({ error: error.message }, { status: 400 });
  const rows = (data ?? []) as Record<string, unknown>[];
  const cols = rows.length ? Object.keys(rows[0]) : [];
  const csv = '﻿' + [cols.join(','), ...rows.map((r) => cols.map((c) => cell(r[c])).join(','))].join('\n');
  return new NextResponse(csv, {
    headers: { 'Content-Type': 'text/csv; charset=utf-8', 'Content-Disposition': `attachment; filename="${view}.csv"` },
  });
}
