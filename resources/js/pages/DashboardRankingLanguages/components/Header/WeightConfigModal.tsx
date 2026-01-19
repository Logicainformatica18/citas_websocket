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
}

export const defaultWeights: WeightConfig = {
  laborWeight: 100,
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
  const [laborWeight, setLaborWeight] = useState(100);

  const handleReset = () => {
    setLaborWeight(100);
  };

  const handleApply = () => {
    onSave({ laborWeight: 100 });
    onOpenChange(false);
  };

  /* Preview mock */
  const laborScore = 92.4;
  const finalScore = laborScore;

  useEffect(() => {
    if (open) {
      setLaborWeight(100);
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
            El ranking de lenguajes se calcula exclusivamente en base a la
            demanda laboral real.
          </DialogDescription>
        </DialogHeader>

        {/* ================= PESO ÚNICO ================= */}
        <div className="mt-6 space-y-6">
          <div>
            <div className="mb-2 flex items-center justify-between">
              <div className="flex items-center gap-2 font-medium">
                📊 Demanda Laboral
                <Info className="h-4 w-4 text-muted-foreground" />
              </div>
              <span className="text-xl font-bold text-[#00B6E8]">
                100%
              </span>
            </div>

            {/* Slider bloqueado (visual, no editable) */}
            <input
              type="range"
              min={100}
              max={100}
              step={1}
              value={laborWeight}
              disabled
              className="
                w-full
                h-2
                rounded-full
                bg-gray-200
                appearance-none
                cursor-not-allowed
                accent-[#00B6E8]
              "
            />

            <p className="mt-2 text-xs text-muted-foreground">
              Actualmente, los lenguajes se evalúan únicamente por su presencia
              en ofertas laborales reales.
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
              <div>
                <p className="text-sm text-muted-foreground">Score Final</p>
                <p className="text-xs text-muted-foreground">
                  {laborScore} × 100%
                </p>
              </div>
              <p className="text-4xl font-extrabold text-[#00B6E8]">
                {finalScore.toFixed(1)}
              </p>
            </div>
          </div>

          <p className="mt-3 text-xs text-muted-foreground">
            Fórmula: Score = Demanda laboral normalizada (0 – 100)
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
