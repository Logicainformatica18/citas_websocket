import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Trash2, Plus, Search, Edit,Award } from "lucide-react";
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

useEffect(() => {
  if (!mounted) return;

  const delay = setTimeout(() => {
    if (search.trim() === "") {
      fetchPage("/methodologies/fetch"); // 🔄 recarga completa
    } else {
      fetchPage(`/methodologies/fetch?search=${encodeURIComponent(search)}`);
    }
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
    <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

      {/* HEADER ISIL */}
      <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <Award className="w-6 h-6 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">Metodologías</span>
        </h1>

        <button
          onClick={() => {
            setEditing(null);
            setShowModal(true);
          }}
          className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow transition"
        >
          Nueva Metodología
        </button>
      </div>

      {/* BUSCADOR */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6 items-start sm:items-center">

        {/* Searchbox */}
        <div className="relative w-full sm:w-80">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
          <input
            type="text"
            placeholder="Buscar metodología..."
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

          {/* ENCABEZADO AZUL ISIL */}
          <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs tracking-wide">
            <tr>
              <th className="px-4 py-2 text-left">Acciones</th>
              <th className="px-4 py-2 text-left">Nombre</th>
              <th className="px-4 py-2 text-left">Contexto</th>
              <th className="px-4 py-2 text-left">Estado</th>
              <th className="px-4 py-2 text-left">Creado</th>
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
                  No hay metodologías registradas.
                </td>
              </tr>
            )}

            {/* ITEMS */}
            {!loading && items.length > 0 && items.map((item) => {
              const ctx = contexts.find((c) => c.id === item.context_id);

              return (
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

                  {/* Contexto con chip ISIL */}
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

                  {/* Estado */}
                  <td className="px-4 py-3">
                    {item.enabled === 1 ? (
                      <span className="px-2 py-1 text-xs rounded bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200">
                        Activo
                      </span>
                    ) : (
                      <span className="px-2 py-1 text-xs rounded bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-300">
                        Inactivo
                      </span>
                    )}
                  </td>

                  {/* Fecha */}
                  <td className="px-4 py-3 text-gray-600 dark:text-gray-300">
                    {new Date(item.created_at || "").toLocaleDateString("es-PE")}
                  </td>
                </tr>
              );
            })}

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

            const addPage = (p: number) => {
              pages.push(
                <button
                  key={p}
                  onClick={() =>
                    fetchPage(`/methodologies/fetch?page=${p}&search=${search}`)
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
