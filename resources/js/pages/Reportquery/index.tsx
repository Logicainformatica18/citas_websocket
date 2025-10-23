import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Plus, Trash2, Copy, Zap, Power } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Reportes', href: '/admin/report-queries' },
];

type ReportQuery = {
  id: number;
  category: string;
  question: string;
  interpreter: string;
  component?: string | null;
  description?: string | null;
  tags?: string[];
  is_active: boolean;
  has_ai_response: boolean;
  created_at: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function ReportQueriesIndex() {
  const { queries: initialPagination, categories: initialCategories } = usePage<{
    queries: Pagination<ReportQuery>;
    categories: string[];
  }>().props;

  const [items, setItems] = useState<ReportQuery[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [categories, setCategories] = useState(initialCategories);
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url, {
        params: { search, category: categoryFilter },
      });
      setItems(res.data.queries.data ?? []);
      setPagination(res.data.queries);
    } catch (e) {
      console.error('Error al cargar reportes', e);
      alert('No se pudo cargar la página.');
    }
  };

  const removeOne = async (id: number) => {
    if (!confirm('¿Eliminar este reporte?')) return;
    try {
      await axios.delete(`/admin/report-queries/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
    } catch {
      alert('No se pudo eliminar.');
    }
  };

  const toggleActive = async (id: number) => {
    try {
      const res = await axios.post(`/admin/report-queries/${id}/toggle-active`);
      setItems((prev) =>
        prev.map((i) => (i.id === id ? { ...i, is_active: res.data.is_active } : i))
      );
    } catch {
      alert('Error al cambiar estado activo.');
    }
  };

  const toggleAI = async (id: number) => {
    try {
      const res = await axios.post(`/admin/report-queries/${id}/toggle-ai`);
      setItems((prev) =>
        prev.map((i) => (i.id === id ? { ...i, has_ai_response: res.data.has_ai_response } : i))
      );
    } catch {
      alert('Error al cambiar estado de IA.');
    }
  };

  const duplicate = async (id: number) => {
    try {
      const res = await axios.post(`/admin/report-queries/${id}/duplicate`);
      alert('✅ Copia creada correctamente.');
      setItems((prev) => [res.data.query, ...prev]);
    } catch {
      alert('No se pudo duplicar.');
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 text-gray-900 dark:text-gray-100 transition-colors duration-200">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold">Gestión de Reportes (Report Queries)</h1>
          <button
            onClick={() => alert('Abrir modal de creación (pendiente)')}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded inline-flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Nuevo Reporte
          </button>
        </div>

        <div className="flex flex-wrap items-center gap-3 mb-6">
          <input
            type="text"
            placeholder="Buscar pregunta..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="px-3 py-2 border rounded bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-white flex-1"
          />
          <select
            value={categoryFilter}
            onChange={(e) => setCategoryFilter(e.target.value)}
            className="px-3 py-2 border rounded bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
          >
            <option value="">Todas las categorías</option>
            {categories.map((cat, i) => (
              <option key={i} value={cat}>
                {cat}
              </option>
            ))}
          </select>
          <button
            onClick={() => fetchPage('/admin/report-queries')}
            className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded"
          >
            Buscar
          </button>
        </div>

        <div className="overflow-x-auto rounded border border-gray-300 dark:border-gray-700">
          <table className="min-w-full text-sm bg-white dark:bg-gray-900">
            <thead className="bg-gray-200 dark:bg-gray-800 text-gray-900 dark:text-gray-300">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Categoría</th>
                <th className="px-4 py-2">Pregunta</th>
                <th className="px-4 py-2">Componente</th>
                <th className="px-4 py-2">Activo</th>
                <th className="px-4 py-2">IA</th>
                <th className="px-4 py-2">Creado</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr
                  key={item.id}
                  className="border-t border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                >
                  <td className="px-4 py-2 whitespace-nowrap">
                    <div className="flex gap-2">
                      <button
                        onClick={() => duplicate(item.id)}
                        className="text-blue-500 hover:text-blue-400"
                        title="Duplicar"
                      >
                        <Copy className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => removeOne(item.id)}
                        className="text-red-500 hover:text-red-400"
                        title="Eliminar"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                  <td className="px-4 py-2">{item.category}</td>
                  <td className="px-4 py-2 max-w-md truncate">{item.question}</td>
                  <td className="px-4 py-2">{item.component ?? '-'}</td>
                  <td className="px-4 py-2 text-center">
                    <button
                      onClick={() => toggleActive(item.id)}
                      className={`px-2 py-1 rounded text-xs font-semibold flex items-center justify-center gap-1 mx-auto ${
                        item.is_active
                          ? 'bg-green-700 text-green-100 hover:bg-green-600'
                          : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                      }`}
                    >
                      <Power className="w-3 h-3" />
                      {item.is_active ? 'Activo' : 'Inactivo'}
                    </button>
                  </td>
                  <td className="px-4 py-2 text-center">
                    <button
                      onClick={() => toggleAI(item.id)}
                      className={`px-2 py-1 rounded text-xs font-semibold flex items-center justify-center gap-1 mx-auto ${
                        item.has_ai_response
                          ? 'bg-indigo-700 text-indigo-100 hover:bg-indigo-600'
                          : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                      }`}
                    >
                      <Zap className="w-3 h-3" />
                      {item.has_ai_response ? 'IA ON' : 'IA OFF'}
                    </button>
                  </td>
                  <td className="px-4 py-2">{item.created_at}</td>
                </tr>
              ))}

              {items.length === 0 && (
                <tr>
                  <td
                    colSpan={7}
                    className="px-4 py-6 text-center text-gray-500 dark:text-gray-400"
                  >
                    No hay reportes disponibles.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* 🔹 Paginación */}
        {pagination.last_page > 1 && (
          <div className="flex justify-center mt-6 gap-2">
            {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((p) => (
              <button
                key={p}
                onClick={() => fetchPage(`/admin/report-queries?page=${p}`)}
                className={`px-3 py-1 rounded text-sm font-medium transition ${
                  pagination.current_page === p
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600'
                }`}
              >
                {p}
              </button>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
