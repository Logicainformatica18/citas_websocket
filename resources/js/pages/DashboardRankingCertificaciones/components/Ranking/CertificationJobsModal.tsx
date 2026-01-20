import axios from "axios";

/* ===============================
   TIPOS
================================ */
export type Job = {
  id: number;
  title: string;
  company: string;
  location: string | null;
  url: string;
};

type Props = {
  open: boolean;
  certification: any;
  jobs: Job[];
  pagination: any | null;
  onClose: () => void;

  // 👇 IMPORTANTE: setter que viene del padre
  onPageChange: (paginator: any) => void;
};

export default function CertificationJobsModal({
  open,
  certification,
  jobs,
  pagination,
  onClose,
  onPageChange,
}: Props) {
  if (!open) return null;

  /* ===============================
     CARGAR OTRA PÁGINA (AXIOS)
  ================================ */
  const loadPage = (url: string) => {
    axios.get(url).then((res) => {
      const paginator = res.data.data;

      onPageChange(paginator);
    });
  };

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center">
      {/* OVERLAY */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={onClose}
      />

      {/* MODAL */}
      <div className="relative w-full max-w-4xl mx-4 rounded-2xl bg-white dark:bg-[#0F2A3A] shadow-xl">
        {/* ================= HEADER ================= */}
        <div className="flex items-center justify-between px-6 py-4 border-b dark:border-[#1E3A4A]">
          <h3 className="text-lg font-semibold">
            Ofertas laborales – {certification?.name}
          </h3>

          <button onClick={onClose} className="text-xl">✕</button>
        </div>

        {/* ================= CONTENT ================= */}
        <div className="px-6 py-6 max-h-[70vh] overflow-y-auto">
          {/* TOTAL */}
          {pagination && (
            <p className="text-sm text-slate-500 mb-4">
              {pagination.total} ofertas laborales encontradas
            </p>
          )}

          {/* SIN RESULTADOS */}
          {jobs.length === 0 && (
            <p className="text-sm text-slate-500">
              No se encontraron ofertas laborales.
            </p>
          )}

          {/* LISTADO */}
          {jobs.length > 0 && (
            <ul className="space-y-4">
              {jobs.map((job) => (
                <li
                  key={job.id}
                  className="rounded-xl border p-4 dark:border-[#1E3A4A]"
                >
                  <h4 className="font-semibold">{job.title}</h4>

                  <p className="text-sm text-gray-500">
                    {job.company}
                    {job.location ? ` · ${job.location}` : ""}
                  </p>

                  <a
                    href={job.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm text-sky-600 hover:underline"
                  >
                    Ver oferta
                  </a>
                </li>
              ))}
            </ul>
          )}

          {/* ================= PAGINACIÓN ================= */}
          {pagination && (
            <div className="flex justify-between items-center mt-6">
              <button
                disabled={!pagination.prev_page_url}
                onClick={() =>
                  pagination.prev_page_url &&
                  loadPage(pagination.prev_page_url)
                }
                className="px-3 py-1 text-sm border rounded disabled:opacity-40"
              >
                Anterior
              </button>

              <span className="text-sm text-slate-500">
                Página {pagination.current_page} de {pagination.last_page}
              </span>

              <button
                disabled={!pagination.next_page_url}
                onClick={() =>
                  pagination.next_page_url &&
                  loadPage(pagination.next_page_url)
                }
                className="px-3 py-1 text-sm border rounded disabled:opacity-40"
              >
                Siguiente
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
