import { Card, Field, Input } from '@/components/ui';
import { ActionForm, SubmitButton } from '@/components/ui/client';
import { sendMagicLink, signInWithPassword } from './actions';

export default function LoginPage() {
  return (
    <main className="flex min-h-screen items-center justify-center p-4">
      <Card className="w-full max-w-sm p-6">
        <div className="mb-6 text-center">
          <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-2xl text-white">◎</div>
          <h1 className="text-xl font-bold">Digital Solution</h1>
          <p className="text-sm text-slate-500">العملاء والباقات والاستوديو</p>
        </div>
        <ActionForm action={signInWithPassword} reset={false} className="flex flex-col gap-3">
          <Field label="الإيميل">
            <Input name="email" type="email" dir="ltr" autoComplete="email" required />
          </Field>
          <Field label="الباسورد">
            <Input name="password" type="password" dir="ltr" autoComplete="current-password" required />
          </Field>
          <SubmitButton className="mt-1 w-full">دخول</SubmitButton>
        </ActionForm>
        <div className="my-5 flex items-center gap-3 text-xs text-slate-400">
          <span className="h-px flex-1 bg-slate-200" />أو<span className="h-px flex-1 bg-slate-200" />
        </div>
        <ActionForm action={sendMagicLink} className="flex flex-col gap-3">
          <Field label="ابعتلي لينك دخول على الإيميل">
            <Input name="email" type="email" dir="ltr" autoComplete="email" required />
          </Field>
          <SubmitButton variant="secondary" className="w-full">ابعت اللينك</SubmitButton>
        </ActionForm>
      </Card>
    </main>
  );
}
