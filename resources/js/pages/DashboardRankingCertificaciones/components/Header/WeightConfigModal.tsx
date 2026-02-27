import { useEffect, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { Info, Calculator } from "lucide-react";

/* =========================================================
   Types
========================================================= */

export interface WeightConfig {
  laborWeight: number;
  trendsWeight: number;
}

export const defaultWeights: WeightConfig = {
  laborWeight: 70,
  trendsWeight: 30,
};

interface WeightConfigModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  weights: WeightConfig;
  onSave: (weights: WeightConfig) => void;
}

/* =========================================================
   Component
========================================================= */

export function WeightConfigModal({
  open,
  onOpenChange,
  weights,
  onSave,
}: WeightConfigModalProps) {
  const [laborWeight, setLaborWeight] = useState(weights.laborWeight);
  const [trendsWeight, setTrendsWeight] = useState(weights.trendsWeight);

  const handleLaborChange = (v: number) => {
    setLaborWeight(v);
    setTrendsWeight(100 - v);
  };

  const handleTrendsChange = (v: number) => {
    setTrendsWeight(v);
    setLaborWeight(100 - v);
  };

  const handleReset = () => {
    setLaborWeight(defaultWeights.laborWeight);
    setTrendsWeight(defaultWeights.trendsWeight);
  };

  const handleApply = () => {
    onSave({ laborWeight, trendsWeight });
    onOpenChange(false);
  };

  /* Preview mock */
  const laborScore = 91.1;
  const trendsScore = 89.2;
  const finalScore =
    laborScore * (laborWeight / 100) +
    trendsScore * (trendsWeight / 100);

  useEffect(() => {
    if (open) {
      setLaborWeight(weights.laborWeight);
      setTrendsWeight(weights.trendsWeight);
    }
  }, [open, weights]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="
          max-w-xl
          rounded-2xl
          p-6
          bg-white
          dark:bg-slate-900
          border
          border-slate-200
          dark:border-slate-700
        "
      >
        {/* ================= HEADER ================= */}
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-xl font-semibold text-slate-800 dark:text-slate-100">
            <Calculator className="h-5 w-5 text-[#00B6E8]" />
            Configuración de Ponderaciones
          </DialogTitle>
          <DialogDescription className="text-slate-500 dark:text-slate-400">
            Ajuste los pesos de cada criterio. Ambos deben sumar 100%.
          </DialogDescription>
        </DialogHeader>

        {/* ================= RANGES ================= */}
        <div className="mt-6 space-y-8">
          {/* Demanda Laboral */}
          <div>
            <div className="mb-2 flex items-center justify-between">
              <div className="flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200">
                📊 Demanda Laboral
                <Info className="h-4 w-4 text-slate-400 dark:text-slate-500" />
              </div>
              <span className="text-xl font-bold text-[#00B6E8]">
                {laborWeight}%
              </span>
            </div>

            <input
              type="range"
              min={0}
              max={100}
              step={1}
              value={laborWeight}
              onChange={(e) => handleLaborChange(Number(e.target.value))}
              className="
                w-full
                h-2
                rounded-full
                appearance-none
                cursor-pointer
                bg-slate-200
                dark:bg-slate-700
                accent-[#00B6E8]
              "
            />
          </div>

          {/* Tendencias Tecnológicas */}
          <div>
            <div className="mb-2 flex items-center justify-between">
              <div className="flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200">
                📈 Tendencias Tecnológicas
                <Info className="h-4 w-4 text-slate-400 dark:text-slate-500" />
              </div>
              <span className="text-xl font-bold text-emerald-500">
                {trendsWeight}%
              </span>
            </div>

            <input
              type="range"
              min={0}
              max={100}
              step={1}
              value={trendsWeight}
              onChange={(e) => handleTrendsChange(Number(e.target.value))}
              className="
                w-full
                h-2
                rounded-full
                appearance-none
                cursor-pointer
                bg-slate-200
                dark:bg-slate-700
                accent-emerald-500
              "
            />
          </div>
        </div>

        {/* ================= PREVIEW ================= */}
        <div
          className="
            mt-8
            rounded-xl
            border
            p-5
            bg-sky-50
            dark:bg-slate-800
            border-sky-100
            dark:border-slate-700
          "
        >
          <div className="mb-2 flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200">
            🧮 Preview del Cálculo
            <span className="text-sm text-slate-500 dark:text-slate-400">
              (AWS Solutions Architect Associate)
            </span>
          </div>

          <div
            className="
              mt-3
              rounded-lg
              p-4
              bg-white
              dark:bg-slate-900
              border
              border-slate-200
              dark:border-slate-700
            "
          >
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  Score Final
                </p>
                <p className="text-xs text-slate-400 dark:text-slate-500">
                  ({laborScore} × {laborWeight}%) + ({trendsScore} ×{" "}
                  {trendsWeight}%)
                </p>
              </div>
              <p className="text-4xl font-extrabold text-[#00B6E8]">
                {finalScore.toFixed(1)}
              </p>
            </div>
          </div>

          <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
            Fórmula: Score = (Laboral × {laborWeight}%) + (Tendencias ×{" "}
            {trendsWeight}%)
          </p>
        </div>

        <Separator className="my-6 bg-slate-200 dark:bg-slate-700" />

        {/* ================= ACTIONS ================= */}
        <div className="flex justify-between">
          <Button
            variant="outline"
            className="border-slate-300 dark:border-slate-600 dark:text-slate-200"
            onClick={handleReset}
          >
            Restablecer
          </Button>

          <Button
            className="
              bg-[#00B6E8]
              hover:bg-[#009FCC]
              text-white
              shadow-sm
            "
            onClick={handleApply}
          >
            Aplicar ponderaciones
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
