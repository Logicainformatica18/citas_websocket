import { Dialog } from "@headlessui/react";
import { useEffect, useState } from "react";
import { X } from "lucide-react";

export type WeightConfig = {
  laborWeight: number;
  trendsWeight: number;
};

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  weights: WeightConfig;
  onSave: (weights: WeightConfig) => void;
};

export function WeightConfigModal({
  open,
  onOpenChange,
  weights,
  onSave,
}: Props) {
  const [laborWeight, setLaborWeight] = useState(weights.laborWeight);
  const [trendsWeight, setTrendsWeight] = useState(weights.trendsWeight);

  /* =========================
     SINCRONIZA CUANDO ABRE
  ========================= */
  useEffect(() => {
    if (open) {
      setLaborWeight(weights.laborWeight);
      setTrendsWeight(weights.trendsWeight);
    }
  }, [open, weights]);

  const total = laborWeight + trendsWeight;
  const valid = total === 100;

  return (
    <Dialog open={open} onClose={onOpenChange} className="relative z-50">
      {/* BACKDROP */}
      <div className="fixed inset-0 bg-black/50" />

      {/* WRAPPER */}
      <div className="fixed inset-0 flex items-center justify-center p-4">
        <Dialog.Panel
          className="
            w-full
            max-w-lg
            rounded-2xl
            shadow-xl
            bg-white
            dark:bg-[#0F2A3A]
            border
            dark:border-[#1E3A4A]
          "
        >
          {/* ================= HEADER ================= */}
          <div className="flex items-center justify-between px-6 py-4 border-b dark:border-[#1E3A4A]">
            <Dialog.Title className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              Metodología del ranking
            </Dialog.Title>

            <button
              onClick={() => onOpenChange(false)}
              className="rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-[#123A52]"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          {/* ================= BODY ================= */}
          <div className="px-6 py-6 space-y-6">
            <p className="text-sm text-gray-600 dark:text-slate-300">
              Ajusta la ponderación utilizada para calcular el{" "}
              <span className="font-medium">resultado final</span> del ranking
              de tecnologías.
            </p>

            {/* ===== LABOR ===== */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-800 dark:text-slate-200">
                  Demanda laboral
                </span>
                <span className="text-sm font-semibold text-[#22C55E]">
                  {laborWeight}%
                </span>
              </div>

              <input
                type="range"
                min={0}
                max={100}
                step={5}
                value={laborWeight}
                onChange={(e) =>
                  setLaborWeight(Number(e.target.value))
                }
                className="w-full"
              />
            </div>

            {/* ===== TRENDS ===== */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-800 dark:text-slate-200">
                  Tendencias tecnológicas
                </span>
                <span className="text-sm font-semibold text-[#A855F7]">
                  {trendsWeight}%
                </span>
              </div>

              <input
                type="range"
                min={0}
                max={100}
                step={5}
                value={trendsWeight}
                onChange={(e) =>
                  setTrendsWeight(Number(e.target.value))
                }
                className="w-full"
              />
            </div>

            {/* ===== VALIDACIÓN ===== */}
            <div className="text-sm">
              {valid ? (
                <span className="text-green-600">
                  ✔ Ponderación válida (100%)
                </span>
              ) : (
                <span className="text-red-500">
                  La suma debe ser 100% (actual: {total}%)
                </span>
              )}
            </div>
          </div>

          {/* ================= FOOTER ================= */}
          <div className="flex items-center justify-end gap-3 px-6 py-4 border-t dark:border-[#1E3A4A]">
            <button
              onClick={() => onOpenChange(false)}
              className="
                px-4 py-2
                rounded-lg
                text-sm
                border
                bg-white
                text-slate-700
                hover:bg-gray-100

                dark:bg-[#123A52]
                dark:text-slate-200
                dark:border-[#1E3A4A]
                dark:hover:bg-[#1B4B63]
              "
            >
              Cancelar
            </button>

            <button
              disabled={!valid}
              onClick={() =>
                onSave({
                  laborWeight,
                  trendsWeight,
                })
              }
              className="
                px-4 py-2
                rounded-lg
                text-sm
                font-medium
                transition
                disabled:opacity-40

                bg-[#1CBCE8]
                text-white
                hover:bg-[#17A9D1]
              "
            >
              Aplicar metodología
            </button>
          </div>
        </Dialog.Panel>
      </div>
    </Dialog>
  );
}
