import { Dialog } from "@headlessui/react";
import { useEffect, useState } from "react";
import axios from "axios";
import { X } from "lucide-react";

type Job = {
  id: number;
  title: string;
  company: string;
  location: string;
  modality: string;
  seniority: string;
  salary_min?: number;
  salary_max?: number;
  source: string;
  published_at: string;
};

type Props = {
  open: boolean;
  onClose: () => void;
  certificationId: number | null;
  certificationName?: string;
};

export default function CertificationJobsModal({
  open,
  onClose,
  certificationId,
  certificationName,
}: Props) {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [meta, setMeta] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  const fetchJobs = async (page = 1) => {
    if (!certificationId) return;

    setLoading(true);

    const res = await axios.get(
      `/dashboard/ranking-certificaciones/${certificationId}/jobs`,
      { params: { page } }
    );

    setJobs(res.data.data);
    setMeta(res.data);
    setLoading(false);
  };

  useEffect(() => {
    if (open) fetchJobs(1);
  }, [open]);

  return (
    <Dialog open={open} onClose={onClose} className="relative z-50">
      <div className="fixed inset-0 bg-black/40" />

      <div className="fixed inset-0 flex items-center justify-center p-4">
        <Dialog.Panel className="w-full max-w-5xl bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6">
          {/* Header */}
          <div className="flex justify-between items-center mb-4">
            <Dialog.Title className="text-xl font-bold">
              Ofertas con {certificationName}
            </Dialog.Title>
            <button onClick={onClose}>
              <X />
            </button>
          </div>

          {/* Body */}
          {loading ? (
            <p className="text-center py-10">Cargando...</p>
          ) : (
            <div className="space-y-3">
              {jobs.map((job) => (
                <div
                  key={job.id}
                  className="border rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                >
                  <h3 className="font-semibold">{job.title}</h3>
                  <p className="text-sm text-gray-600">
                    {job.company} · {job.location} · {job.modality}
                  </p>
                  <p className="text-xs text-gray-500">
                    {job.seniority} · {job.source}
                  </p>
                </div>
              ))}
            </div>
          )}

          {/* Pagination */}
          {meta && (
            <div className="flex justify-between items-center mt-6">
              <button
                disabled={!meta.prev_page_url}
                onClick={() => fetchJobs(meta.current_page - 1)}
                className="px-4 py-2 border rounded disabled:opacity-40"
              >
                ← Anterior
              </button>

              <span className="text-sm">
                Página {meta.current_page} de {meta.last_page}
              </span>

              <button
                disabled={!meta.next_page_url}
                onClick={() => fetchJobs(meta.current_page + 1)}
                className="px-4 py-2 border rounded disabled:opacity-40"
              >
                Siguiente →
              </button>
            </div>
          )}
        </Dialog.Panel>
      </div>
    </Dialog>
  );
}
