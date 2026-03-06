import { BookOpen, Briefcase, Globe } from "lucide-react";

interface Props {
  final_index: number;
  market_rate: number;
  trend_rate: number;
  gap_total: number;
  aligned_count: number;
  total_courses: number;
}

export default function CourseAlignmentKPI({
  final_index,
  market_rate,
  trend_rate,
  aligned_count,
  total_courses,
}: Props) {

  const percentage = Math.round(final_index);

  const getStatusLabel = () => {
    if (percentage >= 75) return "Excelente alineación";
    if (percentage >= 50) return "Alineación moderada";
    return "Baja alineación";
  };

  const getColor = () => {
    if (percentage >= 75) return "#16a34a";
    if (percentage >= 50) return "#f59e0b";
    return "#ef4444";
  };

  const radius = 65;   // 🔹 más pequeño
  const stroke = 10;
  const normalizedRadius = radius - stroke * 2;
  const circumference = normalizedRadius * 2 * Math.PI;
  const strokeDashoffset =
    circumference - (percentage / 100) * circumference;

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

      {/* ===================== GAUGE PRINCIPAL ===================== */}
      <div className="bg-white dark:bg-[#0A2540] rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center relative">

        <div className="mb-3 p-2 rounded-xl bg-[#E6F7FD] dark:bg-[#123A5A]">
          <BookOpen className="w-5 h-5 text-[#0077B6]" />
        </div>

        <svg height={radius * 2} width={radius * 2}>
          <circle
            stroke="#e5e7eb"
            fill="transparent"
            strokeWidth={stroke}
            r={normalizedRadius}
            cx={radius}
            cy={radius}
          />
          <circle
            stroke={getColor()}
            fill="transparent"
            strokeWidth={stroke}
            strokeDasharray={`${circumference} ${circumference}`}
            style={{
              strokeDashoffset,
              transition: "stroke-dashoffset 0.8s ease",
            }}
            strokeLinecap="round"
            r={normalizedRadius}
            cx={radius}
            cy={radius}
          />
        </svg>

        <div className="absolute flex flex-col items-center">
          <span className="text-3xl font-bold text-gray-800 dark:text-white">
            {percentage}%
          </span>
        </div>

        <div className="mt-2 text-center">
          <p className="font-semibold text-sm" style={{ color: getColor() }}>
            {getStatusLabel()}
          </p>
          <p className="text-xs text-gray-500 mt-1">
            {aligned_count} de {total_courses} cursos
          </p>
        </div>
      </div>

      {/* ===================== TARJETAS DERECHA ===================== */}
      <div className="col-span-1 lg:col-span-2 space-y-6">

        {/* DEMANDA LABORAL */}
        <div className="bg-white dark:bg-[#0A2540] rounded-2xl shadow-md p-6 border-l-4 border-green-500">
          <div className="flex items-center gap-4">
            <div className="p-3 bg-green-100 dark:bg-green-900 rounded-xl">
              <Briefcase className="w-5 h-5 text-green-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Demanda Laboral</p>
              <p className="text-2xl font-bold">
                {Math.round(market_rate)}%
              </p>
              <p className="text-sm text-gray-500">
                {aligned_count} de {total_courses} cursos
              </p>
            </div>
          </div>
        </div>

        {/* TENDENCIAS */}
        <div className="bg-white dark:bg-[#0A2540] rounded-2xl shadow-md p-6 border-l-4 border-purple-500">
          <div className="flex items-center gap-4">
            <div className="p-3 bg-purple-100 dark:bg-purple-900 rounded-xl">
              <Globe className="w-5 h-5 text-purple-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Tendencias</p>
              <p className="text-2xl font-bold">
                {Math.round(trend_rate)}%
              </p>
              <p className="text-sm text-gray-500">
                Alineación prospectiva
              </p>
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}