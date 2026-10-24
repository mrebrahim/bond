'use client';

export default function Error({ error, reset }: { error: Error; reset: () => void }) {
  return (
    <div className="mx-auto mt-16 max-w-md rounded-xl border border-red-200 bg-white p-6 text-center">
      <div className="mb-2 text-3xl">⚠️</div>
      <h2 className="mb-2 font-bold">حصلت مشكلة</h2>
      <p className="mb-4 text-sm text-slate-600">{error.message}</p>
      <button onClick={reset} className="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white">جرّب تاني</button>
    </div>
  );
}
