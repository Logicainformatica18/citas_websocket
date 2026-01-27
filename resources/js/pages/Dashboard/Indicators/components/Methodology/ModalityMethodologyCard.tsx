import { Search, Layers, Percent } from "lucide-react";

export default function ModalityMethodologyCard() {
  return (
    <div
      className="
        rounded-2xl
        border
        border-[#BEE9F7]
        bg-white
        p-6
        shadow-sm
      "
    >
      {/* Header */}
      <div className="mb-6 flex items-center gap-2">
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[#E6F7FD]">
          <Search className="h-4 w-4 text-[#00B6E8]" />
        </div>
        <h3 className="text-base font-bold text-[#0A2540]">
          Metodología de análisis
        </h3>
      </div>

      {/* Steps */}
      <div className="space-y-5 text-sm text-slate-700">

        {/* Paso 1 */}
        <div className="flex gap-3">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E6F7FD]">
            <Search className="h-4 w-4 text-[#00B6E8]" />
          </div>
          <div>
            <p className="font-semibold text-[#0A2540]">1. Identificación</p>
            <p className="text-slate-600">
              Análisis del texto de cada vacante para detectar palabras clave
              relacionadas con la modalidad laboral.
            </p>
          </div>
        </div>

        {/* Paso 2 */}
        <div className="flex gap-3">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E6F7FD]">
            <Layers className="h-4 w-4 text-[#00B6E8]" />
          </div>
          <div>
            <p className="font-semibold text-[#0A2540]">2. Clasificación</p>
            <p className="text-slate-600">
              Cada vacante se clasifica como remota, híbrida o presencial según
              la modalidad predominante detectada.
            </p>
          </div>
        </div>

        {/* Paso 3 */}
        <div className="flex gap-3">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E6F7FD]">
            <Percent className="h-4 w-4 text-[#00B6E8]" />
          </div>
          <div>
            <p className="font-semibold text-[#0A2540]">3. Cálculo</p>
            <p className="text-slate-600">
              Porcentaje = (Vacantes por modalidad / Total de vacantes) × 100
            </p>
          </div>
        </div>
      </div>

      {/* Footer */}
      {/* <div className="mt-6 border-t border-[#BEE9F7] pt-4 text-xs text-slate-500">
        100% basado en demanda laboral real de portales de empleo.
      </div> */}
    </div>
  );
}
