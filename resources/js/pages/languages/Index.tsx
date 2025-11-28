import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit } from "lucide-react";
import LanguageModal from "./languageModal";

const breadcrumbs: BreadcrumbItem[] = [{ title: "Lenguajes", href: "/languages" }];

// Helper para formatear fechas
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

type Language = {
  id: number;
  name: string;
  context_id?: number | null;
  enabled: number;   // 👈 nuevo
  created_at?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function LanguagesIndex() {
  const { languages: initialPagination, contexts } = usePage<{
    languages: Pagination<Language>;
    contexts: { id: number; role_name: string; search_context: string }[];
  }>().props;

  const [items, setItems] = useState<Language[]>([]);
  const [pagination, setPagination] = useState<Pagination<Language>>(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<Language | null>(null);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

useEffect(() => {
  const delay = setTimeout(() => {
    if (search.trim() === "") {
      fetchPage("/languages/fetch"); // 👈 recarga el listado completo
    } else {
      fetchPage(`/languages/fetch?search=${encodeURIComponent(search)}`);
    }
  }, 400);

  return () => clearTimeout(delay);
}, [search]);



  const normalizePagePayload = (payload: any): Pagination<Language> => {
    const pager = payload?.languages ?? payload ?? {};
    const data: Language[] = Array.isArray(pager) ? pager : pager?.data ?? [];
    return {
      data,
      current_page: pager?.current_page ?? 1,
      last_page: pager?.last_page ?? 1,
      next_page_url: pager?.next_page_url ?? null,
      prev_page_url: pager?.prev_page_url ?? null,
    };
  };

  const fetchPage = async (url: string) => {
    try {
      setLoading(true);
      const res = await axios.get(url);
      const norm = normalizePagePayload(res?.data ?? null);
      setItems(norm.data);
      setPagination(norm);
    } catch (e) {
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
      await axios.delete(`/languages/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      Swal.fire("Eliminado", "Lenguaje eliminado correctamente.", "success");
    } catch (e) {
      Swal.fire("Error", "No se pudo eliminar el lenguaje.", "error");
    }
  };

  const openEdit = (lang: Language) => {
    setEditing(lang);
    setShowModal(true);
  };

  const handleModalClose = () => {
    setEditing(null);
    setShowModal(false);
  };
const toggleEnabled = async (lang: Language) => {
  try {
    const newValue = lang.enabled === 1 ? 0 : 1;

    await axios.patch(`/languages/${lang.id}/toggle`, {
      enabled: newValue,
    });

    setItems((prev) =>
      prev.map((i) =>
        i.id === lang.id ? { ...i, enabled: newValue } : i
      )
    );
  } catch (e) {
    Swal.fire("Error", "No se pudo actualizar el estado.", "error");
  }
};

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
          Lenguajes
        </h1>

        {/* 🔍 Barra de acciones */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-4">
          <div className="relative w-full sm:w-64">
            <Search className="absolute left-3 top-2.5 text-gray-400 w-4 h-4" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Buscar lenguaje..."
              className="pl-9 pr-3 py-2 w-full rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-gray-900 dark:text-gray-200 focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <button
            onClick={() => {
              setEditing(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 inline-flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Nuevo Lenguaje
          </button>
        </div>

        {/* Tabla */}
        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border-collapse">
            <thead className="bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 uppercase text-sm">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">Contexto</th>
                <th className="px-4 py-2">Activado</th>

                <th className="px-4 py-2">Creado</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-300 dark:divide-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={4} className="text-center py-6 text-slate-500">
                    Cargando...
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={4} className="text-center py-6 text-slate-500">
                    No hay lenguajes registrados.
                  </td>
                </tr>
              ) : (
                items.map((item) => {
                  const ctx = contexts.find((c) => c.id === item.context_id);
                  return (
                    <tr
                      key={item.id}
                      className="hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    >
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
                      <td className="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">
                        {item.name}
                      </td>
                      <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                        {ctx ? `${ctx.search_context} ` : "-"}
                      </td>
                      <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                  <label className="inline-flex items-center cursor-pointer">
  <input
    type="checkbox"
    checked={item.enabled === 1}
    onChange={() => toggleEnabled(item)}
    className="sr-only peer"
  />

  <div className="relative w-11 h-6 bg-gray-300 rounded-full peer-checked:bg-green-500 transition">
    <span className="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
  </div>

  <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">
    {item.enabled ? "Activo" : "Inactivo"}
  </span>
</label>


                      </td>
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

        {/* Paginación */}
        <div className="flex justify-center mt-6 gap-1">
          {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
            .filter(
              (page) =>
                page <= 2 ||
                page >= pagination.last_page - 1 ||
                (page >= pagination.current_page - 2 &&
                  page <= pagination.current_page + 2)
            )
            .map((page, idx, arr) => {
              const prev = arr[idx - 1];
              const isGap = prev && page - prev > 1;
              return (
                <span key={page} className="flex">
                  {isGap && <span className="px-2 py-1 text-slate-400">…</span>}
                  <button
                    onClick={() =>
                      fetchPage(`/languages/fetch?page=${page}&search=${encodeURIComponent(search)}`)
                    }
                    className={`px-3 py-1 rounded text-sm font-medium transition ${
                      pagination.current_page === page
                        ? "bg-blue-600 text-white"
                        : "bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600"
                    }`}
                    disabled={pagination.current_page === page}
                  >
                    {page}
                  </button>
                </span>
              );
            })}
        </div>
      </div>

      {showModal && (
        <LanguageModal
          open={showModal}
          onClose={handleModalClose}
          onCreated={() => fetchPage("/languages/fetch")}
          editing={editing}
          contexts={contexts} // 👈 Contextos disponibles
        />
      )}
    </AppLayout>
  );
}
