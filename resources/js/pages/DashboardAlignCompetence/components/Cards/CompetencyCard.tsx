import { Badge } from "@/components/ui/badge";
import { CheckCircle, XCircle, AlertTriangle } from "lucide-react";

type Props = {
  competency: {
    id: number;
    name: string;
    languages: string[];
    technologies: string[];
    market_match: boolean;
    trend_match: boolean;
    pe_score: number;
    status: "aligned" | "partial" | "gap";
  };
};

export default function CompetencyCard({ competency }: Props) {
  const statusConfig = {
    aligned: {
      label: "Alineada",
      color: "bg-green-100 text-green-700",
      icon: <CheckCircle className="w-4 h-4 text-green-600" />,
    },
    partial: {
      label: "Parcial",
      color: "bg-yellow-100 text-yellow-700",
      icon: <AlertTriangle className="w-4 h-4 text-yellow-600" />,
    },
    gap: {
      label: "GAP",
      color: "bg-red-100 text-red-700",
      icon: <XCircle className="w-4 h-4 text-red-600" />,
    },
  };

  const cfg = statusConfig[competency.status];

  return (
    <div className="rounded-xl border bg-white p-4 space-y-3 shadow-sm">
      {/* HEADER */}
      <div className="flex items-start justify-between">
        <h3 className="font-semibold text-sm">{competency.name}</h3>
        <span className={`px-2 py-1 rounded text-xs ${cfg.color}`}>
          {cfg.label}
        </span>
      </div>

      {/* STACK */}
      <div className="space-y-1">
        {competency.languages.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {competency.languages.map((l) => (
              <Badge key={l} variant="secondary">
                {l}
              </Badge>
            ))}
          </div>
        )}

        {competency.technologies.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {competency.technologies.map((t) => (
              <Badge key={t} variant="outline">
                {t}
              </Badge>
            ))}
          </div>
        )}
      </div>

      {/* EVIDENCIA */}
      <div className="flex items-center justify-between text-xs text-gray-600">
        <div className="flex items-center gap-1">
          {competency.market_match ? (
            <CheckCircle className="w-4 h-4 text-green-500" />
          ) : (
            <XCircle className="w-4 h-4 text-red-500" />
          )}
          Mercado
        </div>

        <div className="flex items-center gap-1">
          {competency.trend_match ? (
            <CheckCircle className="w-4 h-4 text-blue-500" />
          ) : (
            <XCircle className="w-4 h-4 text-red-500" />
          )}
          Tendencias
        </div>
      </div>

      {/* SCORE */}
      <div className="text-xs text-right font-medium text-gray-700">
        PE Score: {(competency.pe_score * 100).toFixed(0)}%
      </div>
    </div>
  );
}
