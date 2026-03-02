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
  weights?: WeightConfig;
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
  const [laborWeight, setLaborWeight] = useState(
    weights?.laborWeight ?? defaultWeights.laborWeight
  );

  const [trendsWeight, setTrendsWeight] = useState(
    weights?.trendsWeight ?? defaultWeights.trendsWeight
  );

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

  /* =========================
     Preview mock
  ========================= */
  const laborScore = 88.4;
  const trendsScore = 92.1;

  const safeLabor = Number(laborWeight) || 0;
  const safeTrends = Number(trendsWeight) || 0;

  const finalScore =
    laborScore * (safeLabor / 100) +
    trendsScore * (safeTrends / 100);

  useEffect(() => {
    if (open) {
      setLaborWeight(weights?.laborWeight ?? defaultWeights.laborWeight);
      setTrendsWeight(weights?.trendsWeight ?? defaultWeights.trendsWeight);
    }
  }, [open, weights]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl rounded-2xl p-6">
        {/* ================= HEADER ================= */}
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-xl">
            <Calculator className="h-5 w-5 text-[#00B6E8]" />
            Configuración de Metodología
          </DialogTitle>
          <DialogDescription>
            Ajusta el peso relativo de cada criterio. Ambos deben sumar 100%.
          </DialogDescription>
        </DialogHeader>

        {/* ================= RANGES ================= */}
        <div className="mt-6 space-y-8">
          {/* Demanda Laboral */}
          <div>
            <div className="mb-2 flex items-center justify-between">
              <div className="flex items-center gap-2 font-medium">
                📊 {laborWeight} % Demanda Laboral
                <Info className="h-4 w-4 text-muted-foreground" />
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
              onChange={(e) =>
                handleLaborChange(Number(e.target.value))
              }
              className="w-full h-2 rounded-full bg-gray-200 appearance-none cursor-pointer accent-[#00B6E8]"
            />
          </div>

          {/* Tendencias Tecnológicas */}
          <div>
            <div className="mb-2 flex items-center justify-between">
              <div className="flex items-center gap-2 font-medium">
                📈 {trendsWeight} % Tendencias Tecnológicas
                <Info className="h-4 w-4 text-muted-foreground" />
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
              onChange={(e) =>
                handleTrendsChange(Number(e.target.value))
              }
              className="w-full h-2 rounded-full bg-gray-200 appearance-none cursor-pointer accent-emerald-500"
            />
          </div>
        </div>

        {/* ================= PREVIEW ================= */}
        <div className="mt-8 rounded-xl border bg-[#E6F7FD] p-5">
          <div className="mb-2 flex items-center gap-2 font-medium">
            🧮 Preview del Cálculo
            <span className="text-sm text-muted-foreground">
              (Ejemplo: React, Python, AWS)
            </span>
          </div>

          <div className="mt-3 rounded-lg bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">
                  Score Final
                </p>
                <p className="text-xs text-muted-foreground">
                  ({laborScore} × {safeLabor}%) + ({trendsScore} × {safeTrends}%)
                </p>
              </div>
              <p className="text-4xl font-extrabold text-[#00B6E8]">
                {finalScore.toFixed(1)}
              </p>
            </div>
          </div>

          <p className="mt-3 text-xs text-muted-foreground">
            Fórmula: Score = (Laboral × {safeLabor}%) + (Tendencias × {safeTrends}%)
          </p>
        </div>

        <Separator className="my-6" />

        {/* ================= ACTIONS ================= */}
        <div className="flex justify-between">
          <Button variant="outline" onClick={handleReset}>
            Restablecer
          </Button>

          <Button
            className="bg-[#00B6E8] hover:bg-[#009FCC]"
            onClick={handleApply}
          >
            Aplicar metodología
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
