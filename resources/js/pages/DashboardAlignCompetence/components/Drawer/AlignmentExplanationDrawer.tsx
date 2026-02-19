import { useEffect, useState } from "react";
import { X, BarChart3, Sparkles } from "lucide-react";
import AlignmentMethodologyContent from "./AlignmentMethodologyContent";
import AlignmentAIRecommendationTab from "./AlignmentAIRecommendationTab";

interface Props {
  open: boolean;
  onClose: () => void;
  recommendation?: string | null;
  recommendationYear?: number | null;
  recommendationDate?: string | null;
}

export default function AlignmentMethodologyDrawer({
  open,
  onClose,
  recommendation,
  recommendationYear,
  recommendationDate,
}: Props) {

  const [activeTab, setActiveTab] = useState<"method" | "ai">("method");

  useEffect(() => {
    if (!open) return;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = "auto";
    };
  }, [open]);

  useEffect(() => {
    if (!open) return;

    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };

    window.addEventListener("keydown", handleEsc);
    return () => window.removeEventListener("keydown", handleEsc);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">

      {/* Overlay */}
      <div
        onClick={onClose}
        className="absolute inset-0 bg-black/40 backdrop-blur-sm"
      />

      {/* Panel */}
      <div className="relative w-[640px] h-full bg-white shadow-2xl flex flex-col animate-slide-in">

        {/* HEADER */}
        <div className="bg-[#0A2540] text-white p-6 flex justify-between items-start">
          <div>
            <h2 className="text-lg font-semibold">
              Alineación Estratégica
            </h2>
            <div className="text-xs opacity-70 mt-1">
              Metodología + Recomendación IA
            </div>
          </div>

          <button onClick={onClose} className="hover:opacity-70">
            <X size={20} />
          </button>
        </div>

        {/* TABS */}
        <div className="flex border-b text-sm bg-slate-50">

          <button
            onClick={() => setActiveTab("method")}
            className={`flex-1 flex items-center justify-center gap-2 py-3 transition
              ${
                activeTab === "method"
                  ? "border-b-2 border-[#00B6E8] text-[#00B6E8] font-semibold bg-white"
                  : "text-slate-500 hover:bg-slate-100"
              }`}
          >
            <BarChart3 size={16} />
            Metodología
          </button>

          <button
            onClick={() => setActiveTab("ai")}
            className={`flex-1 flex items-center justify-center gap-2 py-3 transition
              ${
                activeTab === "ai"
                  ? "border-b-2 border-[#00B6E8] text-[#00B6E8] font-semibold bg-white"
                  : "text-slate-500 hover:bg-slate-100"
              }`}
          >
            <Sparkles size={16} />
            Recomendación IA
          </button>

        </div>

        {/* CONTENT */}
        <div className="flex-1 overflow-y-auto p-6">

          {activeTab === "method" && (
            <AlignmentMethodologyContent />
          )}

          {activeTab === "ai" && (
            <AlignmentAIRecommendationTab
              recommendation={recommendation}
              year={recommendationYear}
              generatedAt={recommendationDate}
            />
          )}

        </div>

      </div>
    </div>
  );
}
