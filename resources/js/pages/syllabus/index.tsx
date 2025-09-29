import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Sílabos', href: '/syllabus' },
];

type Upload = {
  id: number;
  filename: string;
  status: 'pending' | 'processing' | 'processed' | 'failed';
  detected_course?: string | null;
  structured_data?: {
    curso?: string;
    lenguajes?: string[];
    tecnologias?: string[];
    metodologias?: string[];
  };
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function SyllabusIndex() {
  const { uploads: initialPagination } = usePage<{
    uploads: Pagination<Upload>;
  }>().props;

  const [items, setItems] = useState<Upload[]>([]);
  const [pagination, setPagination] = useState<Pagination<Upload>>({
    data: [],
    current_page: 1,
    last_page: 1,
    next_page_url: null,
    prev_page_url: null,
  });
  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      const pager = res.data;
      setItems(pager.data ?? []);
      setPagination({
        data: pager.data ?? [],
        current_page: pager.current_page ?? 1,
        last_page: pager.last_page ?? 1,
        next_page_url: pager.next_page_url ?? null,
        prev_page_url: pager.prev_page_url ?? null,
      });
      setSelectedIds([]);
    } catch (e) {
      console.error('Error al cargar página', e);
      alert('No se pudo cargar la página.');
    }
  };

  const handleUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!file) return alert('Selecciona un archivo PDF');

    const fd = new FormData();
    fd.append('file', file);

    setLoading(true);
    try {
      const { data } = await axios.post('/syllabus/upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setItems((prev) => [data, ...prev]);
      setFile(null);
    } catch {
      alert('Error al subir el sílabo');
    } finally {
      setLoading(false);
    }
  };

  const removeOne = async (id: number, filename: string) => {
    if (!confirm(`¿Eliminar el sílabo "${filename}"?`)) return;
    try {
      await axios.delete(`/syllabus/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      setSelectedIds((prev) => prev.filter((x) => x !== id));
    } catch {
      alert('No se pudo eliminar.');
    }
  };

  const removeBulk = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`¿Eliminar ${selectedIds.length} sílabo(s)?`)) return;
    try {
      await axios.post('/syllabus/bulk-delete', { ids: selectedIds });
      setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id)));
      setSelectedIds([]);
    } catch {
      alert('No se pudo eliminar en lote.');
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-6">Gestión de Sílabos</h1>

        {/* Subir sílabo */}
        <form
          onSubmit={handleUpload}
          className="flex gap-3 items-center bg-gray-800 p-4 rounded-lg mb-6"
        >
          <input
            type="file"
            accept="application/pdf"
            onChange={(e) => setFile(e.target.files?.[0] || null)}
            className="flex-1 text-sm text-gray-300"
          />
          <button
            type="submit"
            disabled={loading || !file}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition disabled:opacity-50"
          >
            {loading ? 'Subiendo…' : 'Subir sílabo'}
          </button>
        </form>

        {/* Acciones bulk */}
        {selectedIds.length > 0 && (
          <div className="mb-4">
            <button
              onClick={removeBulk}
              className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
            >
              Eliminar Seleccionados
            </button>
          </div>
        )}

       {/* Tabla */}
<div className="overflow-x-auto">
  <table className="min-w-full table-auto border border-gray-700 rounded bg-gray-900 text-gray-200">
    <thead className="bg-gray-800 text-left text-gray-300">
      <tr>
        <th className="px-4 py-2">
          <input
            type="checkbox"
            checked={
              items.length > 0 &&
              items.every((i) => selectedIds.includes(i.id))
            }
            onChange={(e) =>
              setSelectedIds(e.target.checked ? items.map((i) => i.id) : [])
            }
          />
        </th>
        <th className="px-4 py-2">Acciones</th>
        <th className="px-4 py-2">Archivo</th>
        <th className="px-4 py-2">Estado</th>
        <th className="px-4 py-2">Curso</th>
        <th className="px-4 py-2">Lenguajes</th>
        <th className="px-4 py-2">Tecnologías</th>
        <th className="px-4 py-2">Metodologías</th>
      </tr>
    </thead>
    <tbody>
      {items.map((item) => (
        <tr key={item.id} className="border-t border-gray-700 hover:bg-gray-800 align-top">
          <td className="px-4 py-2">
            <input
              type="checkbox"
              checked={selectedIds.includes(item.id)}
              onChange={(e) =>
                setSelectedIds((prev) =>
                  e.target.checked
                    ? [...prev, item.id]
                    : prev.filter((id) => id !== item.id)
                )
              }
            />
          </td>
          <td className="px-4 py-2 whitespace-nowrap">
            <button
              onClick={() => removeOne(item.id, item.filename)}
              className="text-red-400 hover:text-red-300 inline-flex items-center gap-1 text-sm"
            >
              <Trash2 className="w-4 h-4" /> Eliminar
            </button>
          </td>
          <td className="px-4 py-2">{item.filename}</td>
          <td className="px-4 py-2">
            <span
              className={`px-2 py-1 rounded text-xs font-medium ${
                item.status === 'pending'
                  ? 'bg-yellow-900 text-yellow-200'
                  : item.status === 'processing'
                  ? 'bg-blue-900 text-blue-200'
                  : item.status === 'processed'
                  ? 'bg-green-900 text-green-200'
                  : 'bg-red-900 text-red-200'
              }`}
            >
              {item.status}
            </span>
          </td>
          <td className="px-4 py-2">
            {item.structured_data?.curso ?? '-'}
          </td>
          <td className="px-4 py-2">
            {item.structured_data?.lenguajes?.length ? (
              <div className="flex flex-wrap gap-1">
                {item.structured_data.lenguajes.map((l, i) => (
                  <span
                    key={`${l}-${i}`}
                    className="px-2 py-0.5 bg-green-900 text-green-200 rounded text-xs"
                  >
                    {l}
                  </span>
                ))}
              </div>
            ) : (
              '-'
            )}
          </td>
          <td className="px-4 py-2">
            {item.structured_data?.tecnologias?.length ? (
              <div className="flex flex-wrap gap-1">
                {item.structured_data.tecnologias.map((t, i) => (
                  <span
                    key={`${t}-${i}`}
                    className="px-2 py-0.5 bg-blue-900 text-blue-200 rounded text-xs"
                  >
                    {t}
                  </span>
                ))}
              </div>
            ) : (
              '-'
            )}
          </td>
          <td className="px-4 py-2">
            {item.structured_data?.metodologias?.length ? (
              <div className="flex flex-wrap gap-1">
                {item.structured_data.metodologias.map((m, i) => (
                  <span
                    key={`${m}-${i}`}
                    className="px-2 py-0.5 bg-purple-900 text-purple-200 rounded text-xs"
                  >
                    {m}
                  </span>
                ))}
              </div>
            ) : (
              '-'
            )}
          </td>
        </tr>
      ))}
      {items.length === 0 && (
        <tr>
          <td colSpan={8} className="px-4 py-6 text-center text-gray-400">
            No hay sílabos para mostrar.
          </td>
        </tr>
      )}
    </tbody>
  </table>
</div>


        {/* Paginación */}
        {pagination.last_page > 1 && (
          <div className="flex justify-center mt-6 gap-2">
            {[...Array(pagination.last_page)].map((_, index) => {
              const page = index + 1;
              return (
                <button
                  key={page}
                  onClick={() => fetchPage(`/syllabus/fetch?page=${page}`)}
                  className={`px-3 py-1 rounded text-sm font-medium transition ${
                    pagination.current_page === page
                      ? 'bg-blue-600 text-white'
                      : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                  }`}
                  disabled={pagination.current_page === page}
                >
                  {page}
                </button>
              );
            })}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
