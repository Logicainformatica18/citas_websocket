import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Search, Edit, Plus, Database } from "lucide-react";
import MarketEntityModal from "./MarketEntityModal";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Market Entities", href: "/market-entities" },
];

type MarketEntity = {
  id: number;
  name: string;
  slug: string;
  entity_type: string;
  origin?: string | null;
  category?: string | null;
  vendor?: string | null;
  level?: string | null;
  has_trend: boolean;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function MarketEntitiesIndex() {
  const { entities: initialPagination } = usePage<{
    entities: Pagination<MarketEntity>;
  }>().props;

  const [items, setItems] = useState<MarketEntity[]>([]);
  const [pagination, setPagination] =
    useState<Pagination<MarketEntity>>(initialPagination);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<MarketEntity | null>(null);

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  useEffect(() => {
    const delay = setTimeout(() => {
      fetchPage(
        `/market-entities/fetch${
          search ? `?search=${encodeURIComponent(search)}` : ""
        }`
      );
    }, 400);

    return () => clearTimeout(delay);
  }, [search]);

  const normalizePagePayload = (payload: any): Pagination<MarketEntity> => {
    const pager = payload.entities ?? payload;
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
    });

    if (!confirm.isConfirmed) return;

    try {
      await axios.delete(`/market-entities/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));
      Swal.fire("Eliminado", "Entidad eliminada correctamente.", "success");
    } catch {
      Swal.fire("Error", "No se pudo eliminar.", "error");
    }
  };

  const toggleTrend = async (entity: MarketEntity) => {
    try {
      const res = await axios.patch(
        `/market-entities/${entity.id}/toggle-trend`
      );

      setItems((prev) =>
        prev.map((i) =>
          i.id === entity.id ? { ...i, has_trend: res.data.has_trend } : i
        )
      );
    } catch {
      Swal.fire("Error", "No se pudo cambiar estado.", "error");
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

        {/* HEADER */}
        <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
          <h1 className="text-3xl font-semibold flex items-center gap-2">
            <Database className="w-6 h-6 text-[#1CBCE8]" />
            <span className="text-[#0C647A] dark:text-[#1CBCE8]">
              Market Entities
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
            Nueva Entidad
          </button>
        </div>

        {/* SEARCH */}
        <div className="mb-6">
          <div className="relative w-full sm:w-80">
            <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
            <input
              type="text"
              placeholder="Buscar entidad..."
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
                <th className="px-4 py-2 text-left">Nombre</th>
                <th className="px-4 py-2 text-left">Tipo</th>
                <th className="px-4 py-2 text-left">Categoría</th>
                <th className="px-4 py-2 text-center">Trend</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">

              {loading ? (
                <tr>
                  <td colSpan={5} className="py-6 text-center">
                    Cargando…
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={5} className="py-6 text-center">
                    No hay entidades registradas.
                  </td>
                </tr>
              ) : (
                items.map((item) => (
                  <tr key={item.id}
                    className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
                  >
                    <td className="px-4 py-3">
                      <button
                        onClick={() => {
                          setEditing(item);
                          setShowModal(true);
                        }}
                        className="text-[#1CBCE8] flex items-center gap-1"
                      >
                        <Edit className="w-4 h-4" /> Editar
                      </button>

                      <button
                        onClick={() => removeOne(item.id, item.name)}
                        className="text-red-500 flex items-center gap-1 mt-1"
                      >
                        <Trash2 className="w-4 h-4" /> Eliminar
                      </button>
                    </td>

                    <td className="px-4 py-3 font-semibold">
                      {item.name}
                    </td>

                    <td className="px-4 py-3">
                      {item.entity_type}
                    </td>

                    <td className="px-4 py-3">
                      {item.category ?? "—"}
                    </td>

                    <td className="px-4 py-3 text-center">
                      <input
                        type="checkbox"
                        checked={item.has_trend}
                        onChange={() => toggleTrend(item)}
                      />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

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
                        `/market-entities/fetch?page=${p}${
                          search ? `&search=${encodeURIComponent(search)}` : ""
                        }`
                      )
                    }
                    className={`px-3 py-1 rounded-md text-sm transition
                      ${
                        current === p
                          ? "bg-[#1CBCE8] text-white shadow"
                          : "bg-gray-200 dark:bg-gray-700"
                      }
                    `}
                  >
                    {p}
                  </button>
                );

              if (total <= 7) {
                for (let i = 1; i <= total; i++) addPage(i);
              } else {
                if (current > 3) {
                  addPage(1);
                  pages.push(<span key="dots1">…</span>);
                }

                for (let i = current - 1; i <= current + 1; i++) {
                  if (i > 0 && i <= total) addPage(i);
                }

                if (current < total - 2) {
                  pages.push(<span key="dots2">…</span>);
                  addPage(total);
                }
              }

              return pages;
            })()}
          </div>
        )}

        {showModal && (
          <MarketEntityModal
            open={showModal}
            onClose={() => setShowModal(false)}
            onSaved={() => fetchPage("/market-entities/fetch")}
            editing={editing}
          />
        )}
      </div>
    </AppLayout>
  );
}
