import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit,Award } from "lucide-react";
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
    <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

      {/* HEADER ISIL */}
      <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <Award className="w-6 h-6 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">Competencias</span>
        </h1>

        <button
          onClick={() => {
            setEditing(null);
            setShowModal(true);
          }}
          className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow transition"
        >
          Nueva Competencia
        </button>
      </div>

      {/* BUSCADOR + FILTRO */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6 items-start sm:items-center">

        {/* Searchbox */}
        <div className="relative w-full sm:w-80">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
          <input
            type="text"
            placeholder="Buscar competencia..."
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

        {/* Filtro carrera */}
        <select
          value={careerFilter}
          onChange={(e) => setCareerFilter(e.target.value)}
          className="
            px-3 py-2 rounded-md
            bg-white dark:bg-gray-800
            border border-gray-300 dark:border-gray-700
            text-gray-900 dark:text-gray-100
            focus:ring-2 focus:ring-[#1CBCE8]
          "
        >
          <option value="">Todas las carreras</option>
          {careers.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>

      </div>

      {/* TABLA ISIL */}
      <div className="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
        <table className="min-w-full text-sm">

          {/* ENCABEZADO AZUL ISIL */}
          <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs tracking-wide">
            <tr>
              <th className="px-4 py-2 text-left">Acciones</th>
              <th className="px-4 py-2 text-left">Nombre</th>
              <th className="px-4 py-2 text-left">Categoría</th>
              <th className="px-4 py-2 text-left">Carrera</th>
              <th className="px-4 py-2 text-left">Peso</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">

            {/* Loading */}
            {loading && (
              <tr>
                <td colSpan={5} className="py-6 text-center text-gray-500">Cargando…</td>
              </tr>
            )}

            {/* Vacío */}
            {!loading && items.length === 0 && (
              <tr>
                <td colSpan={5} className="py-6 text-center text-gray-500">
                  No hay competencias registradas.
                </td>
              </tr>
            )}

            {/* ITEMS */}
            {!loading && items.length > 0 && items.map((item) => (
              <tr
                key={item.id}
                className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
              >
                {/* Acciones */}
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

                {/* Nombre */}
                <td className="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">
                  {item.name}
                </td>

                {/* Categoría */}
                <td className="px-4 py-3">{item.category ?? "-"}</td>

                {/* Carrera con chip ISIL */}
                <td className="px-4 py-3">
                  {item.career_name ? (
                    <span
                      className="
                        px-2 py-1 rounded-md text-xs font-medium
                        bg-[#C9F3FF] text-[#0C647A]
                        dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8]
                        border border-[#1CBCE8]/30
                      "
                    >
                      {item.career_name}
                    </span>
                  ) : (
                    <span className="text-gray-400">—</span>
                  )}
                </td>

                {/* Peso */}
                <td className="px-4 py-3 font-medium">{item.weight ?? "0"}</td>

              </tr>
            ))}

          </tbody>
        </table>
      </div>

      {/* PAGINACIÓN ESTILO ISIL */}
      {pagination.last_page > 1 && (
        <div className="flex justify-center mt-6 gap-1">

          {(() => {
            const pages = [];
            const total = pagination.last_page;
            const current = pagination.current_page;

            const addPage = (p: number) => {
              pages.push(
                <button
                  key={p}
                  onClick={() =>
                    fetchPage(
                      `/competencies/fetch?page=${p}&search=${search}&career_id=${careerFilter}`
                    )
                  }
                  className={`
                    px-3 py-1 rounded-md text-sm transition
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
            };

            if (current > 3) addPage(1);
            if (current > 4)
              pages.push(<span key="dots1" className="px-2 text-gray-400">…</span>);

            for (let p = current - 2; p <= current + 2; p++) {
              if (p >= 1 && p <= total) addPage(p);
            }

            if (current < total - 3)
              pages.push(<span key="dots2" className="px-2 text-gray-400">…</span>);

            if (current < total - 2) addPage(total);

            return pages;
          })()}

        </div>
      )}

      {/* MODAL */}
      {showModal && (
        <CompetencyModal
          open={showModal}
          onClose={handleModalClose}
          onCreated={() => fetchPage("/competencies/fetch")}
          editing={editing}
          careers={careers}
        />
      )}

    </div>
  </AppLayout>
);


}
