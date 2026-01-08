import { CertificationRanking } from "../../types/ranking";

type Props = {
  rank: number;
  data: CertificationRanking;
  onClick?: () => void;
};

/* =========================================
   Colores por ranking
========================================= */
const rankColors: Record<number, string> = {
  1: "bg-gradient-to-br from-[#F59E0B] to-[#D97706] text-white", // Oro
  2: "bg-gradient-to-br from-[#9CA3AF] to-[#6B7280] text-white", // Plata
  3: "bg-gradient-to-br from-[#CD7F32] to-[#A16207] text-white", // Bronce
};

const defaultRankColor =
  "bg-gray-200 text-gray-600";

export default function CertificationCard({ rank, data, onClick }: Props) {
  return (
 <div
  onClick={onClick}
  className="
    group
    cursor-pointer
    rounded-2xl
    border
    bg-white
    p-6
    relative
    overflow-hidden

    transition-all
    duration-300
    hover:shadow-xl
    hover:-translate-y-[2px]
    hover:border-[#1CBCE8]

    dark:bg-[#0F2A3A]
    dark:border-[#1E3A4A]
  "
>

      {/* Barra superior decorativa */}
      <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-[#1CBCE8] to-[#6EE7F9]" />

      <div className="flex justify-between items-center gap-6">
        {/* =====================
            INFO IZQUIERDA
        ===================== */}
        <div className="flex-1 space-y-1">
          {/* Ranking + Nombre */}
          <div className="flex items-center gap-3">
            <span
              className={`
                flex items-center justify-center
                w-10 h-10
                rounded-xl
                font-bold
                text-sm
                ${
                  rankColors[rank] ?? defaultRankColor
                }
              `}
            >
              #{rank}
            </span>

         <h3 className="text-base font-semibold uppercase tracking-wide text-slate-900 dark:text-slate-100">

              {data.name}
            </h3>
          </div>

          {/* Vendor + Nivel */}
         <p className="text-xs uppercase tracking-wider text-gray-600 dark:text-slate-300">

            {data.vendor} · NIVEL {data.level}
          </p>

          {/* Categoría */}
          {data.category && (
         <div className="flex items-center gap-2 pt-2 text-xs uppercase tracking-widest text-gray-700 dark:text-slate-300">

              <span className="w-2 h-2 rounded-full bg-[#1CBCE8]" />
              {data.category}
            </div>
          )}

          {/* Roles (opcional) */}
          {data.roles && data.roles.length > 0 && (
            <div className="flex flex-wrap gap-2 pt-2">
              {data.roles.slice(0, 2).map((role) => (
               <span
  className="
    rounded-full
    border
    px-3 py-1
    text-xs
    uppercase
    tracking-wide
    text-gray-700
    bg-gray-50
    dark:bg-[#123A52]
    dark:text-slate-300
    dark:border-[#1E3A4A]
  "
>

                  {role}
                </span>
              ))}

              {data.roles.length > 2 && (
                <span className="text-xs text-gray-400 uppercase px-2 py-1">
                  +{data.roles.length - 2} MÁS
                </span>
              )}
            </div>
          )}
        </div>

        {/* =====================
            VACANTES (FOCO)
        ===================== */}
        <div className="flex flex-col items-end">
          <span className="text-4xl font-extrabold text-[#0EA5E9] leading-none">
            {data.total_jobs}
          </span>
          <span className="text-xs font-semibold text-gray-500 uppercase tracking-widest mt-1">
            VACANTES
          </span>
        </div>
      </div>
    </div>
  );
}
