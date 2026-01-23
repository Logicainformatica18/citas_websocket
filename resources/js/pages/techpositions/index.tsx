import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { Trash2, Search, Briefcase } from 'lucide-react';
import TechPositionModal from "./modal";

/* =====================================================
   Breadcrumbs
===================================================== */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Roles Tecnológicos', href: '/tech-positions' }
];

/* =====================================================
   Types
===================================================== */
type SimpleItem = {
  id: number;
  name: string;
};

type TechPosition = {
  id: number;
  position_name: string;
  position_name_en: string | null;
  category?: string | null;
  subcategory?: string | null;
  description?: string | null;
  active: number;

  careers: SimpleItem[];
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

/* =====================================================
   Page
===================================================== */
export default function TechPositionsIndex() {
  const {
    positions: initialPagination,
    careers,
  } = usePage<{
    positions: Pagination<TechPosition>;
    careers: SimpleItem[];
  }>().props;

  const [items, setItems] = useState<TechPosition[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);

  const [searchTerm, setSearchTerm] = useState('');
  const [typingTimeout, setTypingTimeout] = useState<NodeJS.Timeout | null>(null);

  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showModal, setShowModal] = useState(false);
  const [editItem, setEditItem] = useState<TechPosition | null>(null);

  /* =====================================================
     🔍 Buscador con debounce
  ===================================================== */
  useEffect(() => {
    if (typingTimeout) clearTimeout(typingTimeout);

    const timeout = setTimeout(async () => {
      if (searchTerm.trim() === '') {
        fetchPage('/tech-positions/fetch');
        return;
      }

      try {
        const res = await axios.get('/tech-positions/fetch', {
          params: { search: searchTerm }
        });
        const pager = res.data;
        setItems(pager.data);
        setPagination(pager);
      } catch {}
    }, 600);

    setTypingTimeout(timeout);
  }, [searchTerm]);

  /* =====================================================
     Data helpers
  ===================================================== */
  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setItems(res.data.data);
      setPagination(res.data);
      setSelectedIds([]);
    } catch {
      alert('Error cargando listado');
    }
  };

  const fetchItem = async (id: number) => {
    try {
      const res = await axios.get(`/tech-positions/${id}`);
      setEditItem(res?.data?.position ?? null);
      setShowModal(true);
    } catch {
      alert('No se pudo cargar el rol.');
    }
  };

  const removeOne = async (id: number, name: string) => {
    if (!confirm(`¿Eliminar el rol "${name}"?`)) return;
    try {
      await axios.delete(`/tech-positions/${id}`);
      setItems(prev => prev.filter(p => p.id !== id));
    } catch {
      alert('No se pudo eliminar.');
    }
  };


return (
  <AppLayout breadcrumbs={breadcrumbs}>
    <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">

      {/* HEADER */}
      <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <Briefcase className="w-6 h-6 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">
            Roles Tecnológicos
          </span>
        </h1>

        <button
          onClick={() => {
            setEditItem(null);
            setShowModal(true);
          }}
          className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow transition"
        >
          Nuevo Rol Tecnológico
        </button>
      </div>

      {/* BUSCADOR */}
      <div className="relative mb-6 w-full md:w-1/2">
        <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
        <input
          type="text"
          placeholder="Buscar rol tecnológico..."
          className="w-full pl-9 pr-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-[#1CBCE8] outline-none"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
        />
      </div>

      {/* TABLA */}
      <div className="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
        <table className="min-w-full text-sm">
          <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs tracking-wide">
            <tr>
              <th className="px-4 py-2 text-left">Acciones</th>
              <th className="px-4 py-2 text-left">Rol</th>
              <th className="px-4 py-2 text-left">Categoría</th>
              <th className="px-4 py-2 text-left">Carreras asociadas</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
            {items.map((item) => (
              <tr
                key={item.id}
                className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
              >
                {/* ACCIONES */}
                <td className="px-4 py-2">
                  <button
                    onClick={() => fetchItem(item.id)}
                    className="text-[#1CBCE8] hover:text-[#17A8D0] flex items-center gap-1"
                  >
                    ✏️ Editar
                  </button>

                  <button
                    onClick={() => removeOne(item.id, item.position_name)}
                    className="text-red-500 hover:text-red-400 flex items-center gap-1 mt-1"
                  >
                    <Trash2 className="w-4 h-4" /> Eliminar
                  </button>
                </td>

                {/* ROL */}
                <td className="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">
                  {item.position_name}
                </td>

                {/* CATEGORÍA */}
                <td className="px-4 py-2">
                  {item.category ?? '-'}
                </td>

                {/* CARRERAS */}
                <td className="px-4 py-2">
                  {item.careers.length ? (
                    <div className="flex flex-wrap gap-1">
                      {item.careers.map((c) => (
                        <span
                          key={c.id}
                          className="px-2 py-1 rounded-md text-xs font-medium
                                     bg-[#C9F3FF] text-[#0C647A]
                                     dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8]
                                     border border-[#1CBCE8]/30"
                        >
                          {c.name}
                        </span>
                      ))}
                    </div>
                  ) : (
                    <span className="text-gray-400 italic">
                      Sin carrera asignada
                    </span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* PAGINACIÓN */}
      {pagination.last_page > 1 && (
        <div className="flex justify-center mt-6 gap-1">
          {[...Array(pagination.last_page)].map((_, idx) => {
            const page = idx + 1;
            return (
              <button
                key={page}
                onClick={() => fetchPage(`/tech-positions/fetch?page=${page}`)}
                className={`px-3 py-1 rounded-md text-sm transition ${
                  pagination.current_page === page
                    ? 'bg-[#1CBCE8] text-white shadow'
                    : 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600'
                }`}
              >
                {page}
              </button>
            );
          })}
        </div>
      )}
    </div>

    {/* MODAL */}
    {showModal && (
      <TechPositionModal
        open={showModal}
        onClose={() => {
          setShowModal(false);
          setEditItem(null);
        }}
        itemToEdit={editItem}
        careers={careers}
        onSaved={(saved) => {
          const idx = items.findIndex((i) => i.id === saved.id);
          if (idx >= 0) {
            const next = [...items];
            next[idx] = saved;
            setItems(next);
          } else {
            setItems([saved, ...items]);
          }
        }}
      />
    )}
  </AppLayout>
);


}
