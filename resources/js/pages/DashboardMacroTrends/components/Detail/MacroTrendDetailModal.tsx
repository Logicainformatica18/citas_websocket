import { X, Star, FileText, ExternalLink } from "lucide-react";

type Props = {
  open: boolean;
  trend: any;
  onClose: () => void;
};

export default function MacroTrendDetailModal({
  open,
  trend,
  onClose,
}: Props) {
  if (!open || !trend) return null;

  const stars = 4;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">

      {/* BACKDROP */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={onClose}
      />

      {/* MODAL */}
      <div className="relative z-10 w-full max-w-3xl rounded-2xl bg-white p-8 shadow-2xl dark:bg-[#0F172A]">

        {/* Close */}
        <button
          onClick={onClose}
          className="absolute right-5 top-5 text-slate-400 hover:text-slate-600 dark:hover:text-white"
        >
          <X className="h-5 w-5" />
        </button>

        {/* Título */}
        <h2 className="text-2xl font-bold text-[#0A2540] dark:text-white">
          {trend.name}
        </h2>

        {/* Estrellas */}
        <div className="mt-2 flex gap-1">
          {[...Array(stars)].map((_, i) => (
            <Star
              key={i}
              className="h-4 w-4 fill-yellow-400 text-yellow-400"
            />
          ))}
        </div>

        {/* Descripción completa */}
        <p className="mt-4 text-slate-700 dark:text-slate-300 leading-relaxed">
          {trend.description}
        </p>

        {/* BLOQUE FUENTE DESTACADO */}
        <div className="mt-6 rounded-xl border-2 border-[#00B6E8]/40 bg-[#F0FBFF] p-5 dark:bg-[#0B2235] dark:border-[#00B6E8]/50">

          <div className="flex items-center gap-2 text-sm font-semibold text-[#00B6E8]">
            <FileText className="h-4 w-4" />
            {trend.source_type ?? "Reporte"} • {trend.source_name}
          </div>

          {trend.source_title && (
            <p className="mt-2 text-sm text-slate-700 dark:text-slate-300">
              {trend.source_title}
            </p>
          )}

          {trend.source_url && (
            <a
              href={trend.source_url}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[#00B6E8] hover:underline"
            >
              Ver fuente original
              <ExternalLink className="h-3 w-3" />
            </a>
          )}
        </div>

        {/* Footer */}
        <div className="mt-6 text-xs text-slate-400">
          Registrado: {new Date(trend.created_at).toLocaleDateString()}
        </div>
      </div>
    </div>
  );
}
