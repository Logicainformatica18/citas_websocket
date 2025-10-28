import { ResponsiveContainer, RadialBarChart, RadialBar } from "recharts";
import { motion } from "framer-motion";
import { TrendingUp, AlertTriangle, ThumbsUp } from "lucide-react";

type Props = {
  data?: {
    value?: number;
    label?: string;
    unit?: string;
    timestamp?: string; // opcional, para mostrar fecha de actualización
  };
};

export default function AlignmentGauge({ data }: Props) {
  const value = data?.value ?? 0;
  const label = data?.label ?? "Alineación Global";
  const unit = data?.unit ?? "%";
  const timestamp = data?.timestamp ?? null;

  // === Cálculo de color y texto descriptivo ===
  const getColor = (v: number) => {
    if (v >= 80) return "#10B981"; // verde
    if (v >= 60) return "#FBBF24"; // amarillo
    return "#EF4444"; // rojo
  };

  const getDescriptor = (v: number) => {
    if (v >= 80) return { text: "Excelente", icon: <ThumbsUp className="w-4 h-4 text-green-400" /> };
    if (v >= 60) return { text: "Moderado", icon: <TrendingUp className="w-4 h-4 text-yellow-400" /> };
    return { text: "Bajo", icon: <AlertTriangle className="w-4 h-4 text-red-400" /> };
  };

  const color = getColor(value);
  const descriptor = getDescriptor(value);
  const chartData = [{ name: label, value }];

  return (
    <div className="bg-gradient-to-b from-gray-900 to-gray-800 p-5 rounded-2xl shadow-lg text-center border border-gray-700">
      <h3 className="text-sm font-medium text-gray-400 mb-3 tracking-wide uppercase">
        {label}
      </h3>

      {/* === Gauge === */}
      <div className="relative flex justify-center items-center">
        <ResponsiveContainer width={220} height={220}>
          <RadialBarChart
            innerRadius="80%"
            outerRadius="100%"
            barSize={14}
            data={chartData}
            startAngle={225}
            endAngle={-45}
          >
            <RadialBar
              dataKey="value"
              fill={color}
              cornerRadius={10}
              clockWise
              background={{ fill: "#1f2937" }}
            />
          </RadialBarChart>
        </ResponsiveContainer>

        {/* === Valor central animado === */}
        <motion.div
          key={value}
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.5 }}
          className="absolute text-center"
        >
          <p className="text-4xl font-bold" style={{ color }}>
            {value.toFixed(1)}
            <span className="text-xl text-gray-400 ml-1">{unit}</span>
          </p>
          <div className="flex items-center justify-center gap-1 text-sm text-gray-300 mt-1">
            {descriptor.icon}
            <span>{descriptor.text}</span>
          </div>
        </motion.div>
      </div>

      {/* === Footer meta info === */}
      {timestamp && (
        <p className="text-xs text-gray-500 mt-3">
          Última actualización: {new Date(timestamp).toLocaleString()}
        </p>
      )}
    </div>
  );
}
