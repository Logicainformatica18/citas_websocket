import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus } from "lucide-react";
import LanguageModal from "./LanguageModal";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Lenguajes", href: "/languages" },
];

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
  const { languages: initialPagination } = usePage<{ languages: Pagination<Language> }>().props;

  const [items, setItems] = useState<Language[]>([]);
  const [pagination, setPagination] = useState<Pagination<Language>>(initialPagination);
  const [showModal, setShowModal] = useState(false);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

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
      const res = await axios.get(url);
      const norm = normalizePagePayload(res?.data ?? null);
      setItems(norm.data);
      setPagination(norm);
    } catch (e) {
      Swal.fire("Error", "No se pudo cargar la página.", "error");
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

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">
          Lenguajes
        </h1>

        <div className="flex items-center gap-2 mb-4">
          <button
            onClick={() => setShowModal(true)}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 inline-flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Nuevo Lenguaje
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border-collapse">
            <thead className="bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 uppercase text-sm">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">Creado</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-300 dark:divide-slate-700">
              {items.map((item) => (
                <tr
                  key={item.id}
                  className="hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                  <td className="px-4 py-2 whitespace-nowrap">
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
                  <td className="px-4 py-2 text-gray-600 dark:text-gray-300">
                    {formatDate(item.created_at)}
                  </td>
                </tr>
              ))}

              {items.length === 0 && (
                <tr>
                  <td
                    className="px-4 py-6 text-center text-slate-500 dark:text-slate-400"
                    colSpan={3}
                  >
                    No hay lenguajes registrados.
                  </td>
                </tr>
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
                  {isGap && (
                    <span className="px-2 py-1 text-slate-400">…</span>
                  )}
                  <button
                    onClick={() => fetchPage(`/languages/fetch?page=${page}`)}
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
          onClose={() => setShowModal(false)}
          onCreated={() => fetchPage("/languages/fetch")}
        />
      )}
    </AppLayout>
  );
}
