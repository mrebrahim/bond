'use client';

import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export function MrrChart({ data }: { data: { month: string; mrr: number; clients: number }[] }) {
  return (
    <div className="h-64 w-full" dir="ltr">
      <ResponsiveContainer>
        <LineChart data={data}>
          <CartesianGrid strokeDasharray="3 3" stroke="#eef2f7" />
          <XAxis dataKey="month" tick={{ fontSize: 11 }} />
          <YAxis yAxisId="l" tick={{ fontSize: 11 }} width={60} />
          <YAxis yAxisId="r" orientation="right" tick={{ fontSize: 11 }} width={30} />
          <Tooltip />
          <Legend />
          <Line yAxisId="l" type="monotone" dataKey="mrr" name="MRR" stroke="#1d5cf0" strokeWidth={2} />
          <Line yAxisId="r" type="monotone" dataKey="clients" name="عملاء" stroke="#10b981" strokeWidth={2} />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

export function UtilizationChart({ data }: { data: { label: string; used: number; forfeited: number }[] }) {
  return (
    <div className="h-64 w-full" dir="ltr">
      <ResponsiveContainer>
        <BarChart data={data}>
          <CartesianGrid strokeDasharray="3 3" stroke="#eef2f7" />
          <XAxis dataKey="label" tick={{ fontSize: 11 }} />
          <YAxis tick={{ fontSize: 11 }} width={40} />
          <Tooltip />
          <Legend />
          <Bar dataKey="used" name="اتستخدم" stackId="a" fill="#1d5cf0" />
          <Bar dataKey="forfeited" name="ضاع" stackId="a" fill="#ef4444" />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
