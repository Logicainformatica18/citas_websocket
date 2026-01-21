export default function MethodologyCard() {
  return (
    <div className="border rounded-xl p-4 text-sm bg-[#F5FCFE] dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
      <p className="font-semibold mb-2 text-slate-900 dark:text-slate-100">
        Metodología de Cálculo
      </p>

      <ul className="space-y-1 text-slate-700 dark:text-slate-300">
        <li>• Demanda laboral de tecnologías</li>
        <li>• Presencia en reportes de tendencias tecnológicas</li>
      </ul>

      <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
        El score final se calcula ponderando la demanda laboral y la
        relevancia en reportes de tendencias, según la metodología
        seleccionada.
      </p>

      <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
        Fórmula general: <br />
        <span className="font-mono">
          Score = (Laboral × peso₁) + (Tendencias × peso₂)
        </span>
      </p>
    </div>
  );
}
