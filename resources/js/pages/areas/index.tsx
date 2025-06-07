import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import AreaModal from './modal';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Áreas', href: '/areas' },
];

type Area = {
  id_area: number;
  descripcion: string;
  habilitado: boolean;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
};

export default function Areas() {
  const { areas: initialPagination } = usePage<{ areas: Pagination<Area> }>().props;

  const [areas, setAreas] = useState<Area[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editArea, setEditArea] = useState<Area | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const handleAreaSaved = (saved: Area) => {
    setAreas((prev) => {
      const exists = prev.find((a) => a.id_area === saved.id_area);
      return exists ? prev.map((a) => (a.id_area === saved.id_area ? saved : a)) : [saved, ...prev];
    });
    setEditArea(null);
  };

  const fetchArea = async (id: number) => {
    const res = await axios.get(`/areas/${id}`);
    setEditArea(res.data.area);
    setShowModal(true);
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setAreas(res.data.data);
      setPagination(res.data);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">Listado de Áreas</h1>

        <button
          onClick={() => {
            setEditArea(null);
            setShowModal(true);
          }}
          className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          Nueva Área
        </button>

        {selectedIds.length > 0 && (
          <button
            onClick={async () => {
              if (confirm(`¿Eliminar ${selectedIds.length} áreas?`)) {
                try {
                  await axios.post('/areas/bulk-delete', { ids: selectedIds });
                  setAreas((prev) => prev.filter((a) => !selectedIds.includes(a.id_area)));
                  setSelectedIds([]);
                } catch (e) {
                  alert('Error al eliminar en lote');
                  console.error(e);
                }
              }
            }}
            className="ml-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
          >
            Eliminar seleccionadas
          </button>
        )}

        <div className="overflow-x-auto mt-4">
          <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-4 py-2">
                  <input
                    type="checkbox"
                    checked={selectedIds.length === areas.length}
                    onChange={(e) =>
                      setSelectedIds(e.target.checked ? areas.map((a) => a.id_area) : [])
                    }
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Descripción</th>
                <th className="px-4 py-2">Habilitado</th>
              </tr>
            </thead>
            <tbody>
              {areas.map((area) => (
                <tr key={area.id_area} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td className="px-4 py-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(area.id_area)}
                      onChange={(e) =>
                        setSelectedIds((prev) =>
                          e.target.checked
                            ? [...prev, area.id_area]
                            : prev.filter((id) => id !== area.id_area)
                        )
                      }
                    />
                  </td>
                  <td className="px-4 py-2 text-sm space-x-2">
                    <button
                      onClick={() => fetchArea(area.id_area)}
                      className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                    >
                      <Paintbrush className="w-4 h-4" /> Editar
                    </button>
                    <button
                      onClick={async () => {
                        if (confirm(`¿Eliminar área "${area.descripcion}"?`)) {
                          try {
                            await axios.delete(`/areas/${area.id_area}`);
                            setAreas((prev) => prev.filter((a) => a.id_area !== area.id_area));
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
                  <td className="px-4 py-2">{area.id_area}</td>
                  <td className="px-4 py-2">{area.descripcion}</td>
                  <td className="px-4 py-2">{area.habilitado ? 'Sí' : 'No'}</td>
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
                onClick={() => fetchPage(`/areas/fetch?page=${page}`)}
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
        <AreaModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditArea(null);
          }}
          onSaved={handleAreaSaved}
          areaToEdit={editArea}
        />
      )}
    </AppLayout>
  );
}
