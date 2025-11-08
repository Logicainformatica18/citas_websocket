import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Plus, Trash2, Copy, Zap, Power, Edit } from 'lucide-react';
import AITrainingModal from './AITrainingModal';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Entrenamiento IA', href: '/admin/ai-trainings' },
];

type AITraining = {
  id: number;
  topic: string;
  prompt: string;
  interpreter: string;
  component?: string | null;
  description?: string | null;
  tags?: string[];
  is_active: boolean;
  has_ai_response: boolean;
  created_at: string;
    created_at_formatted: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function AITrainingsIndex() {
  const { trainings: initialPagination, topics: initialTopics } = usePage<{
    trainings: Pagination<AITraining>;
    topics: string[];
  }>().props;

  const [items, setItems] = useState<AITraining[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [topics, setTopics] = useState(initialTopics);
  const [search, setSearch] = useState('');
  const [topicFilter, setTopicFilter] = useState('');

  // Modal
  const [modalOpen, setModalOpen] = useState(false);
  const [editItem, setEditItem] = useState<AITraining | null>(null);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const fetchPage = async (url: string = '/admin/ai-trainings') => {
    try {
      const res = await axios.get(url, {
        params: { search, topic: topicFilter },
      });
      setItems(res.data.trainings.data ?? []);
      setPagination(res.data.trainings);
      setTopics(res.data.topics ?? []);
    } catch (e) {
      console.error('Error al cargar entrenamientos', e);
      alert('No se pudo cargar la página.');
    }
  };

  const removeOne = async (id: number) => {
    if (!confirm('¿Eliminar este entrenamiento?')) return;
    try {
      await axios.delete(`/admin/ai-trainings/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
    } catch {
      alert('No se pudo eliminar.');
    }
  };

  const toggleActive = async (id: number) => {
    try {
      const res = await axios.post(`/admin/ai-trainings/${id}/toggle-active`);
      setItems((prev) =>
        prev.map((i) => (i.id === id ? { ...i, is_active: res.data.is_active } : i))
      );
    } catch {
      alert('Error al cambiar estado activo.');
    }
  };

  const toggleAI = async (id: number) => {
    try {
      const res = await axios.post(`/admin/ai-trainings/${id}/toggle-ai`);
      setItems((prev) =>
        prev.map((i) =>
          i.id === id ? { ...i, has_ai_response: res.data.has_ai_response } : i
        )
      );
    } catch {
      alert('Error al cambiar estado de IA.');
    }
  };

  const duplicate = async (id: number) => {
    try {
      const res = await axios.post(`/admin/ai-trainings/${id}/duplicate`);
      alert('✅ Copia creada correctamente.');
      setItems((prev) => [res.data.training, ...prev]);
    } catch {
      alert('No se pudo duplicar.');
    }
  };

  const handleOpenNew = () => {
    setEditItem(null);
    setModalOpen(true);
  };

  const handleEdit = (training: AITraining) => {
    setEditItem(training);
    setModalOpen(true);
  };

  const handleSaved = async () => {
    await fetchPage();
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 text-gray-900 dark:text-gray-100 transition-colors duration-200">
        {/* 🔹 Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
          <h1 className="text-2xl font-bold">Entrenamientos de IA</h1>
          <button
            onClick={handleOpenNew}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md inline-flex items-center gap-2 shadow-sm transition"
          >
            <Plus className="w-4 h-4" /> Nuevo Entrenamiento
          </button>
        </div>

        {/* 🔍 Filtros */}
        <div className="flex flex-wrap items-center gap-3 mb-6">
          <input
            type="text"
            placeholder="Buscar prompt o descripción..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-white flex-1 focus:ring-2 focus:ring-blue-400 outline-none"
          />
          <select
            value={topicFilter}
            onChange={(e) => setTopicFilter(e.target.value)}
            className="px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
          >
            <option value="">Todos los temas</option>
            {topics.map((cat, i) => (
              <option key={i} value={cat}>
                {cat}
              </option>
            ))}
          </select>
          <button
            onClick={() => fetchPage()}
            className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md shadow-sm"
          >
            Buscar
          </button>
        </div>

        {/* 📋 Tabla */}
        <div className="overflow-x-auto border border-gray-300 dark:border-gray-700 rounded-lg shadow-sm">
          <table className="min-w-full text-sm bg-white dark:bg-gray-900">
            <thead className="bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-300 uppercase text-xs font-semibold">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Tema</th>
                <th className="px-4 py-2">Prompt</th>
                <th className="px-4 py-2">Componente</th>
                <th className="px-4 py-2 text-center">Activo</th>
                <th className="px-4 py-2 text-center">IA</th>
                <th className="px-4 py-2">Creado</th>
              </tr>
            </thead>
            <tbody>
              {items.length > 0 ? (
                items.map((item) => (
                  <tr
                    key={item.id}
                    className="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                  >
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div className="flex gap-2">
                        <button
                          onClick={() => handleEdit(item)}
                          className="text-yellow-500 hover:text-yellow-400"
                          title="Editar"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
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
                    <td className="px-4 py-2">{item.topic}</td>
                    <td className="px-4 py-2 max-w-md truncate">{item.prompt}</td>
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
                  <td className="px-4 py-2">{item.created_at_formatted}</td>

                  </tr>
                ))
              ) : (
                <tr>
                  <td
                    colSpan={7}
                    className="px-4 py-6 text-center text-gray-500 dark:text-gray-400"
                  >
                    No hay entrenamientos configurados.
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
                onClick={() => fetchPage(`/admin/ai-trainings?page=${p}`)}
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

      {/* 🔹 Modal creación/edición */}
      <AITrainingModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSaved={handleSaved}
        editItem={editItem}
      />
    </AppLayout>
  );
}
