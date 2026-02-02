import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Sparkles, ExternalLink } from "lucide-react";

/* ======================================================
   TYPES
====================================================== */
interface TrendItem {
  id: number;
  topic_name: string;
  trend_score?: number;
  year: number;
  quarter: number;
  source_title?: string;
  source_type?: string;
  source_url?: string;
}

interface Props {
  competency: {
    id: number;
    name: string;
  };
  trends: TrendItem[];
  onClose: () => void;
}

/* ======================================================
   COMPONENT
====================================================== */
export default function CompetencyTrendsModal({
  competency,
  trends = [],
  onClose,
}: Props) {
  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="max-w-2xl max-h-[80vh] overflow-hidden">

        {/* ================= HEADER ================= */}
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-lg font-semibold">
            <Sparkles className="h-5 w-5 text-purple-600" />
            Tendencias asociadas
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
          {trends.length === 0 ? (
            <div className="text-sm text-slate-500 text-center py-10">
              No se encontraron tendencias asociadas a esta competencia
              para el período seleccionado.
            </div>
          ) : (
            <ul className="space-y-3 text-sm">
              {trends.map((t) => (
                <li
                  key={t.id}
                  className="rounded-lg border p-3 hover:bg-slate-50 dark:hover:bg-[#123A52]"
                >
                  <p className="font-semibold text-slate-800 dark:text-slate-100">
                    {t.topic_name}
                  </p>

                  <p className="mt-1 text-xs text-slate-500">
                    {t.source_type && <span>{t.source_type}</span>}
                    <span>
                      {" "}
                      · {t.year}-S{t.quarter === 1 ? "1" : "2"}
                    </span>
                    {t.trend_score !== undefined && (
                      <span> · Score: {t.trend_score}</span>
                    )}
                  </p>

                  {t.source_title && (
                    <p className="mt-1 text-xs text-slate-400">
                      Fuente: {t.source_title}
                    </p>
                  )}

                  {t.source_url && (
                    <a
                      href={t.source_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="mt-2 inline-flex items-center gap-1 text-xs text-purple-600 hover:underline"
                    >
                      Ver reporte
                      <ExternalLink className="h-3 w-3" />
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
