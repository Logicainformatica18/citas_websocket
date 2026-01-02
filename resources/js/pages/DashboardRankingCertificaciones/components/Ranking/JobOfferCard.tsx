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
        border border-gray-200
        bg-white
        p-5
        transition
        hover:shadow-md
        hover:border-[#1CBCE8]
      "
    >
      {/* Enumeración (NO ranking) */}
      <div
        className="
          absolute -top-3 -left-3
          w-8 h-8
          rounded-full
          bg-[#ECFAFD]
          border border-[#A7E5F6]
          flex items-center justify-center
          text-xs font-semibold
          text-[#0A4E61]
        "
      >
        {String(index + 1).padStart(2, "0")}
      </div>

      {/* Contenido */}
      <div className="space-y-2">
        <h3 className="text-sm font-semibold leading-snug">
          {job.title}
        </h3>

        <p className="text-xs text-gray-600">
          {job.company}
          {job.location && ` · ${job.location}`}
        </p>

        <p className="text-xs text-gray-400">
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
              text-xs font-medium
              text-[#1CBCE8]
              pt-2
              hover:underline
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
