import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import axios from "axios";

import {
    PlusCircle,
    Pencil,
    Trash2,
    Database,
    CheckCircle,
    XCircle,
    Search,
} from "lucide-react";

import { toast } from "sonner";
import SourceModal from "./Components/SourceModal";

const breadcrumbs: BreadcrumbItem[] = [
    { title: "Scraping", href: "/scraping-sources" },
];

type Source = {
    id: number;
    name: string;
    url: string;
    frequency: string | null;
    pdf_path?: string | null;
    web_prompt?: string | null;
    web_only?: boolean | null;
    api_url?: string | null;
    api_key?: string | null;
    excel_path?: string | null;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    links: any[];
};

export default function ScrapingSourcesIndex({
    sources,
    filters,
}: {
    sources: Pagination<Source>;
    filters: any;
}) {

    const [search, setSearch] = useState(filters?.search || "");
    const [timer, setTimer] = useState<any>(null);

    const [modalData, setModalData] = useState<Source | null>(null);

    const [items, setItems] = useState<Source[]>(sources.data);
    const [pagination, setPagination] = useState(sources);
const searchLive = (value: string) => {
    setSearch(value);

    // limpiar el timer anterior
    if (timer) clearTimeout(timer);

    // crear nuevo timer
    const newTimer = setTimeout(() => {
        axios
            .get(`/scraping-sources/fetch?search=${value}`)
            .then((r) => {
                setItems(r.data.sources.data);
                setPagination(r.data.sources);
            })
            .catch(() => toast.error("Error buscando"));
    }, 400);

    setTimer(newTimer);
};

    /* =====================================================
        BUSCADOR
    ===================================================== */
const handleSearch = (e: any) => {
    e.preventDefault();

    axios
        .get(`/scraping-sources/fetch?search=${search}`)
        .then((r) => {
            setItems(r.data.sources.data);
            setPagination(r.data.sources);
        })
        .catch(() => toast.error("Error buscando"));
};



    /* =====================================================
        DELETE — estilo USERS (axios)
    ===================================================== */
    const deleteSource = async (id: number) => {
        if (!confirm("¿Eliminar este portal definitivamente?")) return;

        try {
            await axios.delete(`/scraping-sources/${id}`);
            setItems((prev) => prev.filter((x) => x.id !== id));
            toast.success("Fuente eliminada");
        } catch {
            toast.error("No se pudo eliminar");
        }
    };

    /* =====================================================
        PAGINACIÓN AJAX
    ===================================================== */
    const fetchPage = async (url: string) => {
        if (!url) return;

        const query = url.includes("?") ? url.split("?")[1] : "";

        try {
            const res = await axios.get(`/scraping-sources/fetch?${query}`);
            setItems(res.data.sources.data);
            setPagination(res.data.sources);
        } catch {
            toast.error("Error cargando página");
        }
    };

    /* =====================================================
        BADGES ✔✖
    ===================================================== */
    const statusBadge = (exists: boolean) =>
        exists ? (
            <span className="flex items-center justify-center text-green-600">
                <CheckCircle size={18} />
            </span>
        ) : (
            <span className="flex items-center justify-center text-red-500">
                <XCircle size={18} />
            </span>
        );

    const isPossible = (item: Source) =>
        !!item.pdf_path ||
    item.web_only === true ||      // ✔ indicador real para web
        !!item.api_url ||
        !!item.api_key ||
        !!item.excel_path;

    /* =====================================================
        RENDER
    ===================================================== */
   return (
  <AppLayout breadcrumbs={breadcrumbs}>
    <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100 transition">

      {/* 🔷 HEADER ISIL */}
      <div className="flex justify-between items-center mb-6 pb-4 border-b border-gray-300 dark:border-gray-700">
        <h1 className="text-3xl font-semibold flex items-center gap-2">
          <Database className="w-7 h-7 text-[#1CBCE8]" />
          <span className="text-[#0C647A] dark:text-[#1CBCE8]">
            Tendencias Tecnológicas – Fuentes
          </span>
        </h1>
      </div>

      {/* 🔍 BUSCADOR ISIL */}
      <form
        onSubmit={handleSearch}
        className="
          flex flex-col sm:flex-row gap-3 mb-6 
          bg-white dark:bg-gray-800 
          p-4 rounded-xl border dark:border-gray-700 shadow
        "
      >
        <div className="relative">
          <Search className="absolute left-3 top-2.5 text-gray-400 dark:text-gray-500 w-4 h-4" />
          <input
            type="text"
            placeholder="Buscar portal..."
            className="
              pl-9 pr-3 py-2 w-64 rounded-md 
              border border-gray-300 dark:border-gray-700
              bg-gray-50 dark:bg-gray-900
              text-gray-900 dark:text-gray-100
              focus:ring-2 focus:ring-[#1CBCE8] outline-none
            "
            value={search}
            onChange={(e) => searchLive(e.target.value)}
          />
        </div>

        <button className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] text-white rounded-lg shadow transition">
          Buscar
        </button>

        <button
          type="button"
          onClick={() => setModalData({} as Source)}
          className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg flex items-center gap-2 shadow transition"
        >
          <PlusCircle className="w-5 h-5" /> Nuevo
        </button>
      </form>

      {/* 📄 TABLA ISIL */}
      <div className="overflow-x-auto rounded-xl shadow border bg-white dark:bg-gray-900 dark:border-gray-700">
        <table className="min-w-full divide-y text-sm">
          <thead className="
              bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 
              text-white dark:text-[#1CBCE8] 
              uppercase text-xs tracking-wide
            ">
            <tr>
              <th className="px-4 py-3 text-left">Portal</th>
              <th className="px-4 py-3 text-center">Frecuencia</th>
              <th className="px-4 py-3 text-center">PDF</th>
              <th className="px-4 py-3 text-center">Web</th>
              <th className="px-4 py-3 text-center">API</th>
              <th className="px-4 py-3 text-center">Excel</th>
              <th className="px-4 py-3 text-center">¿Posible?</th>
              <th className="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
            {items.map((item) => (
              <tr
                key={item.id}
                className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
              >
                {/* PORTAL */}
                <td className="px-4 py-3">
                  <div className="font-semibold text-gray-900 dark:text-gray-100">
                    {item.name}
                  </div>

                  {item.url && (
                    <a
                      href={item.url}
                      target="_blank"
                      className="text-[#1CBCE8] hover:text-[#17A8D0] text-xs underline"
                    >
                      {item.url}
                    </a>
                  )}
                </td>

                {/* FRECUENCIA */}
                <td className="px-4 py-3 text-center">{item.frequency ?? "-"}</td>

                {/* PDF */}
                <td className="px-4 py-3 text-center">{statusBadge(!!item.pdf_path)}</td>

                {/* WEB */}
                <td className="px-4 py-3 text-center">{statusBadge(item.web_only === true)}</td>

                {/* API */}
                <td className="px-4 py-3 text-center">
                  {statusBadge(!!item.api_url || !!item.api_key)}
                </td>

                {/* EXCEL */}
                <td className="px-4 py-3 text-center">{statusBadge(!!item.excel_path)}</td>

                {/* POSIBLE */}
                <td className="px-4 py-3 text-center">{statusBadge(isPossible(item))}</td>

                {/* ACCIONES */}
                <td className="px-4 py-3 text-right flex justify-end gap-3">
                  <button
                    onClick={() => setModalData(item)}
                    className="text-[#1CBCE8] hover:text-[#17A8D0]"
                  >
                    <Pencil className="w-5 h-5" />
                  </button>

                  <button
                    onClick={() => deleteSource(item.id)}
                    className="text-red-500 hover:text-red-700"
                  >
                    <Trash2 className="w-5 h-5" />
                  </button>
                </td>
              </tr>
            ))}

            {items.length === 0 && (
              <tr>
                <td colSpan={8} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                  No se encontraron resultados.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* 🔹 PAGINACIÓN ISIL */}
      <div className="flex justify-center mt-6 gap-2">
        {pagination.links.map((link, i) => (
          <button
            key={i}
            disabled={!link.url}
            onClick={() => fetchPage(link.url)}
            className={`
              px-3 py-1.5 rounded-md text-sm font-semibold transition
              ${
                link.active
                  ? "bg-[#1CBCE8] text-white shadow"
                  : "bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100"
              }
            `}
          >
            {link.label.replace("&laquo;", "«").replace("&raquo;", "»")}
          </button>
        ))}
      </div>

      {/* MODAL */}
      {modalData && (
        <SourceModal
          data={modalData}
          onClose={() => setModalData(null)}
          onSaved={(saved) => {
            if (!saved) return;

            setItems((prev) => {
              const exists = prev.find((s) => s.id === saved.id);
              return exists
                ? prev.map((s) => (s.id === saved.id ? saved : s))
                : [saved, ...prev];
            });
          }}
        />
      )}
    </div>
  </AppLayout>
);

}
