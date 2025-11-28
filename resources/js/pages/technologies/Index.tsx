import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit } from "lucide-react";
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
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
          Tecnologías
        </h1>

        {/* 🔍 Barra de búsqueda */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-4">

          <div className="relative w-full sm:w-64">
            <Search className="absolute left-3 top-2.5 text-gray-400 w-4 h-4" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Buscar tecnología..."
              className="pl-9 pr-3 py-2 w-full rounded
                border border-slate-300 dark:border-slate-700
                bg-white dark:bg-slate-800
                text-sm text-gray-900 dark:text-gray-200
                focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <button
            onClick={() => {
              setEditing(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 inline-flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Nueva Tecnología
          </button>
        </div>

        {/* TABLA */}
        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border-collapse">
            <thead className="bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 uppercase text-sm">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">Categoría</th>
                <th className="px-4 py-2">Contexto</th>
                <th className="px-4 py-2">Activo</th>
                <th className="px-4 py-2">Creado</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-slate-300 dark:divide-slate-700">

              {loading ? (
                <tr>
                  <td colSpan={6} className="text-center py-6 text-slate-500">
                    Cargando...
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={6} className="text-center py-6 text-slate-500">
                    No hay tecnologías registradas.
                  </td>
                </tr>
              ) : (
                items.map((item) => {
                  const ctx = contexts.find((c) => c.id === item.context_id);
                  const cat = categories.find((c) => c.id === item.category_id);

                  return (
                    <tr key={item.id} className="hover:bg-slate-100 dark:hover:bg-slate-800 transition">

                      {/* ACCIONES */}
                      <td className="px-4 py-2 whitespace-nowrap flex gap-2">
                        <button
                          onClick={() => openEdit(item)}
                          className="text-blue-500 hover:text-blue-700 inline-flex items-center gap-1"
                        >
                          <Edit className="w-4 h-4" /> Editar
                        </button>
                        <button
                          onClick={() => removeOne(item.id, item.name)}
                          className="text-red-500 hover:text-red-700 inline-flex items-center gap-1"
                        >
                          <Trash2 className="w-4 h-4" /> Eliminar
                        </button>
                      </td>

                      {/* NOMBRE */}
                      <td className="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">
                        {item.name}
                      </td>

                      {/* CATEGORÍA */}
                      <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                        {cat ? cat.name : "-"}
                      </td>

                      {/* CONTEXTO */}
                      <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                        {ctx ? ctx.search_context : "-"}
                      </td>

                      {/* SWITCH ENABLED */}
                      <td className="px-4 py-2 text-center">
                        <label className="relative inline-flex items-center cursor-pointer">
                          <input
                            type="checkbox"
                            checked={item.enabled}
                            onChange={() => toggleEnabled(item)}
                            className="sr-only peer"
                          />
                          <div
                            className="
                              w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full
                              peer peer-checked:bg-blue-600
                              dark:bg-slate-700 dark:peer-checked:bg-blue-500
                              transition
                            "
                          ></div>
                          <div
                            className="
                              absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow
                              peer-checked:translate-x-5 transition
                            "
                          ></div>
                        </label>
                      </td>

                      {/* FECHA */}
                      <td className="px-4 py-2 text-gray-600 dark:text-gray-300">
                        {formatDate(item.created_at)}
                      </td>

                    </tr>
                  );
                })
              )}

            </tbody>
          </table>
        </div>

      {/* PAGINACIÓN INTELIGENTE */}
<div className="flex justify-center mt-6 gap-1">

  {(() => {
    const pages = [];
    const total = pagination.last_page;
    const current = pagination.current_page;

    const showPage = (page: number) => {
      pages.push(
        <button
          key={page}
          onClick={() => fetchPage(`/technologies/fetch?page=${page}&search=${encodeURIComponent(search)}`)}
          className={`px-3 py-1 rounded text-sm font-medium transition ${
            current === page
              ? "bg-blue-600 text-white"
              : "bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600"
          }`}
          disabled={current === page}
        >
          {page}
        </button>
      );
    };

    // Mostrar primera página
    if (current > 3) showPage(1);

    // Mostrar "..."
    if (current > 4)
      pages.push(<span key="start-dots" className="px-2 py-1 text-gray-400">…</span>);

    // Páginas centrales
    for (let p = current - 2; p <= current + 2; p++) {
      if (p >= 1 && p <= total) showPage(p);
    }

    // Mostrar "..."
    if (current < total - 3)
      pages.push(<span key="end-dots" className="px-2 py-1 text-gray-400">…</span>);

    // Mostrar última página
    if (current < total - 2) showPage(total);

    return pages;
  })()}

</div>

      </div>

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
    </AppLayout>
  );
}
