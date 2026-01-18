import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
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

/* ======================================================
   Tipos
====================================================== */
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
  active: number;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

/* ======================================================
   Breadcrumbs
====================================================== */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Topics IA", href: "/topics-ia" },
];

/* ======================================================
   Componente
====================================================== */
export default function TopicsIndex() {
  const { topics: initialPagination } = usePage<{
    topics: Pagination<TrendTopic>;
  }>().props;

  const [items, setItems] = useState<TrendTopic[]>([]);
  const [pagination, setPagination] =
    useState<Pagination<TrendTopic>>(initialPagination);

  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);
  const [mounted, setMounted] = useState(false);

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<TrendTopic | null>(null);
const [runningTopicId, setRunningTopicId] = useState<number | null>(null);

  /* ======================================================
     Init desde backend (Inertia)
  ====================================================== */
  useEffect(() => {
    setItems(initialPagination.data ?? []);
    setPagination(initialPagination);
    setMounted(true);
  }, [initialPagination]);

  /* ======================================================
     Search debounce (MISMO patrón que Technologies)
  ====================================================== */
  useEffect(() => {
    if (!mounted) return;

    const delay = setTimeout(() => {
      if (search.trim() === "") {
        fetchPage("/topics-ia/fetch");
      } else {
        fetchPage(`/topics-ia/fetch?search=${encodeURIComponent(search)}`);
      }
    }, 500);

    return () => clearTimeout(delay);
  }, [search, mounted]);

  /* ======================================================
     Normalizador de paginación
  ====================================================== */
  const normalizePagePayload = (payload: any): Pagination<TrendTopic> => {
    const pager = payload?.topics ?? payload;

    return {
      data: Array.isArray(pager?.data) ? pager.data : [],
      current_page: pager?.current_page ?? 1,
      last_page: pager?.last_page ?? 1,
      next_page_url: pager?.next_page_url ?? null,
      prev_page_url: pager?.prev_page_url ?? null,
    };
  };

  /* ======================================================
     FetchPage (ÚNICA fuente de verdad)
  ====================================================== */
  const fetchPage = async (url: string) => {
    try {
      setLoading(true);
      const res = await axios.get(url);
      const norm = normalizePagePayload(res?.data);

      setItems(norm.data);
      setPagination(norm);
    } catch (e) {
      console.error(e);
      Swal.fire("Error", "No se pudo cargar la información.", "error");
    } finally {
      setLoading(false);
    }
  };

  /* ======================================================
     Ejecutar Topic (Job)
  ====================================================== */
  const runTopic = async (topic: TrendTopic) => {
  // 1️⃣ Estado optimista
  setRunningTopicId(topic.id);

  setItems((prev) =>
    prev.map((i) =>
      i.id === topic.id
        ? { ...i, last_run_status: "running" }
        : i
    )
  );

  Swal.fire({
    title: "Ejecución iniciada",
    text: "La IA está procesando las tendencias…",
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading(),
  });

  try {
    await axios.post(`/topics-ia/${topic.id}/run`);
    Swal.close();

    // 2️⃣ Polling suave cada 3s
  const poll = setInterval(async () => {
  try {
    const res = await axios.get(`/topics-ia/${topic.id}/status`);
    const status = res.data;

    setItems((prev) =>
      prev.map((i) =>
        i.id === topic.id
          ? { ...i, ...status }
          : i
      )
    );

    if (status.last_run_status !== "running") {
      clearInterval(poll);
      setRunningTopicId(null);
    }
  } catch (err) {
    console.error("Polling status error", err);
  }
}, 3000);


   } catch (e: any) {
  Swal.close();

  const msg = e?.response?.data?.message ?? "";

  // 🟢 Caso esperado: ya está corriendo
  if (
    e?.response?.status === 409 ||
    msg.toLowerCase().includes("ya está en ejecución")
  ) {
    // 👉 asumir running y seguir polling
    setRunningTopicId(topic.id);

    const poll = setInterval(async () => {
      const res = await axios.get(
        `/topics-ia/fetch?page=${pagination.current_page}&search=${encodeURIComponent(search)}`
      );

      const norm = normalizePagePayload(res.data);
      setItems(norm.data);
      setPagination(norm);

      const updated = norm.data.find((i) => i.id === topic.id);

      if (updated && updated.last_run_status !== "running") {   
        clearInterval(poll);
        setRunningTopicId(null);
      }
    }, 3000);

    return;
  }

  // 🔴 Error real
  setRunningTopicId(null);
  Swal.fire(
    "Error",
    msg || "No se pudo ejecutar el topic.",
    "error"
  );
}

};


  /* ======================================================
     Eliminar
  ====================================================== */
  const removeOne = async (id: number, name: string) => {
    const confirm = await Swal.fire({
      title: `¿Eliminar "${name}"?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Eliminar",
    });

    if (!confirm.isConfirmed) return;

    await axios.delete(`/topics-ia/${id}`);
    fetchPage(
      `/topics-ia/fetch?page=${pagination.current_page}&search=${encodeURIComponent(
        search
      )}`
    );
  };

  /* ======================================================
     Toggle Active
  ====================================================== */
  const toggleActive = async (topic: TrendTopic) => {
    const newVal = topic.active === 1 ? 0 : 1;
    await axios.patch(`/topics-ia/${topic.id}/toggle`, { active: newVal });

    setItems((prev) =>
      prev.map((i) => (i.id === topic.id ? { ...i, active: newVal } : i))
    );
  };

  /* ======================================================
     Reactivar
  ====================================================== */
  const reactivate = async (id: number) => {
    await axios.patch(`/topics-ia/${id}/reactivate`);
    fetchPage(
      `/topics-ia/fetch?page=${pagination.current_page}&search=${encodeURIComponent(
        search
      )}`
    );
  };

  /* ======================================================
     Render
  ====================================================== */
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

        {/* HEADER */}
        <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
          <h1 className="text-3xl font-semibold flex items-center gap-2">
            <Sparkles className="w-6 h-6 text-[#1CBCE8]" />
            <span className="text-[#0C647A] dark:text-[#1CBCE8]">
              Topics IA
            </span>
          </h1>

          <button
            onClick={() => {
              setEditing(null);
              setShowModal(true);
            }}
            className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-md shadow"
          >
            Nuevo Topic IA
          </button>
        </div>

        {/* SEARCH */}
        <div className="relative w-full sm:w-80 mb-6">
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
                <th className="px-4 py-2 text-center">Estado IA</th>
                <th className="px-4 py-2 text-center">Activo</th>
                <th className="px-4 py-2 text-center">Fallos</th>
                <th className="px-4 py-2 text-center">Éxitos</th>
              </tr>
            </thead>

            <tbody>
              {loading && (
                <tr>
                  <td colSpan={7} className="py-6 text-center">Cargando…</td>
                </tr>
              )}

              {!loading && items.length === 0 && (
                <tr>
                  <td colSpan={7} className="py-6 text-center">
                    No hay topics registrados.
                  </td>
                </tr>
              )}

              {!loading &&
                items.map((item) => (
                  <tr key={item.id} className="hover:bg-[#E7F9FD]/50">
                    <td className="px-4 py-2 space-y-1">
                      <button
  onClick={() => runTopic(item)}
  disabled={runningTopicId === item.id}
  className="flex items-center gap-1 text-green-600"
>
  {runningTopicId === item.id
    ? <Loader2 className="w-4 h-4 animate-spin" />
    : <Play className="w-4 h-4" />}
  Ejecutar
</button>


                      <button
                        onClick={() => {
                          setEditing(item);
                          setShowModal(true);
                        }}
                        className="flex items-center gap-1 text-[#1CBCE8]"
                      >
                        <Edit className="w-4 h-4" /> Editar
                      </button>

                      <button
                        onClick={() => removeOne(item.id, item.topic_name)}
                        className="flex items-center gap-1 text-red-500"
                      >
                        <Trash2 className="w-4 h-4" /> Eliminar
                      </button>

                      {!item.active && item.fail_count >= 3 && (
                        <button
                          onClick={() => reactivate(item.id)}
                          className="flex items-center gap-1 text-green-600"
                        >
                          <RefreshCcw className="w-4 h-4" /> Reactivar
                        </button>
                      )}
                    </td>

                    <td className="px-4 py-2 font-semibold">{item.topic_name}</td>
                    <td className="px-4 py-2 capitalize">{item.intent}</td>
                    <td className="px-4 py-2 text-center">{item.last_run_status}</td>
                    <td className="px-4 py-2 text-center">
                      <input
                        type="checkbox"
                        checked={item.active === 1}
                        onChange={() => toggleActive(item)}
                      />
                    </td>
                    <td className="px-4 py-2 text-center text-red-500">{item.fail_count}</td>
                    <td className="px-4 py-2 text-center text-green-600">{item.success_count}</td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>

        {/* PAGINACIÓN */}
        {pagination.last_page > 1 && (
          <div className="flex justify-center mt-6 gap-1">
            {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map(
              (page) => (
                <button
                  key={page}
                  onClick={() =>
                    fetchPage(
                      `/topics-ia/fetch?page=${page}&search=${encodeURIComponent(
                        search
                      )}`
                    )
                  }
                  className={`px-3 py-1 rounded-md text-sm ${
                    pagination.current_page === page
                      ? "bg-[#1CBCE8] text-white"
                      : "bg-gray-200 hover:bg-gray-300"
                  }`}
                >
                  {page}
                </button>
              )
            )}
          </div>
        )}

        {/* MODAL */}
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
      </div>
    </AppLayout>
  );
}
