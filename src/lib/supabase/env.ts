// الـ URL والـ publishable key معمولين للمتصفح أصلًا (مش أسرار) — الحماية في الـ RLS.
// متغيرات Vercel لو موجودة بتغلب، ولو مش موجودة بنستخدم مشروع bond.
export const SUPABASE_URL =
  process.env.NEXT_PUBLIC_SUPABASE_URL || 'https://izpvbgzwiwyaoctdroxr.supabase.co';
export const SUPABASE_KEY =
  process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY || 'sb_publishable_6HSbmXwcBE91g4Jmj2FsqA_lAsVI-wW';
