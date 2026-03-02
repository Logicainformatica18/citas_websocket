interface MethodologyCardProps {
  laborWeight?: number;
  trendWeight?: number;
}

export default function MethodologyCard({
  laborWeight = 70,
  trendWeight = 30,
}: MethodologyCardProps) {
  const labor = Number(laborWeight) || 70;
  const trend = Number(trendWeight) || 100 - labor;

  const laborFactor = (labor / 100).toFixed(1);
  const trendFactor = (trend / 100).toFixed(1);

  return (
    <div className="border rounded-xl p-4 text-sm bg-[#F5FCFE] dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
      <p className="font-semibold mb-2 text-slate-900 dark:text-slate-100">
        Metodología de Cálculo
      </p>

      <ul className="space-y-1 text-slate-700 dark:text-slate-300">
        <li>
          • <strong>{labor}%</strong> Demanda laboral de lenguajes
        </li>
        <li>
          • <strong>{trend}%</strong> Presencia en reportes de tendencias tecnológicas
        </li>
      </ul>

      <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
        El score final se calcula ponderando la demanda laboral y la
        evidencia en reportes especializados, según la metodología
        seleccionada.
      </p>

      <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
        Fórmula general: <br />
        <span className="font-mono">
          Score = ({laborFactor} × Laboral) + ({trendFactor} × Tendencias)
        </span>
      </p>
    </div>
  );
}
