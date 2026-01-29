import { useEffect, useState } from "react";
import axios from "axios";
import {
  X,
  Briefcase,
  Building2,
  MapPin,
  Calendar,
  Banknote,
} from "lucide-react";

interface Job {
  id: number;
  title: string;
  company: string;
  location: string;
  country: string;
  modality: string;
  salary_min: number | null;
  salary_max: number | null;
  source: string;
  published_at: string;
  url: string;
}

interface Props {
  open: boolean;
  onClose: () => void;
  trendId: number | null;
  title?: string;
}

export default function TrendJobsModal({
  open,
  onClose,
  trendId,
  title,
}: Props) {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  const PER_PAGE = 10;

  /* =========================
     CERRAR CON ESC
  ========================= */
  useEffect(() => {
    if (!open) return;

    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        onClose();
      }
    };

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [open, onClose]);

  /* =========================
     RESET AL CERRAR
  ========================= */
  useEffect(() => {
    if (!open) {
      setJobs([]);
      setPage(1);
      setTotal(0);
    }
  }, [open]);

  /* =========================
     FETCH INICIAL
  ========================= */
  useEffect(() => {
    if (!open || !trendId) return;
    fetchJobs(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, trendId]);

  const fetchJobs = async (pageNumber: number) => {
    setLoading(true);

    try {
      const res = await axios.get(
        `/dashboard/ranking/technologies/trend/${trendId}/jobs`,
        {
          params: {
            page: pageNumber,
            per_page: PER_PAGE,
          },
        }
      );

      const paginator = res.data?.data;

      const jobsArray = Array.isArray(paginator?.data)
        ? paginator.data
        : [];

      setJobs(jobsArray);
      setTotal(Number(paginator?.total ?? 0));
      setPage(Number(paginator?.current_page ?? 1));
    } catch (error) {
      console.error("❌ Error cargando jobs de tendencia", error);
      setJobs([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
      onClick={onClose} // 👈 CLICK FUERA CIERRA
    >
      <div
        className="bg-white dark:bg-[#0F2A3A] w-full max-w-5xl rounded-xl shadow-lg overflow-hidden"
        onClick={(e) => e.stopPropagation()} // 👈 BLOQUEA CLICK INTERNO
      >
        {/* ================= HEADER ================= */}
        <div className="flex items-center justify-between px-6 py-4 border-b dark:border-slate-700">
          <div className="flex items-center gap-2">
            <Briefcase className="w-5 h-5 text-[#00B6E8]" />
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              {title
                ? `Ofertas laborales – ${title}`
                : "Ofertas laborales asociadas a la tendencia"}
            </h2>
          </div>

          <button
            onClick={onClose}
            className="text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* ================= BODY ================= */}
        <div className="p-6 max-h-[70vh] overflow-y-auto">
          {loading && (
            <div className="text-center text-slate-500">
              Cargando ofertas laborales…
            </div>
          )}

          {!loading && jobs.length === 0 && (
            <div className="text-center text-slate-500">
              No se encontraron ofertas para esta tendencia.
            </div>
          )}

          {!loading && jobs.length > 0 && (
            <div className="space-y-4">
              {jobs.map((job) => (
                <div
                  key={job.id}
                  className="border rounded-lg p-4 dark:border-slate-700"
                >
                  <div className="flex justify-between items-start gap-4">
                    <div>
                      <h3 className="font-semibold text-slate-900 dark:text-slate-100">
                        {job.title}
                      </h3>

                      <div className="mt-1 space-y-1 text-sm text-slate-500">
                        <div className="flex items-center gap-1">
                          <Building2 className="w-4 h-4" />
                          {job.company}
                        </div>

                        <div className="flex items-center gap-1">
                          <MapPin className="w-4 h-4" />
                          {job.location} ({job.country})
                        </div>

                        <div className="flex items-center gap-1">
                          <Calendar className="w-4 h-4" />
                          {new Date(
                            job.published_at
                          ).toLocaleDateString()}
                        </div>
                      </div>

                      {(job.salary_min || job.salary_max) && (
                        <div className="mt-2 flex items-center gap-1 text-sm text-slate-600 dark:text-slate-300">
                          <Banknote className="w-4 h-4" />
                          {job.salary_min ?? "—"} –{" "}
                          {job.salary_max ?? "—"}
                        </div>
                      )}

                      <div className="mt-1 text-xs text-slate-400">
                        {job.modality} • {job.source}
                      </div>
                    </div>

                    <a
                      href={job.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-sm text-[#00B6E8] hover:underline whitespace-nowrap"
                    >
                      Ver oferta
                    </a>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* ================= FOOTER ================= */}
        {total > PER_PAGE && (
          <div className="flex justify-between items-center px-6 py-4 border-t dark:border-slate-700">
            <span className="text-sm text-slate-500">
              Mostrando {(page - 1) * PER_PAGE + 1}–
              {Math.min(page * PER_PAGE, total)} de {total}
            </span>

            <div className="flex gap-2">
              <button
                disabled={page <= 1}
                onClick={() => fetchJobs(page - 1)}
                className="px-3 py-1 text-sm border rounded disabled:opacity-50"
              >
                Anterior
              </button>
              <button
                disabled={page * PER_PAGE >= total}
                onClick={() => fetchJobs(page + 1)}
                className="px-3 py-1 text-sm border rounded disabled:opacity-50"
              >
                Siguiente
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
