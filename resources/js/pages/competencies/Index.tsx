import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit } from "lucide-react";
import CompetencyModal from "./CompetencyModal";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Competencias", href: "/competencies" },
];

// 📌 Formato de fecha
function formatDate(dateString?: string | null): string {
  if (!dateString) return "-";
  const dt = new Date(dateString);
  return isNaN(dt.getTime())
    ? "-"
    : dt.toLocaleDateString("es-PE", {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
}

type Competency = {
  id: number;
  name: string;
  category?: string | null;
  weight?: number | null;
  career_id?: number | null;
  career_name?: string | null;
  created_at?: string;
  enabled?: number;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

type Career = {
  id: number;
  name: string;
};

export default function CompetenciesIndex() {
  const { competencies: initialPagination, careers } = usePage<{
    competencies: Pagination<Competency>;
    careers: Career[];
  }>().props;

  const [items, setItems] = useState<Competency[]>([]);
  const [pagination, setPagination] =
    useState<Pagination<Competency>>(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<Competency | null>(null);

  const [search, setSearch] = useState("");
  const [careerFilter, setCareerFilter] = useState("");
  const [loading, setLoading] = useState(false);

  // Inicial carga
  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  // 🔁 Debounce búsqueda
useEffect(() => {
  const delay = setTimeout(() => {
    if (search.trim() === "") {
      fetchPage("/competencies/fetch"); // 👈 recarga completa
    } else {
      fetchPage(`/competencies/fetch?search=${encodeURIComponent(search)}`);
    }
  }, 400);

  return () => clearTimeout(delay);
}, [search]);


  const normalizePagePayload = (payload: any): Pagination<Competency> => {
    const pager = payload?.competencies ?? payload ?? {};
    const data: Competency[] = Array.isArray(pager) ? pager : pager?.data ?? [];
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
      const norm = normalizePagePayload(res.data ?? null);

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
      await axios.delete(`/competencies/${id}`);
      setItems((prev) => prev.filter((i) => i.id !== id));

      Swal.fire("Eliminado", "Competencia eliminada correctamente.", "success");
    } catch {
      Swal.fire("Error", "No se pudo eliminar la competencia.", "error");
    }
  };

  const openEdit = (item: Competency) => {
    setEditing(item);
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
          Competencias
        </h1>

        {/* 🔍 Barra filtros */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-4">
          {/* Búsqueda */}
          <div className="relative w-full sm:w-64">
            <Search className="absolute left-3 top-2.5 text-gray-400 w-4 h-4" />
            <input
              type="text"
              placeholder="Buscar competencia..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9 pr-3 py-2 w-full rounded border bg-white dark:bg-slate-800"
            />
          </div>

          {/* Filtro carrera */}
          <select
            value={careerFilter}
            onChange={(e) => setCareerFilter(e.target.value)}
            className="px-3 py-2 rounded border bg-white dark:bg-slate-800"
          >
            <option value="">Todas las carreras</option>
            {careers.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>

          {/* Botón nuevo */}
          <button
            onClick={() => {
              setEditing(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Nueva Competencia
          </button>
        </div>

        {/* Tabla */}
        <div className="overflow-x-auto">
          <table className="min-w-full table-auto border-collapse">
            <thead className="bg-slate-200 dark:bg-slate-700">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">Categoría</th>
                <th className="px-4 py-2">Carrera</th>
                <th className="px-4 py-2">Peso</th>

              </tr>
            </thead>

            <tbody className="divide-y dark:divide-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={6} className="text-center py-6">
                    Cargando...
                  </td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={6} className="text-center py-6">
                    No hay competencias registradas.
                  </td>
                </tr>
              ) : (
                items.map((item) => (
                  <tr
                    key={item.id}
                    className="hover:bg-slate-100 dark:hover:bg-slate-800"
                  >
                    <td className="px-4 py-2 flex gap-2">
                      <button
                        onClick={() => openEdit(item)}
                        className="text-blue-500 flex gap-1"
                      >
                        <Edit className="w-4 h-4" /> Editar
                      </button>

                      <button
                        onClick={() => removeOne(item.id, item.name)}
                        className="text-red-500 flex gap-1"
                      >
                        <Trash2 className="w-4 h-4" /> Eliminar
                      </button>
                    </td>

                    <td className="px-4 py-2 font-semibold">{item.name}</td>
                    <td className="px-4 py-2">{item.category ?? "-"}</td>
                    <td className="px-4 py-2">
                      {item.career_name ?? <span className="text-slate-400">—</span>}
                    </td>
                    <td className="px-4 py-2">{item.weight ?? "0"}</td>

                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Paginación */}
        <div className="flex justify-center mt-6 gap-1">
          {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
            .filter(
              (p) =>
                p <= 2 ||
                p >= pagination.last_page - 1 ||
                (p >= pagination.current_page - 2 &&
                  p <= pagination.current_page + 2)
            )
            .map((page, idx, arr) => {
              const prev = arr[idx - 1];
              const isGap = prev && page - prev > 1;

              return (
                <span key={page} className="flex">
                  {isGap && <span className="px-2">…</span>}

                  <button
                    onClick={() =>
                      fetchPage(
                        `/competencies/fetch?page=${page}&search=${search}&career_id=${careerFilter}`
                      )
                    }
                    className={`px-3 py-1 rounded ${
                      pagination.current_page === page
                        ? "bg-blue-600 text-white"
                        : "bg-slate-200 dark:bg-slate-700 hover:bg-slate-300"
                    }`}
                  >
                    {page}
                  </button>
                </span>
              );
            })}
        </div>
      </div>

      {showModal && (
        <CompetencyModal
          open={showModal}
          onClose={handleModalClose}
          onCreated={() => fetchPage("/competencies/fetch")}
          editing={editing}
          careers={careers}
        />
      )}
    </AppLayout>
  );
}
