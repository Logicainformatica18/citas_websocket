import { Paintbrush, Trash2 } from 'lucide-react';
import HourglassLoader from '@/components/HourglassLoader';
import { useState } from 'react';
import axios from 'axios';
import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import AreaModal from './AreaModal'; // asegúrate de que el path sea correcto

interface Support {
    id: number;
    subject: string;
    description?: string;
    priority: string;
    type: string;
    status: string;
    attachment?: string;
    reservation_time?: string;
    attended_at?: string;
    derived?: string;
    cellphone?: string;
    created_at?: string;
    updated_at?: string;
    area?: { descripcion: string };
    creator?: { firstname: string; lastname: string; names: string };
    client?: { Razon_Social: string };
    project?: { descripcion: string; id_proyecto: number };
    Manzana?: string;
    Lote?: string;
    external_state?: { description: string; id: number };
    internal_state?: { description: string; id: number };
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    supports: Support[];
    setSupports: React.Dispatch<React.SetStateAction<Support[]>>;
    pagination: Pagination<Support>;
    fetchPage: (url: string) => Promise<void>;
    fetchSupport: (id: number) => Promise<void>;
    setShowAreaModal: React.Dispatch<React.SetStateAction<boolean>>;
    setSelectedSupportId: React.Dispatch<React.SetStateAction<number | null>>; // ✅ AGREGA ESTA PROP
    areas: Array<{ id_area: number; descripcion: string }>;
    motives: Array<{ id: number; nombre_motivo: string }>;
    highlightedIds: number[]; // 👈 AÑADE ESTA LÍNEA
}


type PageProps = {
    permissions: string[];
};

export default function SupportTable({
    supports,
    setSupports,
    pagination,
    fetchPage,
    fetchSupport,
    setShowAreaModal,
    setSelectedSupportId, // ✅ AGREGA ESTO
    areas,
    motives,
    highlightedIds, // ✅ FALTA ESTA LÍNEA
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const { permissions } = usePage<PageProps>().props;
    const canEdit = permissions.includes('administrar') || permissions.includes('atc');


return (
  <div className="overflow-x-auto mt-4">
    <>
      {selectedIds.length > 0 && (
        <button
          onClick={async () => {
            if (confirm(`¿Eliminar ${selectedIds.length} tickets?`)) {
              try {
                await axios.post('/supports/bulk-delete', { ids: selectedIds });
                setSupports((prev) => prev.filter((s) => !selectedIds.includes(s.id)));
                setSelectedIds([]);
              } catch (e) {
                alert('Error al eliminar en Lote');
                console.error(e);
              }
            }
          }}
          className="mb-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
        >
          Eliminar seleccionados
        </button>
      )}

      <div className="w-full overflow-x-auto">
        <table className="min-w-[1200px] text-sm divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
          <thead className="bg-gray-100 dark:bg-gray-800">
            <tr>
              <th className="px-2 py-1">
                <input
                  type="checkbox"
                  checked={selectedIds.length === supports.length}
                  onChange={(e) =>
                    setSelectedIds(e.target.checked ? supports.map((s) => s.id) : [])
                  }
                />
              </th>
              {canEdit && <th className="px-2 py-1">Acciones</th>}
              {canEdit && (
              <th className="px-2 py-1">Area Mantenimiento</th>
                )}
              <th className="px-2 py-1">Detalle</th>
              <th className="px-2 py-1">ID</th>
              <th className="px-2 py-1">Área</th>
              <th className="px-2 py-1">Cliente</th>
              <th className="px-2 py-1">Asunto</th>
              <th className="px-2 py-1">Proyecto</th>
              <th className="px-2 py-1">Manzana</th>
              <th className="px-2 py-1">Lote</th>
              <th className="px-2 py-1">Prioridad</th>
              <th className="px-2 py-1">Creación</th>
              <th className="px-2 py-1">Estado de Atención</th>
              <th className="px-2 py-1">Estado Interno</th>
              <th className="px-2 py-1">Archivo</th>
            </tr>
          </thead>
          <tbody>
            {supports.map((support) => (
              <tr
                key={support.id}
                className={`border-t hover:bg-gray-50 dark:hover:bg-gray-700 ${
                  highlightedIds.includes(support.id) ? 'animate-border-glow' : ''
                }`}
              >
                <td className="px-2 py-1">
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(support.id)}
                    onChange={(e) =>
                      setSelectedIds((prev) =>
                        e.target.checked
                          ? [...prev, support.id]
                          : prev.filter((id) => id !== support.id)
                      )
                    }
                  />
                </td>
                {canEdit && (
                  <td className="px-2 py-1 text-sm space-x-2">
                    <button
                      onClick={async () => {
                        setEditingId(support.id);
                        await fetchSupport(support.id);
                        setEditingId(null);
                      }}
                      disabled={editingId === support.id}
                      className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                    >
                      {editingId === support.id ? (
                        <HourglassLoader />
                      ) : (
                        <>
                          <Paintbrush className="w-4 h-4" /> Editar
                        </>
                      )}
                    </button>
                    <button
                      onClick={async () => {
                        if (confirm(`¿Eliminar soporte "${support.subject}"?`)) {
                          try {
                            setDeletingId(support.id);
                            await axios.delete(`/supports/${support.id}`);
                            setSupports((prev) => prev.filter((s) => s.id !== support.id));
                          } catch (e) {
                            alert('Error al eliminar');
                            console.error(e);
                          } finally {
                            setDeletingId(null);
                          }
                        }
                      }}
                      disabled={deletingId === support.id}
                      className="text-red-600 hover:underline dark:text-red-400 flex items-center gap-1"
                    >
                      {deletingId === support.id ? (
                        <HourglassLoader />
                      ) : (
                        <>
                          <Trash2 className="w-4 h-4" /> Eliminar
                        </>
                      )}
                    </button>
                  </td>

                )}
{canEdit && (
 <td className="px-2 py-1">
                  <button
                    onClick={() => {
                      setSelectedSupportId(support.id);
                      setShowAreaModal(true);
                    }}
                    className="text-blue-600 text-sm underline hover:text-blue-800 transition"
                  >
                     Área/Motivo
                  </button>
                </td>
  )}

                <td className="px-2 py-1">
                  <Link
                    href={`/reports/${support.id}`}
                    className="text-blue-600 underline hover:text-blue-800 text-sm"
                  >
                    Ver Reporte
                  </Link>
                </td>
                <td className="px-2 py-1">Ticket-{String(support.id).padStart(5, '0')}</td>
                <td className="px-2 py-1">{support.area?.descripcion || '-'}</td>
                <td className="px-2 py-1">{support.client?.Razon_Social || '-'}</td>
                <td className="px-2 py-1 max-w-[150px] truncate">{support.subject}</td>
                <td className="px-2 py-1">{support.project?.descripcion || '-'}</td>
                <td className="px-2 py-1">{support.Manzana}</td>
                <td className="px-2 py-1">{support.Lote}</td>
                <td className="px-2 py-1">{support.priority}</td>
                <td className="px-2 py-1">{support.created_at}</td>
                <td className="px-2 py-1">{support.external_state?.description || '-'}</td>
                <td className="px-2 py-1">{support.internal_state?.description || '-'}</td>
                <td className="px-2 py-1 whitespace-nowrap">
                  {support.attachment && (
                    <a
                      href={`/attachments/${support.attachment}`}
                      download
                      className="text-blue-600 underline dark:text-blue-400"
                    >
                      {support.attachment}
                    </a>
                  )}
                </td>
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
              onClick={() => fetchPage(`/supports/fetch?page=${page}`)}
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
    </>
  </div>
);


}
