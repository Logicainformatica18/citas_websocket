import { Doughnut } from "react-chartjs-2";
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from "chart.js";

ChartJS.register(ArcElement, Tooltip, Legend);

interface Item {
  modalidad: string;
  vacantes: number;
  porcentaje: number;
}

interface Props {
  data: Item[];
}

export default function ModalityDoughnutChart({ data }: Props) {

  // Orden fijo de modalidades
  const modalities = ["remoto", "híbrido", "presencial"];

  // Normalizar datos para garantizar las 3 modalidades
  const normalizedData = modalities.map((m) => {
    const found = data.find((d) => d.modalidad === m);
    return found || { modalidad: m, vacantes: 0, porcentaje: 0 };
  });

  const chartData = {
    labels: normalizedData.map((d) => d.modalidad),
    datasets: [
      {
        data: normalizedData.map((d) => d.vacantes),
        backgroundColor: [
          "#00B6E8", // remoto
          "#10B981", // híbrido
          "#F97316", // presencial
        ],
        borderWidth: 1,
      },
    ],
  };

  const chartOptions = {
    plugins: {
      legend: {
        position: "bottom" as const,
        labels: {
          boxWidth: 12,
        },
      },
      tooltip: {
        callbacks: {
          label: function (context: any) {
            const index = context.dataIndex;
            const item = normalizedData[index];
            return `${item.modalidad}: ${item.vacantes} (${item.porcentaje}%)`;
          },
        },
      },
    },
  };

  return (
    <div className="rounded-2xl border bg-white p-6 shadow-sm dark:bg-[#0F2A3A] dark:border-slate-700">
      <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-4">
        Distribución de vacantes por modalidad
      </h3>

      <div className="max-w-md mx-auto">
        <Doughnut data={chartData} options={chartOptions} />
      </div>
    </div>
  );
}
