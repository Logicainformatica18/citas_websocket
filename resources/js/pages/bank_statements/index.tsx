import AppLayout from '@/layouts/app-layout';
import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';

interface BankStatement {
  id: number;
  date_str: string;
  union_name: string;
  code: string;
  project: string;
  stage: string;
  lot: string;
  amount: number;
  operation_number: string;
  operation_time: string;
  paid_by: string;
  account_number: string;
  file_name: string;
}

interface PageProps {
  statements: {
    data: BankStatement[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
  };
  filters: {
    operation_number?: string;
  };
}

export default function BankStatements() {
  const { statements, filters } = usePage<PageProps>().props;
  const [operationNumber, setOperationNumber] = useState(filters.operation_number || '');

  // 🔹 Función de paginación abreviada
 const getPageNumbers = () => {
  const total = statements.last_page;
  const current = statements.current_page;
  const delta = 2;
  const pages: (number | string)[] = [];

  const range = [
    1,
    ...Array.from({ length: 2 * delta + 1 }, (_, i) => current - delta + i).filter(
      (n) => n > 1 && n < total
    ),
    total,
  ];

  let last = 0;
  for (const page of [...new Set(range)]) { // 🔹 evitar duplicados
    if (typeof page === 'number') {
      if (page - last > 1) {
        pages.push('…');
      }
      pages.push(page);
      last = page;
    }
  }
  return pages;
};


  // 🔹 Buscar con Inertia router
  const handleSearch = () => {
    router.get(
      '/',
      { operation_number: operationNumber },
      { preserveState: true, replace: true }
    );
  };

  // 🔹 Paginación con Inertia router
  const fetchPage = (page: number) => {
    router.get(
      '/',
      { page, operation_number: operationNumber },
      { preserveState: true, replace: true }
    );
  };

  return (
    <AppLayout>
      <Head title="Extractos Bancarios" />

      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl font-bold">Extractos Bancarios</h1>
        <div className="flex space-x-2">
          <input
            type="text"
            value={operationNumber}
           onChange={(e) => {
  const value = e.target.value;
  setOperationNumber(value);
  if (value === '') {
    router.get('/', {}, { preserveState: true, replace: true });
  }
}}

            placeholder="Buscar Nº de operación"
            className="px-3 py-2 border rounded"
          />
          <button
            className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
            onClick={handleSearch}
          >
            Buscar
          </button>
        </div>
      </div>

      {/* Tabla */}
      <div className="overflow-x-auto mt-4">
        <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
          <thead className="bg-gray-100 dark:bg-gray-800">
            <tr>
              <th className="px-4 py-2 text-black dark:text-white">Fecha</th>
              <th className="px-4 py-2 text-black dark:text-white">Unión</th>
              <th className="px-4 py-2 text-black dark:text-white">Código</th>
              <th className="px-4 py-2 text-black dark:text-white">Proyecto</th>
              <th className="px-4 py-2 text-black dark:text-white">Monto</th>
              <th className="px-4 py-2 text-black dark:text-white">Nº Operación</th>
              <th className="px-4 py-2 text-black dark:text-white">Hora</th>
              <th className="px-4 py-2 text-black dark:text-white">Pagado por</th>
              <th className="px-4 py-2 text-black dark:text-white">N° de Cuenta</th>
            </tr>
          </thead>
          <tbody>
            {statements.data.map((s) => (
              <tr
                key={s.id}
                className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white"
              >
                <td className="px-4 py-2">{s.date_str}</td>
                <td className="px-4 py-2">{s.union_name}</td>
                <td className="px-4 py-2">{s.code}</td>
                <td className="px-4 py-2">{s.project}</td>
                <td className="px-4 py-2">{s.amount}</td>
                <td className="px-4 py-2">{s.operation_number}</td>
                <td className="px-4 py-2">{s.operation_time}</td>
                <td className="px-4 py-2">{s.paid_by}</td>
                <td className="px-4 py-2">{s.account_number}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Paginación */}
      <div className="flex justify-center mt-6 space-x-2">
      {getPageNumbers().map((p, idx) =>
  p === '…' ? (
    <span key={`dots-${idx}`} className="px-2 text-gray-500">
      …
    </span>
  ) : (
    <button
      key={`page-${p}-${idx}`} // 🔹 clave única
      onClick={() => fetchPage(p as number)}
      className={`px-3 py-1 rounded text-sm font-medium transition ${
        statements.current_page === p
          ? 'bg-blue-600 text-white'
          : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
      }`}
      disabled={statements.current_page === p}
    >
      {p}
    </button>
  )
)}

      </div>
    </AppLayout>
  );
}
