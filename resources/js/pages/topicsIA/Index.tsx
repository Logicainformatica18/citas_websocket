import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit, RefreshCcw , Sparkles } from "lucide-react";
import TopicModal from "./TopicModal";

// ==============================================
// Tipos
// ==============================================
type TrendTopic = {
  id: number;
  topic_name: string;
  search_query: string;
  category?: string | null;
  subcategory?: string | null;
  importance_weight: number;
  fail_count: number;
  success_count: number;
  last_fail_at?: string | null;
  created_at?: string;
  active: number; // 1 / 0
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

// ==============================================
// Breadcrumbs
// ==============================================
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Topics IA", href: "/topics-ia" },
];

// ==============================================
// Helper: formatear fecha
// ==============================================
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

// ==============================================
// Componente principal
// ==============================================
export default function TopicsIndex() {
  const { topics: initialPagination } = usePage<{
    topics: Pagination<TrendTopic>;
  }>().props;

  const [items, setItems] = useState<TrendTopic[]>(initialPagination.data);
  const [pagination, setPagination] =
    useState<Pagination<TrendTopic>>(initialPagination);

  const [showModal, setShowModal] = useState<boolean>(false);
  const [editing, setEditing] = useState<TrendTopic | null>(null);
  const [search, setSearch] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(false);

  // ==============================================
  // Efecto: refrescar listado cuando cambian props
  // ==============================================
  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  // ==============================================
  // Efecto: búsqueda con debounce
  // ==============================================
  useEffect(() => {
    const delay = setTimeout(() => {
      const url =
        search.trim() === ""
          ? "/topics-ia/fetch"
          : `/topics-ia/fetch?search=${encodeURIComponent(search)}`;

      fetchPage(url);
    }, 450);

    return () => clearTimeout(delay);
  }, [search]);

  // ==============================================
  // Normalizar paginación
  // ==============================================
  const normalizePagePayload = (payload: any): Pagination<TrendTopic> => {
    const pager = payload ?? {};
    return {
      data: pager.data ?? [],
      current_page: pager.current_page ?? 1,
      last_page: pager.last_page ?? 1,
      next_page_url: pager.next_page_url ?? null,
      prev_page_url: pager.prev_page_url ?? null,
    };
  };

  // ==============================================
  // Fetch paginado
  // ==============================================
  const fetchPage = async (url: string) => {
    try {
      setLoading(true);
      const res = await axios.get(url);
      const norm = normalizePagePayload(res?.data);
      setItems(norm.data);
      setPagination(norm);
    } catch (e) {
      Swal.fire("Error", "No se pudo cargar la página.", "error");
    } finally {
      setLoading(false);
    }
  };

  // ==============================================
  // Eliminar Topic
  // ==============================================
  const removeOne = async (id: number, name: string) => {
    const confirm = await Swal.fire({
      title: `¿Eliminar el topic "${name}"?`,
      text: "Esta acción no se puede deshacer.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      background: document.documentElement.classList.contains("dark")
        ? "#1e293b"
        : "#fff",
      color: document.documentElement.classList.contains("dark")
        ? "#fff"
        : "#000",
    });

    if (!confirm.isConfirmed) return;

    try {
      await axios.delete(`/topics-ia/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      Swal.fire("Eliminado", "Topic eliminado correctamente.", "success");
    } catch (e) {
      Swal.fire("Error", "No se pudo eliminar el topic.", "error");
    }
  };

  // ==============================================
  // Toggle activo/inactivo
  // ==============================================
  const toggleActive = async (topic: TrendTopic) => {
    try {
      const newValue = topic.active === 1 ? 0 : 1;

      await axios.patch(`/topics-ia/${topic.id}/toggle`, {
        active: newValue,
      });

      setItems((prev) =>
        prev.map((i) =>
          i.id === topic.id ? { ...i, active: newValue } : i
        )
      );
    } catch (e) {
      Swal.fire("Error", "No se pudo actualizar el estado.", "error");
    }
  };

  // ==============================================
  // Reactivar
  // ==============================================
  const reactivate = async (id: number) => {
    try {
      await axios.patch(`/topics-ia/${id}/reactivate`);
      Swal.fire("Reactivado", "El topic ha sido reactivado.", "success");
      fetchPage("/topics-ia/fetch");
    } catch (e) {
      Swal.fire("Error", "No se pudo reactivar el topic.", "error");
    }
  };

  // ==============================================
  // Abrir modal edición
  // ==============================================
  const openEdit = (topic: TrendTopic) => {
    setEditing(topic);
    setShowModal(true);
  };

  const handleModalClose = () => {
    setEditing(null);
    setShowModal(false);
  };

  // ==============================================
  // Render
  // ==============================================
 return (
  <AppLayout breadcrumbs={breadcrumbs}>
    <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100 transition">

      {/* 🔷 HEADER ISIL */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <Sparkles className="w-7 h-7 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">Topics IA (Tendencias Tecnológicas)</span>
        </h1>

        <button
          onClick={() => {
            setEditing(null);
            setShowModal(true);
          }}
          className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow flex items-center gap-2 transition"
        >
          <Plus className="w-4 h-4" /> Nuevo Topic IA
        </button>
      </div>

      {/* 🔍 Buscador */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-6">
        <div className="relative w-full sm:w-80">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Buscar topic..."
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

      {/* 🔹 TABLA ISIL */}
      <div className="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
        <table className="min-w-full text-sm">

          {/* ENCABEZADO */}
          <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs tracking-wide">
            <tr>
              <th className="px-4 py-2 text-left">Acciones</th>
              <th className="px-4 py-2 text-left">Topic</th>
              <th className="px-4 py-2 text-left">Categoría</th>
              <th className="px-4 py-2 text-center">Activo</th>
              <th className="px-4 py-2 text-center">Fallos</th>
              <th className="px-4 py-2 text-left">Último fallo</th>
              <th className="px-4 py-2 text-center">Éxitos</th>
              <th className="px-4 py-2 text-left">Creado</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
            {loading ? (
              <tr>
                <td colSpan={8} className="py-6 text-center text-gray-500">Cargando…</td>
              </tr>
            ) : items.length === 0 ? (
              <tr>
                <td colSpan={8} className="py-6 text-center text-gray-500">
                  No hay topics registrados.
                </td>
              </tr>
            ) : (
              items.map((item) => (
                <tr
                  key={item.id}
                  className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
                >
                  {/* ACCIONES */}
                  <td className="px-4 py-3 whitespace-nowrap flex flex-col gap-2">
                    <button
                      onClick={() => openEdit(item)}
                      className="text-[#1CBCE8] hover:text-[#17A8D0] flex items-center gap-1"
                    >
                      <Edit className="w-4 h-4" /> Editar
                    </button>

                    <button
                      onClick={() => removeOne(item.id, item.topic_name)}
                      className="text-red-500 hover:text-red-400 flex items-center gap-1"
                    >
                      <Trash2 className="w-4 h-4" /> Eliminar
                    </button>

                    {!item.active && item.fail_count >= 3 && (
                      <button
                        onClick={() => reactivate(item.id)}
                        className="text-green-600 hover:text-green-500 flex items-center gap-1"
                      >
                        <RefreshCcw className="w-4 h-4" /> Reactivar
                      </button>
                    )}
                  </td>

                  {/* TOPIC */}
                  <td className="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">
                    {item.topic_name}
                  </td>

                  {/* CATEGORÍA */}
                  <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                    {item.category ?? "-"} / {item.subcategory ?? "-"}
                  </td>

                  {/* SWITCH ACTIVO */}
                  <td className="px-4 py-3 text-center">
                    <label className="relative inline-flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        checked={item.active === 1}
                        onChange={() => toggleActive(item)}
                        className="sr-only peer"
                      />
                      <div className="
                        w-12 h-6 rounded-full transition-colors
                        bg-gray-300 peer-checked:bg-[#1CBCE8]
                        dark:bg-gray-700 dark:peer-checked:bg-[#1CBCE8]
                      "></div>
                      <div
                        className="
                          absolute left-1 top-1 h-4 w-4 bg-white rounded-full shadow
                          transition-all peer-checked:translate-x-6
                        "
                      ></div>
                    </label>
                  </td>

                  {/* FALLOS */}
                  <td className="px-4 py-3 text-center font-semibold text-red-500">
                    {item.fail_count}
                  </td>

                  {/* ÚLTIMO FALLO */}
                  <td className="px-4 py-3 text-gray-600 dark:text-gray-300">
                    {formatDate(item.last_fail_at)}
                  </td>

                  {/* ÉXITOS */}
                  <td className="px-4 py-3 text-center font-semibold text-green-600 dark:text-green-400">
                    {item.success_count}
                  </td>

                  {/* CREACIÓN */}
                  <td className="px-4 py-3 text-gray-600 dark:text-gray-300">
                    {formatDate(item.created_at)}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* PAGINACIÓN ISIL */}
      <div className="flex justify-center mt-6 gap-1">
        {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
          .filter((p) =>
            p <= 2 ||
            p >= pagination.last_page - 1 ||
            (p >= pagination.current_page - 2 && p <= pagination.current_page + 2)
          )
          .map((p, idx, arr) => {
            const prev = arr[idx - 1];
            const gap = prev && p - prev > 1;

            return (
              <span key={p} className="flex">
                {gap && <span className="px-2 text-gray-400">…</span>}

                <button
                  onClick={() =>
                    fetchPage(`/topics-ia/fetch?page=${p}&search=${encodeURIComponent(search)}`)
                  }
                  className={`
                    px-3 py-1 rounded-md text-sm font-medium transition
                    ${
                      pagination.current_page === p
                        ? "bg-[#1CBCE8] text-white shadow"
                        : "bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200"
                    }
                  `}
                  disabled={pagination.current_page === p}
                >
                  {p}
                </button>
              </span>
            );
          })}
      </div>
    </div>

    {showModal && (
      <TopicModal
        open={showModal}
        onClose={handleModalClose}
        onCreated={() => fetchPage("/topics-ia/fetch")}
        editing={editing}
      />
    )}
  </AppLayout>
);

}
