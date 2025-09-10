import AppLayout from '@/layouts/app-layout';
import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Echo from '@/lib/echo';

interface TransactionLine {
  id: number;
  transaction_id: number;
  process_date: string | null;
  value_date: string | null;
  description: string | null;
  place: string | null;
  branch_code: string | null;
  operation_number: string | null;
  time: string | null;
  origin: string | null;
  transaction_type: string | null;
  debit: string | null;
  credit: string | null;
  balance: string | null;
  created_at: string;
}

export default function TransactionsIndex() {
  const [lines, setLines] = useState<TransactionLine[]>([]);

  // 🚀 Cargar datos iniciales
  useEffect(() => {
    axios.get('/transactions/fetch-lines').then(res => {
      setLines(res.data);
    });
  }, []);

  // 📡 Suscripción a WebSocket
  useEffect(() => {
    const channel = Echo.channel('transactions');

    channel.listen('.line.created', (e: any) => {
      console.log('Nuevo transaction line:', e.line);
      setLines(prev => [e.line, ...prev]); // prepend
    });

    return () => {
      Echo.leave('transactions');
    };
  }, []);

  return (
    <AppLayout breadcrumbs={[{ title: 'Transactions', href: '/transactions' }]}>
      <Head title="Transactions" />

      <div className="p-6">
        <h1 className="text-2xl font-bold mb-4">Transaction Lines</h1>

        <table className="min-w-full border text-sm">
          <thead className="bg-gray-200">
            <tr>
              <th className="border px-2 py-1">#</th>
              <th className="border px-2 py-1">Process Date</th>
              <th className="border px-2 py-1">Value Date</th>
              <th className="border px-2 py-1">Description</th>
              <th className="border px-2 py-1">Place</th>
              <th className="border px-2 py-1">Branch</th>
              <th className="border px-2 py-1">Op. #</th>
              <th className="border px-2 py-1">Time</th>
              <th className="border px-2 py-1">Origin</th>
              <th className="border px-2 py-1">Type</th>
              <th className="border px-2 py-1">Debit</th>
              <th className="border px-2 py-1">Credit</th>
              <th className="border px-2 py-1">Balance</th>
            </tr>
          </thead>
          <tbody>
            {lines.map((line, idx) => (
              <tr key={line.id}>
                <td className="border px-2 py-1">{idx + 1}</td>
                <td className="border px-2 py-1">{line.process_date}</td>
                <td className="border px-2 py-1">{line.value_date}</td>
                <td className="border px-2 py-1">{line.description}</td>
                <td className="border px-2 py-1">{line.place}</td>
                <td className="border px-2 py-1">{line.branch_code}</td>
                <td className="border px-2 py-1">{line.operation_number}</td>
                <td className="border px-2 py-1">{line.time}</td>
                <td className="border px-2 py-1">{line.origin}</td>
                <td className="border px-2 py-1">{line.transaction_type}</td>
                <td className="border px-2 py-1">{line.debit}</td>
                <td className="border px-2 py-1">{line.credit}</td>
                <td className="border px-2 py-1">{line.balance}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AppLayout>
  );
}
