import { Dialog } from "@headlessui/react";
import { useEffect, useState } from "react";
import axios from "axios";
import { X } from "lucide-react";
//import JobOfferCard from "./JobOfferCard";

type Job = {
  id: number;
  title: string;
  company: string;
  location?: string;
  modality?: string;
  source?: string;
  url?: string;
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

  const fetchJobs = async (pageNumber: number) => {
    if (!technologyId) return;

    setLoading(true);

    const res = await axios.get(
      `/dashboard/ranking/technologies/${technologyId}/jobs`,
      {
        params: { page: pageNumber, per_page: 10 },
      }
    );

    setJobs(res.data.data);
    setPage(res.data.current_page);
    setLastPage(res.data.last_page);

    setLoading(false);
  };

  useEffect(() => {
    if (open && technologyId) {
      fetchJobs(1);
    }
  }, [open, technologyId]);

  return (
    <Dialog open={open} onClose={onClose} className="relative z-50">
      {/* BACKDROP */}
      <div className="fixed inset-0 bg-black/50" />

      {/* WRAPPER */}
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <Dialog.Panel
          className="
            w-full
            max-w-6xl
            rounded-2xl
            shadow-xl
            flex
            flex-col
            max-h-[85vh]

            bg-white
            dark:bg-[#0F2A3A]
            border
            dark:border-[#1E3A4A]
          "
        >
          {/* ================= HEADER ================= */}
          <div className="flex items-center justify-between px-6 py-4 border-b dark:border-[#1E3A4A] shrink-0">
            <div>
              <Dialog.Title className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                Ofertas laborales
              </Dialog.Title>
              <p className="text-sm text-gray-600 dark:text-slate-300">
                {technologyName}
              </p>
            </div>

            <button
              onClick={onClose}
              className="rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-[#123A52]"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          {/* ================= BODY ================= */}
          <div className="flex-1 overflow-y-auto px-6 py-4">
            {loading ? (
              <p className="text-center py-10 text-gray-600 dark:text-slate-400">
                Cargando ofertas…
              </p>
            ) : (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                {/* {jobs.map((job, index) => (
                  <JobOfferCard
                    key={job.id}
                    index={(page - 1) * 10 + index}
                    job={job}
                  />
                ))} */}
              </div>
            )}
          </div>

          {/* ================= FOOTER ================= */}
          <div className="flex items-center justify-between px-6 py-4 border-t dark:border-[#1E3A4A] shrink-0">
            <button
              disabled={page === 1}
              onClick={() => fetchJobs(page - 1)}
              className="
                px-4 py-2
                rounded-lg
                border
                text-sm
                font-medium
                transition
                disabled:opacity-40

                bg-white
                text-slate-700
                hover:bg-gray-100

                dark:bg-[#123A52]
                dark:text-slate-200
                dark:border-[#1E3A4A]
                dark:hover:bg-[#1B4B63]
              "
            >
              ← Anterior
            </button>

            <span className="text-sm text-gray-600 dark:text-slate-400">
              Página {page} de {lastPage}
            </span>

            <button
              disabled={page === lastPage}
              onClick={() => fetchJobs(page + 1)}
              className="
                px-4 py-2
                rounded-lg
                border
                text-sm
                font-medium
                transition
                disabled:opacity-40

                bg-white
                text-slate-700
                hover:bg-gray-100

                dark:bg-[#123A52]
                dark:text-slate-200
                dark:border-[#1E3A4A]
                dark:hover:bg-[#1B4B63]
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
