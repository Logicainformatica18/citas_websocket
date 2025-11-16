import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit } from "lucide-react";
import MethodologyModal from "./MethodologyModal";

const breadcrumbs: BreadcrumbItem[] = [{ title: "Metodologías", href: "/methodologies" }];

type Methodology = {
  id: number;
  name: string;
  context_id?: number | null;
  enabled: boolean;
  created_at?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

export default function MethodologiesIndex() {
  const { methodologies: initialPagination, contexts } = usePage<{
    methodologies: Pagination<Methodology>;
    contexts: { id: number; search_context: string }[];
  }>().props;

  const [items, setItems] = useState<Methodology[]>([]);
  const [pagination, setPagination] = useState<Pagination<Methodology>>(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<Methodology | null>(null);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
    setMounted(true);
  }, [initialPagination]);

  // 🔁 Debounce de búsqueda
  useEffect(() => {
    if (!mounted) return;
    if (search.trim() === "") return;

    const delay = setTimeout(() => {
      fetchPage(`/methodologies/fetch?search=${encodeURIComponent(search)}`);
    }, 500);

    return () => clearTimeout(delay);
  }, [search, mounted]);

  const fetchPage = async (url: string) => {
    try {
      setLoading(true);
      const res = await axios.get(url);
      const data = res.data;
      setItems(data.data);
      setPagination(data);
    } catch {
      Swal.fire("Error", "No se pudo cargar la página.", "error");
    } finally {
      setLoading(false);
    }
  };

  /** 🚦 Cambiar estado enabled */
  const toggleEnabled = async (id: number, current: boolean) => {
    try {
      await axios.patch(`/methodologies/${id}/toggle`, {
        enabled: !current,
      });

      setItems((prev) =>
        prev.map((m) =>
          m.id === id ? { ...m, enabled: !current } : m
        )
      );
    } catch {
      Swal.fire("Error", "No se pudo cambiar el estado.", "error");
    }
  };

  /** 🗑️ Eliminar */
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
      await axios.delete(`/methodologies/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      Swal.fire("Eliminado", "Metodología eliminada correctamente.", "success");
    } catch {
      Swal.fire("Error", "No se pudo eliminar la metodología.", "error");
    }
  };

  const openEdit = (m: Methodology) => {
    setEditing(m);
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
          Metodologías
        </h1>

        {/* 🔍 Búsqueda */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-4">
          <div className="relative w-full sm:w-64">
            <Search className="absolute left-3 top-2.5 text-gray-400 w-4 h-4" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Buscar metodología..."
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
            <Plus className="w-4 h-4" /> Nueva Metodología
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
                <th className="px-4 py-2">Activo</th>
                <th className="px-4 py-2">Creado</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-300 dark:divide-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={5} className="text-center py-6 text-slate-500">
                    Cargando...
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={5} className="text-center py-6 text-slate-500">
                    No hay metodologías registradas.
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
                        {ctx ? ctx.search_context : "-"}
                      </td>

                      {/* 🔥 SWITCH ACTIVAR/DESACTIVAR */}
                      <td className="px-4 py-2 text-center">
                        <label className="inline-flex items-center cursor-pointer">
                          <input
                            type="checkbox"
                            className="sr-only peer"
                            checked={item.enabled}
                            onChange={() => toggleEnabled(item.id, item.enabled)}
                          />
                          <div className="
                            w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full 
                            peer dark:bg-gray-700 peer-checked:bg-blue-600
                            relative transition
                          ">
                            <span
                              className="
                                absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full 
                                transition-all peer-checked:translate-x-5 shadow
                              "
                            ></span>
                          </div>
                        </label>
                      </td>

                      <td className="px-4 py-2 text-gray-600 dark:text-gray-300">
                        {new Date(item.created_at || "").toLocaleDateString("es-PE")}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* PAGINACIÓN */}
        {pagination.last_page > 1 && (
          <div className="flex justify-center mt-6 gap-1">
            {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
              .map((page) => (
                <button
                  key={page}
                  onClick={() =>
                    fetchPage(
                      `/methodologies/fetch?page=${page}&search=${encodeURIComponent(search)}`
                    )
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
              ))}
          </div>
        )}

        {/* MODAL */}
        {showModal && (
          <MethodologyModal
            open={showModal}
            onClose={handleModalClose}
            onCreated={() => fetchPage("/methodologies/fetch")}
            editing={editing}
            contexts={contexts}
          />
        )}
      </div>
    </AppLayout>
  );
}
