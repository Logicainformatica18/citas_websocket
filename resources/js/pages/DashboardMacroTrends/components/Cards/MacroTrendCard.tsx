import { Star, ExternalLink } from "lucide-react";
import { useState } from "react";

type Props = {
  data: any;
  onOpen: (trend: any) => void;
};

export default function MacroTrendCard({ data, onOpen }: Props) {
  const [expanded, setExpanded] = useState(false);

  const title = data?.trend_name ?? "Sin título";
  const description = data?.description ?? "";

  const stars = 4;

  return (
    <div
      className="group rounded-2xl border-2 border-[#00B6E8]/40 bg-white p-6 shadow-sm transition-all duration-300 hover:shadow-lg dark:bg-[#0F172A] dark:border-[#00B6E8]/40"
      onMouseEnter={() => setExpanded(true)}
      onMouseLeave={() => setExpanded(false)}
    >
      {/* HEADER */}
      <div className="flex justify-between items-start gap-4">
        <h3 className="text-lg font-bold text-[#0A2540] dark:text-white leading-snug">
          {title}
        </h3>

        <div className="flex gap-1 mt-1">
          {[...Array(stars)].map((_, i) => (
            <Star
              key={i}
              className="h-4 w-4 fill-yellow-400 text-yellow-400"
            />
          ))}
        </div>
      </div>

      {/* DESCRIPCIÓN */}
      <p
        className={`mt-3 text-sm text-slate-600 dark:text-slate-300 transition-all duration-300 ${
          expanded ? "" : "line-clamp-2"
        }`}
      >
        {description}
      </p>

      {/* FOOTER */}
      <div className="mt-4 flex items-center justify-between flex-wrap gap-3">

        {/* IZQUIERDA: Fuente + acciones */}
        <div className="flex items-center gap-4 flex-wrap">

          {/* Fuente badge */}
          <span className="inline-flex items-center rounded-full bg-[#00B6E8]/10 text-[#00B6E8] px-3 py-1 text-xs font-semibold">
            {data?.source_name ?? "Fuente"}
          </span>

          {/* Ver análisis */}
          <button
            onClick={() => onOpen(data)}
            className="text-sm font-semibold text-[#00B6E8] hover:underline"
          >
            Ver análisis
          </button>

          {/* Ver reporte */}
          {/* {data?.source_url && (
            <a
              href={data.source_url}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1 text-sm font-semibold text-[#00B6E8] hover:underline"
            >
              Ver reporte
              <ExternalLink className="h-3 w-3" />
            </a>
          )} */}
        </div>

        {/* DERECHA: periodo */}
        {data?.year && (
          <span className="text-xs text-slate-500 dark:text-slate-400">
            {data.year} · Q{data.quarter}
          </span>
        )}
      </div>
    </div>
  );
}
