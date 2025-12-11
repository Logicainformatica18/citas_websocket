import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit,Award } from "lucide-react";
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
    <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

      {/* HEADER ISIL */}
      <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <Award className="w-6 h-6 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">Lenguajes</span>
        </h1>

        <button
          onClick={() => {
            setEditing(null);
            setShowModal(true);
          }}
          className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow transition"
        >
          Nuevo Lenguaje
        </button>
      </div>

      {/* SEARCHBOX */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6 items-start sm:items-center">
        <div className="relative w-full sm:w-80">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
          <input
            type="text"
            placeholder="Buscar lenguaje..."
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

          {/* HEADER ISIL */}
          <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs tracking-wide">
            <tr>
              <th className="px-4 py-2 text-left">Acciones</th>
              <th className="px-4 py-2 text-left">Nombre</th>
              <th className="px-4 py-2 text-left">Contexto</th>
              <th className="px-4 py-2 text-center">Estado</th>
              <th className="px-4 py-2 text-left">Creado</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">

            {loading ? (
              <tr>
                <td colSpan={5} className="py-6 text-center text-gray-500">
                  Cargando…
                </td>
              </tr>
            ) : items.length === 0 ? (
              <tr>
                <td colSpan={5} className="py-6 text-center text-gray-500">
                  No hay lenguajes registrados.
                </td>
              </tr>
            ) : (
              items.map((item) => {
                const ctx = contexts.find((c) => c.id === item.context_id);

                return (
                  <tr
                    key={item.id}
                    className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
                  >
                    {/* ACCIONES */}
                    <td className="px-4 py-3 whitespace-nowrap">
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

                    {/* CONTEXTO como CHIP ISIL */}
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

                    {/* 🔵 SWITCH ISIL (Manteniendo tu comportamiento original) */}
                    <td className="px-4 py-3 text-center">
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={item.enabled === 1}
                          onChange={() => toggleEnabled(item)}
                          className="sr-only peer"
                        />

                        {/* Fondo del switch */}
                        <div className="
                          w-11 h-6 rounded-full transition 
                          bg-gray-300 peer-checked:bg-[#1CBCE8]
                          dark:bg-gray-700 dark:peer-checked:bg-[#1CBCE8]
                        " />

                        {/* Botón del switch */}
                        <div className="
                          absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow
                          transition peer-checked:translate-x-5
                        " />
                      </label>
                    </td>

                    {/* FECHA */}
                    <td className="px-4 py-3 text-gray-600 dark:text-gray-300">
                      {formatDate(item.created_at)}
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {/* PAGINACIÓN ISIL */}
      {pagination.last_page > 1 && (
        <div className="flex justify-center mt-6 gap-1">
          {(() => {
            const pages = [];
            const total = pagination.last_page;
            const current = pagination.current_page;

            const add = (p: number) => {
              pages.push(
                <button
                  key={p}
                  onClick={() =>
                    fetchPage(`/languages/fetch?page=${p}&search=${encodeURIComponent(search)}`)
                  }
                  className={`px-3 py-1 rounded-md text-sm transition
                    ${
                      current === p
                        ? "bg-[#1CBCE8] text-white shadow"
                        : "bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600"
                    }
                  `}
                >
                  {p}
                </button>
              );
            };

            if (current > 3) add(1);
            if (current > 4) pages.push(<span key="dots1" className="px-2 text-gray-400">…</span>);

            for (let p = current - 2; p <= current + 2; p++) {
              if (p >= 1 && p <= total) add(p);
            }

            if (current < total - 3) pages.push(<span key="dots2" className="px-2 text-gray-400">…</span>);
            if (current < total - 2) add(total);

            return pages;
          })()}
        </div>
      )}

      {/* MODAL */}
      {showModal && (
        <LanguageModal
          open={showModal}
          onClose={handleModalClose}
          onCreated={() => fetchPage("/languages/fetch")}
          editing={editing}
          contexts={contexts}
        />
      )}

    </div>
  </AppLayout>
);



}
