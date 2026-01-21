import { Dialog } from "@headlessui/react";
import { useEffect, useState } from "react";
import axios from "axios";
import { X, ExternalLink } from "lucide-react";

/* =========================
   TIPOS
========================= */
type Job = {
  id: number;
  title: string;
  company: string;
  location?: string | null;
  country?: string | null;
  modality?: string | null;
  source?: string | null;
  published_at?: string | null;
  url?: string | null;
};

type Props = {
  open: boolean;
  onClose: () => void;
  technologyId: number | null;
  technologyName?: string;
};

export default function TechnologyJobsModal({
  open,
  onClose,
  technologyId,
  technologyName,
}: Props) {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);

  /* =========================
     FETCH OFERTAS
  ========================= */
  const fetchJobs = async (pageNumber: number) => {
    if (!technologyId) return;

    setLoading(true);

    try {
      const res = await axios.get(
        `/dashboard/ranking/technologies/${technologyId}/jobs`,
        {
          params: {
            page: pageNumber,
            per_page: 10,
          },
        }
      );

      setJobs(res.data.data ?? []);
      setPage(res.data.current_page);
      setLastPage(res.data.last_page);
    } catch (error) {
      console.error("❌ Error cargando ofertas laborales:", error);
      setJobs([]);
    } finally {
      setLoading(false);
    }
  };

  /* =========================
     EFECTO APERTURA
  ========================= */
  useEffect(() => {
    if (open && technologyId) {
      setJobs([]);
      setPage(1);
      setLastPage(1);
      fetchJobs(1);
    }
  }, [open, technologyId]);

  if (!open) return null;

  return (
    <Dialog open={open} onClose={onClose} className="relative z-[9999]">
      {/* BACKDROP */}
      <div className="fixed inset-0 bg-black/50" aria-hidden="true" />

      {/* WRAPPER */}
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <Dialog.Panel
          className="
            w-full max-w-6xl
            rounded-2xl shadow-xl
            flex flex-col max-h-[85vh]
            bg-white dark:bg-[#0F2A3A]
            border dark:border-[#1E3A4A]
          "
        >
          {/* ================= HEADER ================= */}
          <div className="flex items-center justify-between px-6 py-4 border-b dark:border-[#1E3A4A] shrink-0">
            <div>
              <Dialog.Title className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                Ofertas laborales
              </Dialog.Title>
              {technologyName && (
                <p className="text-sm text-gray-600 dark:text-slate-300">
                  {technologyName}
                </p>
              )}
            </div>

            <button
              onClick={onClose}
              className="
                rounded-lg p-2
                text-gray-600 hover:bg-gray-100
                dark:text-slate-300 dark:hover:bg-[#123A52]
              "
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          {/* ================= BODY ================= */}
          <div className="flex-1 overflow-y-auto px-6 py-4">
            {loading ? (
              <p className="text-center py-10 text-gray-600 dark:text-slate-400">
                Cargando ofertas laborales…
              </p>
            ) : jobs.length === 0 ? (
              <p className="text-center py-10 text-gray-600 dark:text-slate-400">
                No se encontraron ofertas laborales para esta tecnología.
              </p>
            ) : (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {jobs.map((job, index) => (
                  <div
                    key={job.id}
                    className="
                      rounded-xl border p-4
                      bg-white dark:bg-[#102C3C]
                      dark:border-[#1E3A4A]
                      hover:shadow-md transition
                    "
                  >
                    {/* TÍTULO */}
                    <h4 className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                      {(page - 1) * 10 + index + 1}. {job.title}
                    </h4>

                    {/* EMPRESA */}
                    <p className="text-xs text-slate-500 mt-1">
                      {job.company}
                      {job.country && ` · ${job.country}`}
                      {job.modality && ` · ${job.modality}`}
                    </p>

                    {/* FECHA / FUENTE */}
                    <p className="text-[11px] text-slate-400 mt-1">
                      {job.source && `Fuente: ${job.source}`}
                      {job.published_at && ` · ${job.published_at}`}
                    </p>

                    {/* LINK */}
                    {job.url && (
                      <a
                        href={job.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="
                          inline-flex items-center gap-1
                          mt-3 text-xs font-semibold
                          text-[#1CBCE8] hover:underline
                        "
                      >
                        Ver oferta
                        <ExternalLink className="h-3 w-3" />
                      </a>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* ================= FOOTER ================= */}
          <div className="flex items-center justify-between px-6 py-4 border-t dark:border-[#1E3A4A] shrink-0">
            <button
              disabled={page <= 1}
              onClick={() => fetchJobs(page - 1)}
              className="
                px-4 py-2 rounded-lg border text-sm font-medium transition
                disabled:opacity-40
                bg-white text-slate-700 hover:bg-gray-100
                dark:bg-[#123A52] dark:text-slate-200
                dark:border-[#1E3A4A] dark:hover:bg-[#1B4B63]
              "
            >
              ← Anterior
            </button>

            <span className="text-sm text-gray-600 dark:text-slate-400">
              Página {page} de {lastPage}
            </span>

            <button
              disabled={page >= lastPage}
              onClick={() => fetchJobs(page + 1)}
              className="
                px-4 py-2 rounded-lg border text-sm font-medium transition
                disabled:opacity-40
                bg-white text-slate-700 hover:bg-gray-100
                dark:bg-[#123A52] dark:text-slate-200
                dark:border-[#1E3A4A] dark:hover:bg-[#1B4B63]
              "
            >
              Siguiente →
            </button>
          </div>
        </Dialog.Panel>
      </div>
    </Dialog>
  );
}
