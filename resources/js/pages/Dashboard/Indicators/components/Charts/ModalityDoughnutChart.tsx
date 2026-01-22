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
  const chartData = {
    labels: data.map((d) => d.modalidad),
    datasets: [
      {
        data: data.map((d) => d.vacantes),
        backgroundColor: [
          "#00B6E8", // remoto
          "#10B981", // híbrido
          "#F97316", // presencial
          "#CBD5E1", // desconocido
        ],
        borderWidth: 1,
      },
    ],
  };

  return (
    <div className="rounded-2xl border bg-white p-6 shadow-sm dark:bg-[#0F2A3A] dark:border-slate-700">
      <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-4">
        Distribución de vacantes por modalidad
      </h3>

      <div className="max-w-md mx-auto">
        <Doughnut
          data={chartData}
          options={{
            plugins: {
              legend: {
                position: "bottom",
                labels: {
                  boxWidth: 12,
                },
              },
            },
          }}
        />
      </div>
    </div>
  );
}
