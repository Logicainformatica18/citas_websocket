interface Props {
  title: string;
  value: string | number;
  score?: number;
  trend?: number;
}

export default function KpiCard({ title, value, score, trend }: Props) {
  return (
    <div className="border rounded-xl p-4 bg-white">
      <p className="text-sm text-gray-500">{title}</p>
      <p className="text-xl font-bold">{value}</p>

      {score && <p className="text-[#1CBCE8] font-bold">Score {score}</p>}
      {trend && <p className="text-green-500 text-sm">↑ {trend}%</p>}
    </div>
  );
}
