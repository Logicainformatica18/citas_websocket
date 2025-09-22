import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Trash2, Play, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Scrapings', href: '/scrapings' },
  { title: 'Detalle', href: '#' },
];

type ScrapingField = {
  id: number;
  field_name: string;
  selector: string;
  path: string;
};

type Scraping = {
  id: number;
  name: string;
  base_url: string;
  fields: ScrapingField[];
};

export default function ScrapingShow() {
  const { scraping } = usePage<{ scraping: Scraping }>().props;
  const [results, setResults] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  // modal nuevo field
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({ field_name: '', selector: '', path: '/' });
  const [processing, setProcessing] = useState(false);

  const runScraping = async () => {
    setLoading(true);
    try {
      const res = await axios.post(`/scrapings/${scraping.id}/run`);
      setResults(res.data.data || []);
    } catch (e) {
      console.error('Error al ejecutar scraping', e);
      alert('Error al ejecutar scraping');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateField = async (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);
    try {
      await router.post(`/scrapings/${scraping.id}/fields`, form, {
        onSuccess: () => {
          setShowModal(false);
          setForm({ field_name: '', selector: '', path: '/' });
        },
      });
    } finally {
      setProcessing(false);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">Scraping: {scraping.name}</h1>
        <p className="text-muted-foreground mb-6">URL base: {scraping.base_url}</p>

        <div className="flex justify-between mb-4">
          <button
            onClick={() => setShowModal(true)}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Nuevo campo
          </button>

          <button
            onClick={runScraping}
            disabled={loading}
            className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 flex items-center gap-2 disabled:opacity-50"
          >
            <Play className="w-4 h-4" /> {loading ? 'Ejecutando...' : 'Ejecutar scraping'}
          </button>
        </div>

        <div className="overflow-x-auto mb-8">
          <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-4 py-2 text-left">Nombre</th>
                <th className="px-4 py-2 text-left">Selector</th>
                <th className="px-4 py-2 text-left">Path</th>
                <th className="px-4 py-2 text-left">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {scraping.fields.map((f) => (
                <tr key={f.id} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td className="px-4 py-2">{f.field_name}</td>
                  <td className="px-4 py-2"><code>{f.selector}</code></td>
                  <td className="px-4 py-2">{f.path}</td>
                  <td className="px-4 py-2">
                    <button
                      onClick={() => {
                        if (confirm(`¿Eliminar campo ${f.field_name}?`)) {
                          router.delete(`/scrapings/${scraping.id}/fields/${f.id}`);
                        }
                      }}
                      className="flex items-center gap-1 text-red-600 hover:underline"
                    >
                      <Trash2 className="w-4 h-4" /> Eliminar
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {results.length > 0 && (
          <div className="mt-8">
            <h2 className="text-xl font-bold mb-4">Resultados</h2>
            <div className="overflow-x-auto">
              <table className="min-w-full border border-gray-300 rounded bg-white dark:bg-black">
                <thead>
                  <tr>
                    {Object.keys(results[0]).map((col) => (
                      <th
                        key={col}
                        className="px-4 py-2 border text-black dark:text-white bg-gray-100 dark:bg-gray-800"
                      >
                        {col}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {results.map((row, i) => (
                    <tr key={i} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700">
                      {Object.keys(results[0]).map((col) => (
                        <td key={col} className="px-4 py-2 border text-black dark:text-white">
                          {row[col] ?? '-'}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>

      {/* Modal para nuevo field */}
      {showModal && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
          <div className="bg-white dark:bg-gray-900 rounded shadow-lg p-6 w-full max-w-lg">
            <h2 className="text-xl font-bold mb-4">Nuevo campo</h2>
            <form onSubmit={handleCreateField} className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Nombre del campo</label>
                <input
                  type="text"
                  value={form.field_name}
                  onChange={(e) => setForm({ ...form, field_name: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                  placeholder="Ej: course"
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-1">Selector CSS</label>
                <input
                  type="text"
                  value={form.selector}
                  onChange={(e) => setForm({ ...form, selector: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                  placeholder=".career-card__title"
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-1">Path</label>
                <input
                  type="text"
                  value={form.path}
                  onChange={(e) => setForm({ ...form, path: e.target.value })}
                  className="w-full border rounded px-3 py-2"
                  placeholder="/ o /profesores"
                />
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
