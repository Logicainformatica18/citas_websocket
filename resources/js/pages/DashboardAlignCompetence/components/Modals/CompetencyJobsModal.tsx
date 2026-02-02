import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

/* ======================================================
   TYPES
====================================================== */
interface JobItem {
  id: number;
  title: string;
  company?: string;
  location?: string;
  country?: string;
  modality?: string;
  published_at?: string;
  url?: string;
}

interface JobsResponse {
  data?: JobItem[];
}

interface Props {
  competency: {
    id: number;
    name: string;
  };
  jobs: JobItem[] | JobsResponse | null;
  onClose: () => void;
}

/* ======================================================
   COMPONENT
====================================================== */
export default function CompetencyJobsModal({
  competency,
  jobs,
  onClose,
}: Props) {
  /* =========================
     NORMALIZE JOBS
  ========================= */
  const jobList: JobItem[] = Array.isArray(jobs)
    ? jobs
    : jobs?.data ?? [];

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="max-w-2xl max-h-[80vh] overflow-hidden">

        {/* ================= HEADER ================= */}
        <DialogHeader>
          <DialogTitle className="text-lg font-semibold">
            Empleos relacionados
          </DialogTitle>
          <p className="text-sm text-slate-500">
            Competencia:{" "}
            <span className="font-medium text-slate-700">
              {competency.name}
            </span>
          </p>
        </DialogHeader>

        {/* ================= BODY ================= */}
        <div className="mt-4 overflow-y-auto pr-1 max-h-[55vh]">
          {jobList.length === 0 ? (
            <div className="text-sm text-slate-500 text-center py-10">
              No se encontraron empleos asociados a esta competencia
              para el período seleccionado.
            </div>
          ) : (
            <ul className="space-y-3 text-sm">
              {jobList.map((j) => (
                <li
                  key={j.id}
                  className="rounded-lg border p-3 hover:bg-slate-50 dark:hover:bg-[#123A52]"
                >
                  <p className="font-semibold text-slate-800 dark:text-slate-100">
                    {j.title}
                  </p>

                  <p className="mt-1 text-xs text-slate-500">
                    {j.company && <span>{j.company}</span>}
                    {j.location && <span> · {j.location}</span>}
                    {j.country && <span> · {j.country}</span>}
                  </p>

                  {j.modality && (
                    <p className="mt-1 text-xs text-slate-400">
                      Modalidad: {j.modality}
                    </p>
                  )}

                  {j.url && (
                    <a
                      href={j.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="mt-2 inline-block text-xs text-[#00B6E8] hover:underline"
                    >
                      Ver oferta →
                    </a>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
