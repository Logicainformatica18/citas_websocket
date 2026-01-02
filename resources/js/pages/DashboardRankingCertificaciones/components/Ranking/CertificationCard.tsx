import { CertificationRanking } from "../../types/ranking";

type Props = {
  rank: number;
  data: CertificationRanking;
  onClick?: () => void;
};

export default function CertificationCard({ rank, data, onClick }: Props) {
  return (
    <div
      onClick={onClick}
      className="
        cursor-pointer
        rounded-xl
        border
        p-5
        bg-white
        hover:shadow-md
        hover:border-[#1CBCE8]
        transition
      "
    >
      <div className="flex justify-between items-center">
        {/* =====================
            INFO PRINCIPAL
        ===================== */}
        <div>
          <h3 className="text-base font-semibold">
            #{rank} {data.name}
          </h3>

          <p className="text-sm text-gray-500">
            {data.vendor} · Nivel {data.level}
          </p>
        </div>

        {/* =====================
            VACANTES
        ===================== */}
        <div className="text-right">
          <p className="text-2xl font-bold text-[#1CBCE8]">
            {data.total_jobs}
          </p>
          <p className="text-xs text-gray-500">
            vacantes
          </p>
        </div>
      </div>
    </div>
  );
}
