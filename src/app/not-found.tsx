import Link from 'next/link';

export default function NotFound() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-3 p-4 text-center">
      <div className="text-4xl">🔍</div>
      <h1 className="text-lg font-bold">الصفحة دي مش موجودة</h1>
      <Link href="/" className="text-brand-700 underline">ارجع للرئيسية</Link>
    </main>
  );
}
