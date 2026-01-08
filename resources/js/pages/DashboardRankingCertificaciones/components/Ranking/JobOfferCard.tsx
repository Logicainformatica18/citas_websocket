import { ExternalLink } from "lucide-react";

type Props = {
  index: number;
  job: {
    title: string;
    company?: string;
    location?: string;
    modality?: string;
    source?: string;
    url?: string;
  };
};

export default function JobOfferCard({ index, job }: Props) {
  return (
    <div
      className="
        group
        relative
        rounded-xl
        border
        p-5
        transition-all
        duration-300
        cursor-default

        bg-white
        border-gray-200
        hover:shadow-md
        hover:border-[#1CBCE8]

        dark:bg-[#0F2A3A]
        dark:border-[#1E3A4A]
        dark:hover:border-[#1CBCE8]
        dark:hover:shadow-lg
      "
    >
      {/* Enumeración */}
      <div
        className="
          absolute -top-3 -left-3
          w-8 h-8
          rounded-full
          flex items-center justify-center
          text-xs font-semibold

          bg-[#ECFAFD]
          border border-[#A7E5F6]
          text-[#0A4E61]

          dark:bg-[#123A52]
          dark:border-[#1CBCE8]/30
          dark:text-slate-200
        "
      >
        {String(index + 1).padStart(2, "0")}
      </div>

      {/* Contenido */}
      <div className="space-y-2">
        {/* Título */}
        <h3 className="text-sm font-semibold leading-snug text-slate-900 dark:text-slate-100">
          {job.title}
        </h3>

        {/* Empresa + ubicación */}
        <p className="text-xs text-gray-600 dark:text-slate-300">
          {job.company}
          {job.location && ` · ${job.location}`}
        </p>

        {/* Modalidad + fuente */}
        <p className="text-xs text-gray-500 dark:text-slate-400">
          {job.modality}
          {job.source && ` · ${job.source}`}
        </p>

        {/* CTA */}
        {job.url && (
          <a
            href={job.url}
            target="_blank"
            rel="noopener noreferrer"
            className="
              inline-flex items-center gap-1
              pt-2
              text-xs font-medium

              text-[#1CBCE8]
              hover:underline

              dark:text-[#38BDF8]
            "
          >
            Ver oferta original
            <ExternalLink size={13} />
          </a>
        )}
      </div>
    </div>
  );
}
