export function SeniorityMethodologyCard() {
  return (
    <div className="border rounded-xl p-5 bg-[#F5FCFE] dark:bg-[#0F2A3A] dark:border-[#1E3A4A] text-sm">
      <p className="font-semibold mb-3 text-slate-900 dark:text-slate-100">
        Metodología de cálculo
      </p>

      <ul className="space-y-2 text-slate-700 dark:text-slate-300">
        <li>
          • Identificación del nivel de seniority solicitado en cada vacante
          (junior, mid, senior y equivalentes).
        </li>
        <li>
          • Asociación de vacantes a carreras académicas mediante competencias
          requeridas.
        </li>
        <li>
          • Cálculo porcentual del seniority por carrera en función del total de
          vacantes analizadas.
        </li>
      </ul>

      <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
        % del nivel = (vacantes del nivel ÷ total de vacantes de la carrera) × 100
      </p>
    </div>
  );
}
