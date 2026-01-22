export default function ModalityMethodologyCard() {
  return (
    <div className="rounded-xl border p-4 text-sm bg-[#F5FCFE] dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
      <p className="font-semibold mb-2 text-slate-900 dark:text-slate-100">
        Metodología del indicador
      </p>

      <ul className="space-y-1 text-slate-700 dark:text-slate-300">
        <li>• Se analizan vacantes provenientes de portales de empleo</li>
        <li>• La modalidad se identifica a partir del campo <code>modality</code></li>
        <li>• Se clasifican en remoto, híbrido o presencial</li>
      </ul>

      <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
        El porcentaje se calcula como la proporción de vacantes de cada
        modalidad respecto al total analizado.
      </p>
    </div>
  );
}
