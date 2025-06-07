// resources/js/pages/projects/index.tsx
import axios from 'axios';
import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Paintbrush, Trash2 } from 'lucide-react';
import ProjectModal from './modal';
import HourglassLoader from '@/components/HourglassLoader';

type BreadcrumbItem = {
  title: string;
  href: string;
};

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Proyectos', href: '/projects' },
];

type ProjectItem = {
  id_proyecto: number;
  descripcion: string;
  habilitado: number;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

export default function Projects() {
  const { projects: initialPagination } = usePage<{ projects: Pagination<ProjectItem> }>().props;
  const [items, setItems] = useState<ProjectItem[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<ProjectItem | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const handleSaved = (saved: ProjectItem) => {
    setItems((prev) => {
      const exists = prev.find((i) => i.id_proyecto === saved.id_proyecto);
      return exists ? prev.map((i) => (i.id_proyecto === saved.id_proyecto ? saved : i)) : [saved, ...prev];
    });
    setEditItem(null);
  };

  const fetchItem = async (id: number) => {
    try {
      setEditingId(id);
      const res = await axios.get(`/projects/${id}`);
      setEditItem(res.data.project);
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

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-6">Proyectos</h1>

        <div className="flex items-center gap-2 mb-4">
          <button
            onClick={() => {
              setEditItem(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-blue-600 text-white rounded"
          >
            Nuevo Proyecto
          </button>

          {selectedIds.length > 0 && (
            <button
              onClick={async () => {
                if (confirm(`¿Eliminar ${selectedIds.length} proyecto(s)?`)) {
                  await axios.post('/projects/bulk-delete', { ids: selectedIds });
                  setItems((prev) => prev.filter((i) => !selectedIds.includes(i.id_proyecto)));
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
                    onChange={(e) => setSelectedIds(e.target.checked ? items.map((i) => i.id_proyecto) : [])}
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Descripción</th>
                <th className="px-4 py-2">Habilitado</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id_proyecto} className="border-t hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(item.id_proyecto)}
                      onChange={(e) => setSelectedIds((prev) =>
                        e.target.checked ? [...prev, item.id_proyecto] : prev.filter((id) => id !== item.id_proyecto)
                      )}
                    />
                  </td>
                  <td className="px-4 py-2 space-x-2">
                    <button
                      onClick={() => fetchItem(item.id_proyecto)}
                      disabled={editingId === item.id_proyecto}
                      className="text-blue-600 hover:underline"
                    >
                      {editingId === item.id_proyecto ? (
                        <HourglassLoader />
                      ) : (
                        <>
                          <Paintbrush className="w-4 h-4 inline" /> Editar
                        </>
                      )}
                    </button>

                    <button
                      onClick={async () => {
                        if (confirm('¿Eliminar este proyecto?')) {
                          try {
                            setDeletingId(item.id_proyecto);
                            await axios.delete(`/projects/${item.id_proyecto}`);
                            setItems((prev) => prev.filter((i) => i.id_proyecto !== item.id_proyecto));
                          } finally {
                            setDeletingId(null);
                          }
                        }
                      }}
                      disabled={deletingId === item.id_proyecto}
                      className="text-red-600 hover:underline"
                    >
                      {deletingId === item.id_proyecto ? (
                        <HourglassLoader />
                      ) : (
                        <>
                          <Trash2 className="w-4 h-4 inline" /> Eliminar
                        </>
                      )}
                    </button>
                  </td>
                  <td className="px-4 py-2">{item.id_proyecto}</td>
                  <td className="px-4 py-2">{item.descripcion}</td>
                  <td className="px-4 py-2">{item.habilitado ? 'Sí' : 'No'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex justify-center mt-6 gap-2">
          {[...Array(pagination.last_page)].map((_, index) => {
            const page = index + 1;
            return (
              <button
                key={page}
                onClick={() => fetchPage(`/projects/fetch?page=${page}`)}
                className={`px-3 py-1 rounded ${pagination.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}
              >
                {page}
              </button>
            );
          })}
        </div>
      </div>

      {showModal && (
        <ProjectModal
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
