import AppLayout from '@/layouts/app-layout';
import { usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2, Settings, Plus, Archive } from 'lucide-react'; // 👈 añadido Archive
import ScrapingModal from './ScrapingModal';

const breadcrumbs = [{ title: 'Scrapings', href: '/scrapings' }];

type Scraping = {
  id: number;
  name: string;
  base_url: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
};

export default function Scrapings() {
  const { scrapings: initialPagination } = usePage<{ scrapings: Pagination<Scraping> }>().props;
  const [scrapings, setScrapings] = useState<Scraping[]>(initialPagination?.data || []);
  const [pagination, setPagination] = useState(initialPagination);

  // Modal
  const [showModal, setShowModal] = useState(false);
  const [editScraping, setEditScraping] = useState<Scraping | null>(null);

  const handleSaved = (saved: Scraping) => {
    setScrapings((prev) => {
      const exists = prev.find((s) => s.id === saved.id);
      return exists
        ? prev.map((s) => (s.id === saved.id ? saved : s))
        : [saved, ...prev];
    });
    setEditScraping(null);
  };

  const fetchScraping = async (id: number) => {
    try {
      const res = await axios.get(`/scrapings/${id}`);
      setEditScraping(res.data.scraping);
      setShowModal(true);
    } catch (e) {
      console.error('Error al cargar scraping', e);
    }
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setScrapings(res.data.scrapings.data);
      setPagination(res.data.scrapings);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">Listado de Scrapings</h1>

        <button
          onClick={() => {
            setEditScraping(null);
            setShowModal(true);
          }}
          className="mb-4 px-4 py-2 flex items-center gap-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          <Plus className="w-4 h-4" /> Nuevo Scraping
        </button>

        <div className="overflow-x-auto mt-4">
          <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Gestionar</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">URL base</th>
              </tr>
            </thead>
            <tbody>
              {scrapings.map((s) => (
                <tr
                  key={s.id}
                  className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white"
                >
                  <td className="px-4 py-2 space-x-2 text-sm">
                    <button
                      onClick={() => fetchScraping(s.id)}
                      className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                    >
                      <Paintbrush className="w-4 h-4" /> Editar
                    </button>

                    <button
                      onClick={async () => {
                        if (confirm(`¿Eliminar scraping ${s.name}?`)) {
                          try {
                            await axios.delete(`/scrapings/${s.id}`);
                            setScrapings((prev) => prev.filter((u) => u.id !== s.id));
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

                  <td className="px-4 py-2 text-sm flex gap-3">
                    <button
                      onClick={() => router.visit(`/scrapings/${s.id}/fields`)}
                      className="text-indigo-600 hover:underline flex items-center gap-1 dark:text-indigo-400"
                    >
                      <Settings className="w-4 h-4" /> Campos
                    </button>

                    <button
                      onClick={() => router.visit(`/scrapings/${s.id}/backups`)}
                      className="text-green-600 hover:underline flex items-center gap-1 dark:text-green-400"
                    >
                      <Archive className="w-4 h-4" /> Backups
                    </button>
                  </td>

                  <td className="px-4 py-2">{s.id}</td>
                  <td className="px-4 py-2">{s.name}</td>
                  <td className="px-4 py-2">{s.base_url}</td>
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
                onClick={() => fetchPage(`/scrapings/fetch?page=${page}`)}
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
        <ScrapingModal
          scraping={editScraping}
          onClose={() => {
            setShowModal(false);
            setEditScraping(null);
          }}
          onSaved={handleSaved}
        />
      )}
    </AppLayout>
  );
}
