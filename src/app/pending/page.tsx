import { Card } from '@/components/ui';
import { signOut } from '@/app/(auth)/login/actions';

export default function PendingPage() {
  return (
    <main className="flex min-h-screen items-center justify-center p-4">
      <Card className="max-w-sm p-6 text-center">
        <div className="mb-2 text-3xl">⏳</div>
        <h1 className="mb-2 text-lg font-bold">حسابك لسه متفعلش</h1>
        <p className="mb-4 text-sm text-slate-500">كلّم إبراهيم يضيفك للفريق ويحددلك دورك.</p>
        <form action={signOut}>
          <button className="text-sm text-brand-700 underline">خروج</button>
        </form>
      </Card>
    </main>
  );
}
