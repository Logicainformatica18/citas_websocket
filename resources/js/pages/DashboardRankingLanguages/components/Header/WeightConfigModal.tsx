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
  trendWeight: number;
}

export const defaultWeights: WeightConfig = {
  laborWeight: 70,
  trendWeight: 30,
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
  const [laborWeight, setLaborWeight] = useState(70);
  const [trendWeight, setTrendWeight] = useState(30);

  /* ================= RESET ================= */
  const handleReset = () => {
    setLaborWeight(70);
    setTrendWeight(30);
  };

  /* ================= APPLY ================= */
  const handleApply = () => {
    if (laborWeight + trendWeight !== 100) return;

    onSave({
      laborWeight,
      trendWeight,
    });

    onOpenChange(false);
  };

  /* ================= PREVIEW ================= */
  const laborScore = 92.4;
  const trendScore = 65.0;

  const finalScore =
    laborScore * (laborWeight / 100) +
    trendScore * (trendWeight / 100);

  /* ================= SYNC ================= */
  useEffect(() => {
    if (open) {
      setLaborWeight(weights.laborWeight ?? 70);
      setTrendWeight(weights.trendWeight ?? 30);
    }
  }, [open, weights]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl rounded-2xl p-6">
        {/* ================= HEADER ================= */}
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-xl">
            <Calculator className="h-5 w-5 text-[#00B6E8]" />
            Metodología de Cálculo
          </DialogTitle>
          <DialogDescription>
            El ranking de lenguajes combina demanda laboral y evidencia
            de tendencias tecnológicas.
          </DialogDescription>
        </DialogHeader>

        {/* ================= PESOS ================= */}
        <div className="mt-6 space-y-6">

          {/* ===== LABOR ===== */}
          <div>
            <div className="mb-2 flex items-center justify-between">
              <div className="flex items-center gap-2 font-medium">
                📊 Demanda Laboral
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
              onChange={(e) => {
                const value = Number(e.target.value);
                setLaborWeight(value);
                setTrendWeight(100 - value);
              }}
              className="w-full h-2 rounded-full accent-[#00B6E8]"
            />

            <p className="mt-2 text-xs text-muted-foreground">
              Basado en ofertas laborales reales del período seleccionado.
            </p>
          </div>

          {/* ===== TRENDS ===== */}
          <div>
            <div className="mb-2 flex items-center justify-between">
              <div className="flex items-center gap-2 font-medium">
                📈 Tendencias Tecnológicas
                <Info className="h-4 w-4 text-muted-foreground" />
              </div>
              <span className="text-xl font-bold text-purple-600">
                {trendWeight}%
              </span>
            </div>

            <input
              type="range"
              min={0}
              max={100}
              step={1}
              value={trendWeight}
              onChange={(e) => {
                const value = Number(e.target.value);
                setTrendWeight(value);
                setLaborWeight(100 - value);
              }}
              className="w-full h-2 rounded-full accent-purple-500"
            />

            <p className="mt-2 text-xs text-muted-foreground">
              Evidencia externa proveniente de reportes especializados.
            </p>
          </div>
        </div>

        {/* ================= PREVIEW ================= */}
        <div className="mt-8 rounded-xl border bg-[#E6F7FD] p-5">
          <div className="mb-2 flex items-center gap-2 font-medium">
            🧮 Ejemplo de cálculo
            <span className="text-sm text-muted-foreground">
              (Lenguaje: Python)
            </span>
          </div>

          <div className="mt-3 rounded-lg bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div className="text-sm text-muted-foreground">
                <p>{laborScore} × {laborWeight}%</p>
                <p>{trendScore} × {trendWeight}%</p>
              </div>

              <p className="text-4xl font-extrabold text-[#00B6E8]">
                {finalScore.toFixed(1)}
              </p>
            </div>
          </div>

          <p className="mt-3 text-xs text-muted-foreground">
            Score = (Labor × peso) + (Tendencias × peso)
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
            disabled={laborWeight + trendWeight !== 100}
          >
            Aplicar metodología
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
