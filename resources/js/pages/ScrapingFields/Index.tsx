import AppLayout from '@/layouts/app-layout';
import { usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2, Plus } from 'lucide-react';

const breadcrumbs = [{ title: 'Scrapings', href: '/scrapings' }];

type Scraping = {
  id: number;
  name: string;
};

type Field = {
  id: number;
  field_name: string;
  selector: string;
  path?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
};

export default function ScrapingFieldsIndex() {
  const { scraping, fields: initialPagination } = usePage<{
    scraping: Scraping;
    fields: Pagination<Field>;
  }>().props;

  const [fields, setFields] = useState<Field[]>(initialPagination.data || []);
  const [pagination, setPagination] = useState(initialPagination);

  // Modal
  const [showModal, setShowModal] = useState(false);
  const [editField, setEditField] = useState<Field | null>(null);
  const [form, setForm] = useState({ field_name: '', selector: '', path: '' });
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState<{ field_name?: string; selector?: string; path?: string }>({});

  const handleSaved = (saved: Field) => {
    setFields((prev) => {
      const exists = prev.find((f) => f.id === saved.id);
      return exists ? prev.map((f) => (f.id === saved.id ? saved : f)) : [saved, ...prev];
    });
    setEditField(null);
  };

  const fetchField = async (id: number) => {
    try {
      const res = await axios.get(`/scrapings/${scraping.id}/fields/${id}`);
      setEditField(res.data.field);
      setForm({
        field_name: res.data.field.field_name,
        selector: res.data.field.selector,
        path: res.data.field.path || '',
      });
      setShowModal(true);
    } catch (e) {
      console.error('Error al cargar campo', e);
    }
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setFields(res.data.fields.data);
      setPagination(res.data.fields);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

 const handleSubmit = async (e: React.FormEvent) => {
  e.preventDefault();
  setProcessing(true);
  setErrors({});
  try {
    if (editField) {
      // ✅ Actualizar con axios
      const res = await axios.put(
        `/scrapings/${scraping.id}/fields/${editField.id}`,
        form
      );
      handleSaved(res.data.field);
      setShowModal(false);
    } else {
      // ✅ Crear con axios
      const res = await axios.post(`/scrapings/${scraping.id}/fields`, form);
      handleSaved(res.data.field);
      setShowModal(false);
      setForm({ field_name: "", selector: "", path: "" });
    }
  } catch (error: any) {
    if (error.response?.status === 422) {
      setErrors(error.response.data.errors);
    }
    console.error("❌ Error en handleSubmit", error);
  } finally {
    setProcessing(false);
  }
};


  return (
    <AppLayout
      breadcrumbs={[
        ...breadcrumbs,
        { title: scraping.name, href: `/scrapings/${scraping.id}/fields` },
      ]}
    >
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">
          Campos de: <span className="text-blue-600">{scraping.name}</span>
        </h1>

        <button
          onClick={() => {
            setEditField(null);
            setForm({ field_name: '', selector: '', path: '' });
            setShowModal(true);
          }}
          className="mb-4 px-4 py-2 flex items-center gap-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          <Plus className="w-4 h-4" /> Nuevo Campo
        </button>

        <div className="overflow-x-auto mt-4">
          <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">Selector</th>
                <th className="px-4 py-2">Ruta (path)</th>
              </tr>
            </thead>
            <tbody>
              {fields.map((f) => (
                <tr
                  key={f.id}
                  className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white"
                >
                  <td className="px-4 py-2 space-x-2 text-sm">
                    <button
                      onClick={() => fetchField(f.id)}
                      className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                    >
                      <Paintbrush className="w-4 h-4" /> Editar
                    </button>
                    <button
                      onClick={async () => {
                        if (confirm(`¿Eliminar campo ${f.field_name}?`)) {
                          try {
                            await axios.delete(`/scrapings/${scraping.id}/fields/${f.id}`);
                            setFields((prev) => prev.filter((u) => u.id !== f.id));
                          } catch (e) {
                            alert('Error al eliminar');
                            console.error(e);
                          }
                        }
                      }}
                      className="text-red-600 hover:underline dark:text-red-400 flex items-center gap-1"
                    >
                      <Trash2 className="w-4 h-4" /> Eliminar
                    </button>
                  </td>

                  <td className="px-4 py-2">{f.id}</td>
                  <td className="px-4 py-2">{f.field_name}</td>
                  <td className="px-4 py-2">{f.selector}</td>
                  <td className="px-4 py-2">{f.path || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex justify-center mt-6 space-x-2">
          {[...Array(pagination.last_page)].map((_, index) => {
            const page = index + 1;
            return (
              <button
                key={page}
                onClick={() =>
                  fetchPage(`/scrapings/${scraping.id}/fields/fetch?page=${page}`)
                }
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
      </div>

      {showModal && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
          <div className="bg-white dark:bg-gray-900 rounded shadow-lg p-6 w-full max-w-lg">
            <h2 className="text-xl font-bold mb-4">
              {editField ? 'Editar Campo' : 'Nuevo Campo'}
            </h2>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Nombre</label>
                <input
                  type="text"
                  value={form.field_name}
                  onChange={(e) => setForm({ ...form, field_name: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                  placeholder="Ej: Curso"
                />
                {errors.field_name && <p className="text-red-600 text-sm">{errors.field_name}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium mb-1">Selector CSS</label>
                <input
                  type="text"
                  value={form.selector}
                  onChange={(e) => setForm({ ...form, selector: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                  placeholder=".clase-css"
                />
                {errors.selector && <p className="text-red-600 text-sm">{errors.selector}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium mb-1">Ruta (Path)</label>
                <input
                  type="text"
                  value={form.path}
                  onChange={(e) => setForm({ ...form, path: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                  placeholder="/subpagina/opcional"
                />
                {errors.path && <p className="text-red-600 text-sm">{errors.path}</p>}
              </div>

              <div className="flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition disabled:opacity-50"
                >
                  {processing ? 'Guardando...' : 'Guardar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
