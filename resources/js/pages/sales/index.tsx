// resources/js/pages/sales/index.tsx
import axios from 'axios';
import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Paintbrush, Trash2 } from 'lucide-react';
import SaleModal from './modal';
import HourglassLoader from '@/components/HourglassLoader';

type BreadcrumbItem = {
  title: string;
  href: string;
};

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Ventas', href: '/sales' },
];

type SaleItem = {
  id: number;
  code: string;
  holder: string;
  stage: string;
  mz_lote: string;
  state: string;
  cliente?: string;
  proyecto?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

export default function Sales() {
  const { sales: initialPagination } = usePage<{ sales: Pagination<SaleItem> }>().props;
  const [items, setItems] = useState<SaleItem[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<SaleItem | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const handleSaved = (saved: SaleItem) => {
    setItems((prev) => {
      const exists = prev.find((i) => i.id === saved.id);
      return exists ? prev.map((i) => (i.id === saved.id ? saved : i)) : [saved, ...prev];
    });
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    try {
      setEditingId(id);
      const res = await axios.get(`/sales/${id}`);
      setEditItem(res.data.sale);
      setShowModal(true);
    } finally {
      setEditingId(null);
    }
  };

  const fetchPage = async (url: string) => {
    const res = await axios.get(url);
    setItems(res.data.data);
    setPagination(res.data);
  };
function getVisiblePages(current: number, last: number): (number | string)[] {
  const delta = 2;
  const range = [];
  const rangeWithDots = [];
  let l: number;

  for (let i = 1; i <= last; i++) {
    if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
      range.push(i);
    }
  }

  for (const i of range) {
    if (l) {
      if (i - l === 2) {
        rangeWithDots.push(l + 1);
      } else if (i - l !== 1) {
        rangeWithDots.push('...');
      }
    }
    rangeWithDots.push(i);
    l = i;
  }

  return rangeWithDots;
}

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-6">Ventas</h1>

        <div className="flex items-center gap-2 mb-4">
          <button
            onClick={() => {
              setEditItem(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-blue-600 text-white rounded"
          >
            Nueva Venta
          </button>

          {selectedIds.length > 0 && (
            <button
              onClick={async () => {
                if (confirm(`¿Eliminar ${selectedIds.length} venta(s)?`)) {
                  await axios.post('/sales/bulk-delete', { ids: selectedIds });
                  setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id)));
                  setSelectedIds([]);
                }
              }}
              className="px-4 py-2 bg-red-600 text-white rounded"
            >
              Eliminar Seleccionados
            </button>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border rounded">
            <thead className="bg-gray-100 text-left">
              <tr>
                <th className="px-4 py-2">
                  <input
                    type="checkbox"
                    checked={selectedIds.length === items.length}
                    onChange={(e) => setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])}
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Código</th>
                <th className="px-4 py-2">Titular</th>
                <th className="px-4 py-2">Etapa</th>
                <th className="px-4 py-2">Mz-Lote</th>
                <th className="px-4 py-2">Estado</th>
                <th className="px-4 py-2">Cliente</th>
                <th className="px-4 py-2">Proyecto</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-t hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(item.id)}
                      onChange={(e) => setSelectedIds((prev) =>
                        e.target.checked ? [...prev, item.id] : prev.filter((id) => id !== item.id)
                      )}
                    />
                  </td>
                  <td className="px-4 py-2 space-x-2">
                    <button
                      onClick={() => fetchItem(item.id)}
                      disabled={editingId === item.id}
                      className="text-blue-600 hover:underline"
                    >
                      {editingId === item.id ? (
                        <HourglassLoader />
                      ) : (
                        <>
                          <Paintbrush className="w-4 h-4 inline" /> Editar
                        </>
                      )}
                    </button>

                    <button
                      onClick={async () => {
                        if (confirm('¿Eliminar esta venta?')) {
                          try {
                            setDeletingId(item.id);
                            await axios.delete(`/sales/${item.id}`);
                            setItems((prev) => prev.filter((i) => i.id !== item.id));
                          } finally {
                            setDeletingId(null);
                          }
                        }
                      }}
                      disabled={deletingId === item.id}
                      className="text-red-600 hover:underline"
                    >
                      {deletingId === item.id ? (
                        <HourglassLoader />
                      ) : (
                        <>
                          <Trash2 className="w-4 h-4 inline" /> Eliminar
                        </>
                      )}
                    </button>
                  </td>
                  <td className="px-4 py-2">{item.id}</td>
                  <td className="px-4 py-2">{item.code}</td>
                  <td className="px-4 py-2">{item.holder}</td>
                  <td className="px-4 py-2">{item.stage}</td>
                  <td className="px-4 py-2">{item.mz_lote}</td>
                  <td className="px-4 py-2">{item.state}</td>
                  <td className="px-4 py-2">{item.cliente ?? '-'}</td>
                  <td className="px-4 py-2">{item.proyecto ?? '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

      <div className="flex justify-center mt-6 gap-2">
 {getVisiblePages(pagination.current_page, pagination.last_page).map((page, idx) =>
  page === '...' ? (
    <span key={`dots-${idx}`} className="px-2 py-1 text-gray-400">...</span>
  ) : (
    <button
      key={`page-${page}-${idx}`} // 👈 se asegura que sea única
      onClick={() => fetchPage(`/sales/fetch?page=${page}`)}
      className={`px-3 py-1 rounded ${
        pagination.current_page === page
          ? 'bg-blue-600 text-white'
          : 'bg-gray-200 text-gray-800'
      }`}
    >
      {page}
    </button>
  )
)}

</div>

      </div>

      {showModal && (
        <SaleModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditItem(null);
          }}
          onSaved={handleSaved}
          itemToEdit={editItem}
        />
      )}
    </AppLayout>
  );
}
