import { NextResponse, type NextRequest } from 'next/server';
import { createClient } from '@/lib/supabase/server';

// لينك التقييم القصير اللي بيوصل للعميل على الواتساب: /f/<short_code> → فورم جوجل متملي
export async function GET(req: NextRequest, { params }: { params: Promise<{ code: string }> }) {
  const { code } = await params;
  if (!/^[0-9a-f]{8}$/.test(code)) return new NextResponse('اللينك ده مش صحيح', { status: 404 });
  const supabase = await createClient();
  const { data } = await supabase.rpc('feedback_redirect', { p_code: code });
  if (!data) return new NextResponse('اللينك ده مش صحيح', { status: 404 });
  return NextResponse.redirect(data, 302);
}
