import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Search, Edit, TrendingUp, Plus } from "lucide-react";
import EntityTrendModal from "./EntityTrendModal";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Entity Trends", href: "/entity-trends" },
];

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

type EntityTrend = {
  id: number;
  trend_name: string;
  trend_score: number;
  entity_name?: string | null;
  match_type?: string | null;
  confidence_score?: number | null;
  source_title?: string | null;
  source_url?: string | null;
  year: number;
  quarter: number;
  created_at?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function EntityTrendsIndex() {
  const { trends: initialPagination } = usePage<{
    trends: Pagination<EntityTrend>;
  }>().props;

  const [items, setItems] = useState<EntityTrend[]>([]);
  const [pagination, setPagination] =
    useState<Pagination<EntityTrend>>(initialPagination);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<EntityTrend | null>(null);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  useEffect(() => {
    const delay = setTimeout(() => {
      if (search.trim() === "") {
        fetchPage("/entity-trends/fetch");
      } else {
        fetchPage(
          `/entity-trends/fetch?search=${encodeURIComponent(search)}`
        );
      }
    }, 400);

    return () => clearTimeout(delay);
  }, [search]);

const normalizePagePayload = (payload: any): Pagination<EntityTrend> => {
  if (!payload) {
    return {
      data: [],
      current_page: 1,
      last_page: 1,
      next_page_url: null,
      prev_page_url: null,
    };
  }

  // Si viene anidado desde Inertia
  const pager = payload.trends ?? payload;

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

    const norm = normalizePagePayload(res.data);

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
      background: document.documentElement.classList.contains("dark")
        ? "#1e293b"
        : "#fff",
      color: document.documentElement.classList.contains("dark")
        ? "#fff"
        : "#000",
    });

    if (!confirm.isConfirmed) return;

    try {
      await axios.delete(`/entity-trends/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      Swal.fire("Eliminado", "Trend eliminado correctamente.", "success");
    } catch (e) {
      Swal.fire("Error", "No se pudo eliminar el trend.", "error");
    }
  };

  const openEdit = (trend: EntityTrend) => {
    setEditing(trend);
    setShowModal(true);
  };

  const handleModalClose = () => {
    setEditing(null);
    setShowModal(false);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

        {/* HEADER */}
        <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
          <h1 className="text-3xl font-semibold flex items-center gap-2">
            <TrendingUp className="w-6 h-6 text-[#1CBCE8]" />
            <span className="text-[#0C647A] dark:text-[#1CBCE8]">
              Entity Trends
            </span>
          </h1>

          <button
            onClick={() => {
              setEditing(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow transition flex items-center gap-2"
          >
            <Plus className="w-4 h-4" />
            Nuevo Trend
          </button>
        </div>

        {/* SEARCH */}
        <div className="mb-6">
          <div className="relative w-full sm:w-80">
            <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
            <input
              type="text"
              placeholder="Buscar trend..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-9 pr-3 py-2 rounded-md
                bg-white dark:bg-gray-800
                border border-gray-300 dark:border-gray-700
                focus:ring-2 focus:ring-[#1CBCE8] outline-none"
            />
          </div>
        </div>

        {/* TABLE */}
        <div className="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
          <table className="min-w-full text-sm">

            <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs">
              <tr>
                <th className="px-4 py-2 text-left">Acciones</th>
                <th className="px-4 py-2 text-left">Trend</th>
                <th className="px-4 py-2 text-left">Entidad</th>
                <th className="px-4 py-2 text-center">Score</th>
                <th className="px-4 py-2 text-center">Confianza</th>
                <th className="px-4 py-2 text-center">Periodo</th>
                <th className="px-4 py-2 text-left">Creado</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">

              {loading ? (
                <tr>
                  <td colSpan={7} className="py-6 text-center text-gray-500">
                    Cargando…
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-6 text-center text-gray-500">
                    No hay trends registrados.
                  </td>
                </tr>
              ) : (
                items.map((item) => (
                  <tr
                    key={item.id}
                    className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
                  >
                    <td className="px-4 py-3">
                      <button
                        onClick={() => openEdit(item)}
                        className="text-[#1CBCE8] hover:text-[#17A8D0] flex items-center gap-1"
                      >
                        <Edit className="w-4 h-4" /> Editar
                      </button>

                      <button
                        onClick={() => removeOne(item.id, item.trend_name)}
                        className="text-red-500 hover:text-red-400 flex items-center gap-1 mt-1"
                      >
                        <Trash2 className="w-4 h-4" /> Eliminar
                      </button>
                    </td>

                    <td className="px-4 py-3 font-semibold">
                      {item.trend_name}
                    </td>

                    <td className="px-4 py-3">
                      {item.entity_name ?? "—"}
                    </td>

                    <td className="px-4 py-3 text-center">
                      {item.trend_score ?? "-"}
                    </td>

                    <td className="px-4 py-3 text-center">
                      {item.confidence_score ?? "-"}
                    </td>

                    <td className="px-4 py-3 text-center">
                      {item.year} Q{item.quarter}
                    </td>

                    <td className="px-4 py-3 text-gray-600 dark:text-gray-300">
                      {formatDate(item.created_at)}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* PAGINACIÓN */}
    {/* PAGINACIÓN */}
{/* PAGINACIÓN INTELIGENTE */}
{pagination.last_page > 1 && (
  <div className="flex justify-center mt-6 gap-1 flex-wrap">
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
                `/entity-trends/fetch?page=${p}${
                  search ? `&search=${encodeURIComponent(search)}` : ""
                }`
              )
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

      // Mostrar siempre primeras 2 páginas
      if (total <= 7) {
        for (let i = 1; i <= total; i++) addPage(i);
      } else {
        if (current > 3) {
          addPage(1);
          addPage(2);
          pages.push(
            <span key="dots-start" className="px-2 text-gray-400">
              …
            </span>
          );
        }

        for (let i = current - 1; i <= current + 1; i++) {
          if (i > 0 && i <= total) addPage(i);
        }

        if (current < total - 2) {
          pages.push(
            <span key="dots-end" className="px-2 text-gray-400">
              …
            </span>
          );
          addPage(total - 1);
          addPage(total);
        }
      }

      return pages;
    })()}
  </div>
)}

        {/* MODAL */}
        {showModal && (
          <EntityTrendModal
            open={showModal}
            onClose={handleModalClose}
            onSaved={() => fetchPage("/entity-trends/fetch")}
            editing={editing}
          />
        )}

      </div>
    </AppLayout>
  );
}
