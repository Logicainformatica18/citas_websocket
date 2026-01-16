import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import {
  Trash2,
  Plus,
  Search,
  Edit,
  RefreshCcw,
  Sparkles,
  Play,
  Loader2,
} from "lucide-react";
import TopicModal from "./TopicModal";

/* ==============================================
   Tipos
============================================== */
type TrendTopic = {
  id: number;
  topic_name: string;
  search_query: string;

  intent: "certification" | "technology_trend" | "skill" | "workforce" | "mixed";
  execution_mode?: "manual" | "scheduled";
  last_run_status?: "idle" | "running" | "success" | "failed";
  last_run_message?: string | null;

  category?: string | null;
  subcategory?: string | null;
  importance_weight: number;

  fail_count: number;
  success_count: number;
  last_fail_at?: string | null;
  last_success_at?: string | null;
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

/* ==============================================
   Breadcrumbs
============================================== */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Topics IA", href: "/topics-ia" },
];

/* ==============================================
   Helper: fecha
============================================== */
function formatDate(dateString?: string | null): string {
  if (!dateString) return "-";
  const d = new Date(dateString);
  return isNaN(d.getTime())
    ? "-"
    : d.toLocaleDateString("es-PE", {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
}

/* ==============================================
   Componente
============================================== */
export default function TopicsIndex() {
  const { topics: initialPagination } = usePage<{
    topics: Pagination<TrendTopic>;
  }>().props;

  const [items, setItems] = useState(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<TrendTopic | null>(null);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);

  /* ==============================================
     Sync props
  ============================================== */
  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  /* ==============================================
     Search debounce
  ============================================== */
  useEffect(() => {
    const t = setTimeout(() => {
      const url =
        search.trim() === ""
          ? "/topics-ia/fetch"
          : `/topics-ia/fetch?search=${encodeURIComponent(search)}`;

      fetchPage(url);
    }, 450);

    return () => clearTimeout(t);
  }, [search]);

  /* ==============================================
     Fetch
  ============================================== */
  const fetchPage = async (url: string) => {
    try {
      setLoading(true);
      const res = await axios.get(url);
      setItems(res.data.data ?? []);
      setPagination(res.data);
    } catch {
      Swal.fire("Error", "No se pudo cargar la información.", "error");
    } finally {
      setLoading(false);
    }
  };

  /* ==============================================
     Ejecutar tendencia (🔥 NUEVO)
  ============================================== */
  const runTopic = async (topic: TrendTopic) => {
    Swal.fire({
      title: "Generando tendencias",
      text: "Ejecutando búsqueda con IA…",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    try {
      await axios.post(`/topics-ia/${topic.id}/run`);
      Swal.fire("Éxito", "Tendencias generadas correctamente.", "success");
      fetchPage("/topics-ia/fetch");
    } catch (e: any) {
      Swal.fire(
        "Error",
        e?.response?.data?.message ?? "No se pudo ejecutar el topic.",
        "error"
      );
    }
  };

  /* ==============================================
     Eliminar
  ============================================== */
  const removeOne = async (id: number, name: string) => {
    const ok = await Swal.fire({
      title: `¿Eliminar "${name}"?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Eliminar",
    });

    if (!ok.isConfirmed) return;

    await axios.delete(`/topics-ia/${id}`);
    setItems((p) => p.filter((i) => i.id !== id));
  };

  /* ==============================================
     Toggle active
  ============================================== */
  const toggleActive = async (topic: TrendTopic) => {
    const newVal = topic.active === 1 ? 0 : 1;
    await axios.patch(`/topics-ia/${topic.id}/toggle`, { active: newVal });
    setItems((p) =>
      p.map((i) => (i.id === topic.id ? { ...i, active: newVal } : i))
    );
  };

  /* ==============================================
     Reactivar
  ============================================== */
  const reactivate = async (id: number) => {
    await axios.patch(`/topics-ia/${id}/reactivate`);
    fetchPage("/topics-ia/fetch");
  };

  /* ==============================================
     Render
  ============================================== */
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen">

        {/* HEADER */}
        <div className="flex justify-between items-center mb-6 border-b pb-4">
          <h1 className="text-3xl font-semibold flex items-center gap-2">
            <Sparkles className="w-7 h-7 text-[#1CBCE8]" />
            <span className="text-[#0C647A] dark:text-[#1CBCE8]">
              Topics IA (Tendencias / Certificaciones / Skills)
            </span>
          </h1>

          <button
            onClick={() => {
              setEditing(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-[#1CBCE8] text-white rounded-md flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Nuevo Topic IA
          </button>
        </div>

        {/* SEARCH */}
        <div className="relative w-80 mb-6">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Buscar topic…"
            className="w-full pl-9 pr-3 py-2 rounded-md border"
          />
        </div>

        {/* TABLE */}
        <div className="overflow-x-auto border rounded-lg">
          <table className="min-w-full text-sm">
            <thead className="bg-[#1CBCE8] text-white uppercase text-xs">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">Topic</th>
                <th className="px-4 py-2">Intent</th>
                <th className="px-4 py-2">Estado IA</th>
                <th className="px-4 py-2">Activo</th>
                <th className="px-4 py-2">Fallos</th>
                <th className="px-4 py-2">Éxitos</th>
              </tr>
            </thead>

            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-6 text-center">Cargando…</td>
                </tr>
              ) : (
                items.map((item) => (
                  <tr key={item.id} className="hover:bg-[#E7F9FD]/50">
                    <td className="px-4 py-2 space-y-1">
                      <button
                        onClick={() => runTopic(item)}
                        disabled={item.last_run_status === "running"}
                        className="flex items-center gap-1 text-green-600"
                      >
                        {item.last_run_status === "running" ? (
                          <Loader2 className="w-4 h-4 animate-spin" />
                        ) : (
                          <Play className="w-4 h-4" />
                        )}

                      </button>

                      <button
                        onClick={() => setEditing(item)}
                        className="flex items-center gap-1 text-[#1CBCE8]"
                      >
                        <Edit className="w-4 h-4" />
                      </button>

                      <button
                        onClick={() => removeOne(item.id, item.topic_name)}
                        className="flex items-center gap-1 text-red-500"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>

                      {!item.active && item.fail_count >= 3 && (
                        <button
                          onClick={() => reactivate(item.id)}
                          className="flex items-center gap-1 text-green-600"
                        >
                          <RefreshCcw className="w-4 h-4" />
                        </button>
                      )}
                    </td>

                    <td className="px-4 py-2 font-semibold">
                      {item.topic_name}
                    </td>

                    <td className="px-4 py-2 capitalize">
                      {item.intent}
                    </td>

                    <td className="px-4 py-2">
                      {item.last_run_status ?? "idle"}
                    </td>

                    <td className="px-4 py-2 text-center">
                      <input
                        type="checkbox"
                        checked={item.active === 1}
                        onChange={() => toggleActive(item)}
                      />
                    </td>

                    <td className="px-4 py-2 text-center text-red-500">
                      {item.fail_count}
                    </td>

                    <td className="px-4 py-2 text-center text-green-600">
                      {item.success_count}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {showModal && (
        <TopicModal
          open={showModal}
          editing={editing}
          onClose={() => {
            setEditing(null);
            setShowModal(false);
          }}
          onCreated={() => fetchPage("/topics-ia/fetch")}
        />
      )}
    </AppLayout>
  );
}
