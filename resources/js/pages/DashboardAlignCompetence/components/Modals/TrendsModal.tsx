import { Dialog } from "@/components/ui/dialog";
import { useEffect, useState } from "react";
import axios from "axios";

export default function TrendsModal({ open, onClose, competencyId }: any) {
  const [trends, setTrends] = useState<any[]>([]);

  useEffect(() => {
    if (!open) return;

    axios
      .get(`/dashboard/indicators/pe-alignment/competency/${competencyId}/trends`)
      .then(res => setTrends(res.data.data ?? []));
  }, [open]);

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <div className="p-6 space-y-4">
        <h3 className="font-semibold text-lg">
          Tendencias estratégicas
        </h3>

        {trends.map((t, i) => (
          <div key={i} className="border p-3 rounded-lg text-sm">
            <p className="font-medium">{t.trend_name}</p>
            <p className="text-muted-foreground">
              Score: {t.trend_score}
            </p>
          </div>
        ))}

        {trends.length === 0 && (
          <p className="text-sm text-muted-foreground">
            No hay tendencias registradas para este periodo.
          </p>
        )}
      </div>
    </Dialog>
  );
}
