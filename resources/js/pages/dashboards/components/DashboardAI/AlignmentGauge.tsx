import { useEffect } from "react";
import { ResponsiveContainer, RadialBarChart, RadialBar } from "recharts";

type Props = {
  data: {
    value: number;
    label: string;
    unit: string;
  };
};

export default function AlignmentGauge({ data }: Props) {
  const chartData = [{ name: data.label, value: data.value }];

  return (
    <div className="bg-gray-800 p-4 rounded-xl shadow-lg text-center">
      <h3 className="text-sm text-gray-400 mb-2">{data.label}</h3>
      <div className="flex justify-center items-center">
        <ResponsiveContainer width={200} height={200}>
          <RadialBarChart innerRadius="80%" outerRadius="100%" data={chartData}>
            <RadialBar
              minAngle={15}
              clockWise
              dataKey="value"
              fill="#3b82f6"
              cornerRadius={10}
            />
          </RadialBarChart>
        </ResponsiveContainer>
      </div>
      <p className="text-2xl font-semibold text-blue-400 mt-2">
        {data.value.toFixed(1)} {data.unit}
      </p>
    </div>
  );
}
