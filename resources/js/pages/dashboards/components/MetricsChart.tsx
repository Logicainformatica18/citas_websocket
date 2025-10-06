import { useEffect, useState, useRef } from "react";
import axios from "axios";
import { Bar } from "react-chartjs-2";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from "chart.js";

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

export default function MetricsChart() {
  const [data, setData] = useState<any>(null);
  const scrollRef = useRef<HTMLDivElement>(null);
  const isDragging = useRef(false);
  const startX = useRef(0);
  const scrollLeft = useRef(0);

  // 🚀 Arrastrar con clic sostenido
  const handleMouseDown = (e: React.MouseEvent) => {
    isDragging.current = true;
    startX.current = e.pageX - (scrollRef.current?.offsetLeft || 0);
    scrollLeft.current = scrollRef.current?.scrollLeft || 0;
  };

  const handleMouseUp = () => {
    isDragging.current = false;
  };

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!isDragging.current) return;
    e.preventDefault();
    const x = e.pageX - (scrollRef.current?.offsetLeft || 0);
    const walk = (x - startX.current) * 1.5;
    if (scrollRef.current) scrollRef.current.scrollLeft = scrollLeft.current - walk;
  };

  useEffect(() => {
    axios.get("/api/ai/metrics").then((res) => {
      setData(res.data.language_demand);
    });
  }, []);

  if (!data) return <div className="p-6 text-gray-400">Cargando métricas...</div>;

  const chartData = {
    labels: data.map((item: any) => item.name),
    datasets: [
      {
        label: "Ofertas registradas",
        data: data.map((item: any) => item.jobs),
        backgroundColor: "#4F8DF3",
        borderRadius: 6,
        barThickness: 40,
      },
    ],
  };

  const chartOptions = {
    responsive: true,
    plugins: {
      legend: { display: false },
      title: {
        display: true,
        text: "Lenguajes más demandados",
        color: "#60A5FA",
        font: { size: 16, weight: "bold" },
      },
    },
    scales: {
      x: {
        ticks: { color: "#9CA3AF" },
        grid: { display: false },
      },
      y: {
        ticks: { color: "#9CA3AF" },
        grid: { color: "rgba(255,255,255,0.05)" },
      },
    },
  };

  return (
    <div
      className="relative bg-gray-800/80 rounded-lg p-4 overflow-hidden select-none"
      ref={scrollRef}
      onMouseDown={handleMouseDown}
      onMouseUp={handleMouseUp}
      onMouseLeave={handleMouseUp}
      onMouseMove={handleMouseMove}
    >
      <div className="min-w-[800px]">
        <Bar data={chartData} options={chartOptions} />
      </div>
      <div className="absolute top-3 right-4 text-xs text-gray-400">
        arrastra para desplazarte →
      </div>
    </div>
  );
}
