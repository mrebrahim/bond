'use client';

import { Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export function BalanceChart({ data }: { data: { label: string; balance: number }[] }) {
  return (
    <div className="h-56 w-full" dir="ltr">
      <ResponsiveContainer>
        <LineChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
          <XAxis dataKey="label" tick={{ fontSize: 11 }} />
          <YAxis tick={{ fontSize: 11 }} width={48} />
          <Tooltip formatter={(v) => [`${v} ج.م`, 'الرصيد']} />
          <Line type="monotone" dataKey="balance" stroke="#1d5cf0" strokeWidth={2} dot={{ r: 3 }} />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
