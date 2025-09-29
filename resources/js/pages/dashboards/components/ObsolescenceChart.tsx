import { useEffect, useState } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { PieChart, Pie, Cell } from "recharts";

const value = 100; // porcentaje actual
const data = [
  { name: "verde", value: 25 },
  { name: "amarillo", value: 50 },
  { name: "rojo", value: 25 },
];

const COLORS = ["#27ae60", "#f1c40f", "#e74c3c"];

export default function ObsolescenceGauge() {
  const [angle, setAngle] = useState(-90); // inicia en 0%
  
  useEffect(() => {
    // al montar o actualizar value, se actualiza el ángulo con animación
    const newAngle = (value / 100) * 180 - 90;
    setAngle(newAngle);
  }, [value]);

  return (
    <Card className="bg-[#111] text-white rounded-xl p-6">
      <CardContent className="flex flex-col items-center">
        <h2 className="text-md font-bold text-center leading-tight">
          NIVEL DE OBSOLESCENCIA <br /> SOBRE IA
        </h2>
        <p className="text-xs text-gray-300 mb-4">
          EN PROGRAMAS DE TECNOLOGÍA
        </p>

        {/* Gauge */}
        <div className="relative w-64 h-40">
          <PieChart width={250} height={140}>
            <Pie
              data={data}
              cx="50%"
              cy="100%"
              startAngle={180}
              endAngle={0}
              innerRadius={70}
              outerRadius={90}
              dataKey="value"
            >
              {data.map((entry, index) => (
                <Cell key={index} fill={COLORS[index % COLORS.length]} />
              ))}
            </Pie>
          </PieChart>

          {/* Aguja triangular animada */}
          <div
            className="absolute left-1/2 bottom-[30px] w-0 h-0 transition-transform duration-1500 ease-out"
            style={{
              transform: `translateX(-50%) rotate(${angle}deg)`,
              transformOrigin: "bottom center",
              borderLeft: "10px solid transparent",
              borderRight: "10px solid transparent",
              borderBottom: "80px solid #444", // color de la aguja
            }}
          ></div>

          {/* Círculo central */}
          <div className="absolute left-1/2 bottom-[18px] w-6 h-6 bg-gray-300 rounded-full transform -translate-x-1/2"></div>
        </div>

        {/* Texto debajo */}
        <div className="mt-2 text-3xl font-bold">{value}%</div>
      </CardContent>
    </Card>
  );
}
