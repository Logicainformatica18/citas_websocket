import * as Dialog from "@radix-ui/react-dialog";
import { X, SlidersHorizontal } from "lucide-react";
import { useEffect, useState } from "react";

/* ======================================================
   TYPES
====================================================== */
export interface WeightConfig {
  laborWeight: number;   // %
  trendsWeight: number; // %
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  weights: WeightConfig;
  onSave: (weights: WeightConfig) => void;
}

/* ======================================================
   COMPONENT
====================================================== */
export function WeightConfigModal({
  open,
  onOpenChange,
  weights,
  onSave,
}: Props) {
  const [localWeights, setLocalWeights] = useState<WeightConfig>(weights);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLocalWeights(weights);
    setError(null);
  }, [weights, open]);

  /* =========================
     HANDLERS
  ========================= */
  const updateLabor = (value: number) => {
    const labor = Math.min(Math.max(value, 0), 100);
    setLocalWeights({
      laborWeight: labor,
      trendsWeight: 100 - labor,
    });
  };

  const updateTrend = (value: number) => {
    const trend = Math.min(Math.max(value, 0), 100);
    setLocalWeights({
      laborWeight: 100 - trend,
      trendsWeight: trend,
    });
  };

  const handleSave = () => {
    if (localWeights.laborWeight + localWeights.trendsWeight !== 100) {
      setError("Las ponderaciones deben sumar 100%");
      return;
    }

    setError(null);
    onSave(localWeights);
    onOpenChange(false);
  };

  /* =========================
     RENDER
  ========================= */
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 bg-black/40 z-40" />

        <Dialog.Content
          className="
            fixed
            z-50
            left-1/2
            top-1/2
            w-full
            max-w-lg
            -translate-x-1/2
            -translate-y-1/2
            rounded-2xl
            bg-white
            p-6
            shadow-2xl
            dark:bg-[#0F2A3A]
          "
        >
          {/* ===== HEADER ===== */}
          <div className="mb-4 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <SlidersHorizontal className="h-5 w-5 text-[#00B6E8]" />
              <Dialog.Title className="text-lg font-bold text-[#0A2540] dark:text-slate-100">
                Metodología de cálculo
              </Dialog.Title>
            </div>

            <button
              onClick={() => onOpenChange(false)}
              className="rounded-md p-1 hover:bg-slate-100 dark:hover:bg-[#123A52]"
            >
              <X className="h-4 w-4 text-slate-500" />
            </button>
          </div>

          {/* ===== DESCRIPTION ===== */}
          <p className="mb-6 text-sm text-slate-600 dark:text-slate-300">
            Ajusta la importancia relativa entre la demanda del mercado laboral
            y la alineación con tendencias estratégicas del futuro.
          </p>

          {/* ===== SLIDERS ===== */}
          <div className="space-y-6">

            {/* ===== LABOR ===== */}
            <div>
              <div className="mb-1 flex items-center justify-between">
                <span className="text-sm font-semibold text-[#0A2540] dark:text-slate-200">
                  Demanda laboral
                </span>
                <span className="text-sm font-bold text-[#00B6E8]">
                  {localWeights.laborWeight}%
                </span>
              </div>

              <input
                type="range"
                min={0}
                max={100}
                step={5}
                value={localWeights.laborWeight}
                onChange={(e) => updateLabor(Number(e.target.value))}
                className="w-full accent-[#00B6E8]"
              />
            </div>

            {/* ===== TRENDS ===== */}
            <div>
              <div className="mb-1 flex items-center justify-between">
                <span className="text-sm font-semibold text-[#0A2540] dark:text-slate-200">
                  Tendencias y prospectiva
                </span>
                <span className="text-sm font-bold text-[#00B6E8]">
                  {localWeights.trendsWeight}%
                </span>
              </div>

              <input
                type="range"
                min={0}
                max={100}
                step={5}
                value={localWeights.trendsWeight}
                onChange={(e) => updateTrend(Number(e.target.value))}
                className="w-full accent-[#00B6E8]"
              />
            </div>
          </div>

          {/* ===== ERROR ===== */}
          {error && (
            <p className="mt-4 text-sm text-red-600">
              {error}
            </p>
          )}

          {/* ===== FORMULA ===== */}
          <div className="mt-6 rounded-xl border bg-[#E6F7FD] p-4 text-xs text-[#0A2540] dark:bg-[#123A52] dark:text-slate-300">
            <p className="font-semibold mb-1">Fórmula aplicada</p>
            <p>
              Score = ({localWeights.laborWeight / 100} × Coincidencia Mercado)
              + ({localWeights.trendsWeight / 100} × Coincidencia Prospectiva)
            </p>
          </div>

          {/* ===== ACTIONS ===== */}
          <div className="mt-6 flex justify-end gap-3">
            <button
              onClick={() => onOpenChange(false)}
              className="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#123A52]"
            >
              Cancelar
            </button>

            <button
              onClick={handleSave}
              className="rounded-xl bg-[#00B6E8] px-5 py-2 text-sm font-semibold text-white hover:bg-[#009FCB]"
            >
              Aplicar metodología
            </button>
          </div>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
