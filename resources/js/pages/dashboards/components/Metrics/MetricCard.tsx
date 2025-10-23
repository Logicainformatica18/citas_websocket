import { useEffect, useState } from "react";
import axios from "axios";
import { Loader2, FileDown, FileText } from "lucide-react";
import { Card, CardHeader, CardContent } from "@/components/ui/card";
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";

interface Props {
  metric: string; // ejemplo: 'global-alignment', 'tech-growth'
  title?: string;
  description?: string;
}

export default function MetricCard({ metric, title, description }: Props) {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        const res = await axios.get(`/api/ai/metrics/${metric}`);
        setData(res.data);
      } catch (err) {
        console.error("Error cargando métrica:", err);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [metric]);

  if (loading)
    return (
      <div className="flex justify-center items-center h-40 bg-gray-900 rounded-lg border border-gray-800">
        <Loader2 className="animate-spin text-gray-400 w-6 h-6" />
      </div>
    );

  if (!data)
    return (
      <div className="text-gray-400 text-sm text-center p-4">
        No hay datos para mostrar.
      </div>
    );

  const isList = Array.isArray(data.data);
  const hasValue = data.value !== undefined;

  return (
    <Card className="bg-gray-900 border border-gray-800 text-gray-100 hover:shadow-lg transition">
      <CardHeader className="flex flex-col items-start space-y-2">
        <h3 className="text-lg font-semibold text-blue-400">
          {title || data.label}
        </h3>
        {description && (
          <p className="text-xs text-gray-400 leading-tight">{description}</p>
        )}
      </CardHeader>

      <CardContent className="space-y-3">
        {hasValue && (
          <div className="text-4xl font-bold text-center text-orange-400">
            {data.value}
            <span className="text-lg text-gray-400 ml-1">{data.unit}</span>
          </div>
        )}

        {/* 📊 Si viene una lista tipo ranking */}
        {isList && (
          <div className="overflow-x-auto mt-3">
            <table className="w-full text-sm text-gray-300 border-t border-gray-800">
              <thead>
                <tr className="bg-gray-800/50 text-gray-400 text-xs uppercase">
                  {Object.keys(data.data[0]).map((key) => (
                    <th key={key} className="p-2 text-left capitalize">
                      {key.replace(/_/g, " ")}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.data.map((row: any, i: number) => (
                  <tr
                    key={i}
                    className={`hover:bg-gray-800/30 ${
                      i % 2 === 0 ? "bg-gray-800/10" : ""
                    }`}
                  >
                    {Object.values(row).map((value: any, j: number) => (
                      <td key={j} className="p-2">
                        {typeof value === "number" ? value.toFixed(2) : value}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* 📈 Si la métrica tiene datos temporales (futuro expansion) */}
        {data.data && !isList && data.data.length > 0 && (
          <ResponsiveContainer width="100%" height={200}>
            <LineChart data={data.data}>
              <XAxis dataKey="periodo" stroke="#666" />
              <YAxis stroke="#666" />
              <Tooltip />
              <Line
                type="monotone"
                dataKey="valor"
                stroke="#f97316"
                strokeWidth={2}
              />
            </LineChart>
          </ResponsiveContainer>
        )}

        {/* 📤 Botones de exportación */}
        <div className="flex justify-end gap-2 mt-4">
          <button className="flex items-center gap-1 px-3 py-1 text-xs bg-gray-800 hover:bg-gray-700 rounded">
            <FileDown className="w-3 h-3" /> Excel
          </button>
          <button className="flex items-center gap-1 px-3 py-1 text-xs bg-gray-800 hover:bg-gray-700 rounded">
            <FileText className="w-3 h-3" /> PDF
          </button>
        </div>
      </CardContent>
    </Card>
  );
}
