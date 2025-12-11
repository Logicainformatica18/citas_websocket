import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit,Award} from "lucide-react";
import TechnologyModal from "./TechnologyModal";

const breadcrumbs: BreadcrumbItem[] = [{ title: "Tecnologías", href: "/technologies" }];

// Helper fecha
function formatDate(dateString?: string | null): string {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return isNaN(date.getTime())
    ? "-"
    : date.toLocaleDateString("es-PE", {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
}

type Technology = {
  id: number;
  name: string;
  category_id?: number | null;
  context_id?: number | null;
  enabled: boolean;
  created_at?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function TechnologiesIndex() {
  const { technologies: initialPagination, contexts, categories } = usePage<{
    technologies: Pagination<Technology>;
    contexts: { id: number; search_context: string }[];
    categories: { id: number; name: string }[];
  }>().props;

  const [items, setItems] = useState<Technology[]>([]);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<Technology | null>(null);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);
  const [mounted, setMounted] = useState(false);

  /** 🚦 Activar/Desactivar */
  const toggleEnabled = async (tech: Technology) => {
    try {
      const res = await axios.patch(`/technologies/${tech.id}/toggle`);
      const updated = res.data.enabled;

      setItems((prev) =>
        prev.map((i) =>
          i.id === tech.id ? { ...i, enabled: updated } : i
        )
      );
    } catch {
      Swal.fire("Error", "No se pudo cambiar el estado.", "error");
    }
  };

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
    setMounted(true);
  }, [initialPagination]);

useEffect(() => {
  if (!mounted) return;

  const delay = setTimeout(() => {
    if (search.trim() === "") {
      fetchPage("/technologies/fetch");   // ← recarga normal
    } else {
      fetchPage(`/technologies/fetch?search=${encodeURIComponent(search)}`);
    }
  }, 500);

  return () => clearTimeout(delay);
}, [search, mounted]);


const normalizePagePayload = (payload: any): Pagination<Technology> => {
  const pager = payload?.technologies ?? payload;

  return {
    data: pager.data ?? [],
    current_page: pager.current_page ?? 1,
    last_page: pager.last_page ?? 1,
    next_page_url: pager.next_page_url ?? null,
    prev_page_url: pager.prev_page_url ?? null,
  };
};


  const fetchPage = async (url: string) => {
    try {
      setLoading(true);
      const res = await axios.get(url);
      const norm = normalizePagePayload(res?.data ?? null);

      setItems(norm.data);
      setPagination(norm);
    } catch {
      Swal.fire("Error", "No se pudo cargar la página.", "error");
    } finally {
      setLoading(false);
    }
  };

  const removeOne = async (id: number, name: string) => {
    const confirm = await Swal.fire({
      title: `¿Eliminar "${name}"?`,
      text: "Esta acción no se puede deshacer.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      background: document.documentElement.classList.contains("dark") ? "#1e293b" : "#fff",
      color: document.documentElement.classList.contains("dark") ? "#fff" : "#000",
    });

    if (!confirm.isConfirmed) return;

    try {
      await axios.delete(`/technologies/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      Swal.fire("Eliminado", "Tecnología eliminada correctamente.", "success");
    } catch {
      Swal.fire("Error", "No se pudo eliminar la tecnología.", "error");
    }
  };

  const openEdit = (tech: Technology) => {
    setEditing(tech);
    setShowModal(true);
  };

  const handleModalClose = () => {
    setEditing(null);
    setShowModal(false);
  };

return (
  <AppLayout breadcrumbs={breadcrumbs}>
    <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

      {/* HEADER ISIL */}
      <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <Award className="w-6 h-6 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">Tecnologías</span>
        </h1>

        <button
          onClick={() => {
            setEditing(null);
            setShowModal(true);
          }}
          className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow transition"
        >
          Nueva Tecnología
        </button>
      </div>

      {/* SEARCHBOX */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6 items-start sm:items-center">
        <div className="relative w-full sm:w-80">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
          <input
            type="text"
            placeholder="Buscar tecnología..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="
              w-full pl-9 pr-3 py-2 rounded-md
              bg-white dark:bg-gray-800
              border border-gray-300 dark:border-gray-700
              text-gray-900 dark:text-gray-100
              focus:ring-2 focus:ring-[#1CBCE8] outline-none
            "
          />
        </div>
      </div>

      {/* TABLA ISIL */}
      <div className="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
        <table className="min-w-full text-sm">

          {/* HEADER TABLA ISIL */}
          <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs tracking-wide">
            <tr>
              <th className="px-4 py-2 text-left">Acciones</th>
              <th className="px-4 py-2 text-left">Nombre</th>
              <th className="px-4 py-2 text-left">Categoría</th>
              <th className="px-4 py-2 text-left">Contexto</th>
              <th className="px-4 py-2 text-center">Estado</th>
              <th className="px-4 py-2 text-left">Creado</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">

            {/* Loading */}
            {loading && (
              <tr>
                <td colSpan={6} className="py-6 text-center text-gray-500">Cargando…</td>
              </tr>
            )}

            {/* Empty */}
            {!loading && items.length === 0 && (
              <tr>
                <td colSpan={6} className="py-6 text-center text-gray-500">
                  No hay tecnologías registradas.
                </td>
              </tr>
            )}

            {/* Items */}
            {!loading &&
              items.length > 0 &&
              items.map((item) => {
                const ctx = contexts.find((c) => c.id === item.context_id);
                const cat = categories.find((c) => c.id === item.category_id);

                return (
                  <tr
                    key={item.id}
                    className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
                  >
                    {/* ACCIONES */}
                    <td className="px-4 py-3">
                      <button
                        onClick={() => openEdit(item)}
                        className="text-[#1CBCE8] hover:text-[#17A8D0] flex items-center gap-1"
                      >
                        <Edit className="w-4 h-4" /> Editar
                      </button>

                      <button
                        onClick={() => removeOne(item.id, item.name)}
                        className="text-red-500 hover:text-red-400 flex items-center gap-1 mt-1"
                      >
                        <Trash2 className="w-4 h-4" /> Eliminar
                      </button>
                    </td>

                    {/* NOMBRE */}
                    <td className="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">
                      {item.name}
                    </td>

                    {/* CATEGORÍA CHIP ISIL */}
                    <td className="px-4 py-3">
                      {cat ? (
                        <span
                          className="
                            px-2 py-1 rounded-md text-xs font-medium
                            bg-[#C9F3FF] text-[#0C647A]
                            dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8]
                            border border-[#1CBCE8]/30
                          "
                        >
                          {cat.name}
                        </span>
                      ) : (
                        <span className="text-gray-400">—</span>
                      )}
                    </td>

                    {/* CONTEXTO */}
                    <td className="px-4 py-3">
                      {ctx ? (
                        <span
                          className="
                            px-2 py-1 rounded-md text-xs font-medium
                            bg-[#C9F3FF] text-[#0C647A]
                            dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8]
                            border border-[#1CBCE8]/30
                          "
                        >
                          {ctx.search_context}
                        </span>
                      ) : (
                        <span className="text-gray-400">—</span>
                      )}
                    </td>

               <td className="px-4 py-3 text-center">
  <label className="relative inline-flex items-center cursor-pointer">
    <input
      type="checkbox"
      checked={!!item.enabled}

      onChange={() => toggleEnabled(item)}
      className="sr-only peer"
    />

    {/* Fondo del switch */}
    <div
      className="
        w-12 h-6 rounded-full transition-colors
        bg-gray-300 peer-checked:bg-[#1CBCE8]
        dark:bg-gray-700 dark:peer-checked:bg-[#1CBCE8]
      "
    />

    {/* Bolita */}
    <div
      className="
        absolute left-1 top-1 w-4 h-4 rounded-full bg-white shadow
        transition-all duration-300
        peer-checked:translate-x-6
      "
    />
  </label>

  {/* ESTADO textual para que se vea más claro */}
  <div className="text-xs mt-1 text-gray-600 dark:text-gray-300">
    {item.enabled ? "Activo" : "Inactivo"}
  </div>
</td>


                    {/* FECHA */}
                    <td className="px-4 py-3 text-gray-600 dark:text-gray-300">
                      {formatDate(item.created_at)}
                    </td>
                  </tr>
                );
              })}
          </tbody>
        </table>
      </div>

      {/* PAGINACIÓN ISIL */}
      {pagination.last_page > 1 && (
        <div className="flex justify-center mt-6 gap-1">
          {(() => {
            const pages = [];
            const current = pagination.current_page;
            const total = pagination.last_page;

            const addPage = (p: number) =>
              pages.push(
                <button
                  key={p}
                  onClick={() =>
                    fetchPage(
                      `/technologies/fetch?page=${p}&search=${encodeURIComponent(search)}`
                    )
                  }
                  className={`px-3 py-1 rounded-md text-sm transition
                    ${
                      current === p
                        ? "bg-[#1CBCE8] text-white shadow"
                        : "bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600"
                    }
                  `}
                >
                  {p}
                </button>
              );

            if (current > 3) addPage(1);
            if (current > 4) pages.push(<span key="dots1" className="px-2 text-gray-400">…</span>);

            for (let p = current - 2; p <= current + 2; p++) {
              if (p >= 1 && p <= total) addPage(p);
            }

            if (current < total - 3) pages.push(<span key="dots2" className="px-2 text-gray-400">…</span>);
            if (current < total - 2) addPage(total);

            return pages;
          })()}
        </div>
      )}

      {/* MODAL */}
      {showModal && (
        <TechnologyModal
          open={showModal}
          onClose={handleModalClose}
          onCreated={() => fetchPage("/technologies/fetch")}
          editing={editing}
          categories={categories}
          contexts={contexts}
        />
      )}
    </div>
  </AppLayout>
);


}
