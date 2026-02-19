import {
  ShieldCheck,
  TrendingUp,
  AlertTriangle,
  CircleSlash,
  Info,
} from "lucide-react";

interface Props {
  onOpenDrawer: () => void;
}

export default function AlignmentExplanationBlock({ onOpenDrawer }: Props) {
  return (
    <div className="bg-white dark:bg-slate-900 border rounded-2xl shadow-sm p-6 space-y-4">

      <div className="flex items-center gap-2">
        <Info className="text-indigo-600" size={18} />
        <h3 className="font-semibold text-slate-800 dark:text-slate-100">
          ¿Cómo interpretar el resultado estratégico?
        </h3>
      </div>

      <div className="grid md:grid-cols-5 gap-4 text-sm">

        <div className="flex items-center gap-2">
          <ShieldCheck className="text-green-600" size={18} />
          <span><strong>Fuerte:</strong> ≥ 80%</span>
        </div>

        <div className="flex items-center gap-2">
          <TrendingUp className="text-yellow-600" size={18} />
          <span><strong>Media:</strong> 60% – 79%</span>
        </div>

        <div className="flex items-center gap-2">
          <AlertTriangle className="text-orange-600" size={18} />
          <span><strong>Débil:</strong> 40% – 59%</span>
        </div>

        <div className="flex items-center gap-2">
          <AlertTriangle className="text-amber-700" size={18} />
          <span><strong>Baja:</strong> 1% – 39%</span>
        </div>

        <div className="flex items-center gap-2">
          <CircleSlash className="text-red-700" size={18} />
          <span><strong>Crítica:</strong> 0%</span>
        </div>

      </div>

      <div className="pt-2">
        <button
          onClick={onOpenDrawer}
          className="text-indigo-600 hover:text-indigo-800 font-medium text-sm"
        >
          Ver metodología completa →
        </button>
      </div>

    </div>
  );
}
