import { Dialog } from "@/components/ui/dialog";
import { useEffect, useState } from "react";
import axios from "axios";

export default function JobsModal({ open, onClose, competencyId }: any) {
  const [jobs, setJobs] = useState<any[]>([]);

  useEffect(() => {
    if (!open) return;

    axios
      .get(`/dashboard/indicators/pe-alignment/competency/${competencyId}/jobs`)
      .then(res => setJobs(res.data.data ?? []));
  }, [open]);

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <div className="p-6 space-y-4">
        <h3 className="font-semibold text-lg">
          Vacantes asociadas
        </h3>

        {jobs.map(j => (
          <div key={j.id} className="border p-3 rounded-lg text-sm">
            <p className="font-medium">{j.title}</p>
            <p className="text-muted-foreground">
              {j.company} — {j.location}
            </p>
          </div>
        ))}

        {jobs.length === 0 && (
          <p className="text-sm text-muted-foreground">
            No se encontraron vacantes en el periodo seleccionado.
          </p>
        )}
      </div>
    </Dialog>
  );
}
