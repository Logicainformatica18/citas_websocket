import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { X, Bot, ClipboardList, Sparkles } from "lucide-react";

interface Props {
  competency: any;
  onClose: () => void;
}

export default function CompetencyAIDrawer({ competency, onClose }: Props) {

  const [tab, setTab] = useState<"diagnosis" | "recommendation">("diagnosis");

  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };

    window.addEventListener("keydown", handleEsc);
    return () => window.removeEventListener("keydown", handleEsc);
  }, [onClose]);

  if (!competency) return null;

  /* =========================
     PARSE JSON
  ========================= */

  let diagnosis: any = null;
  let recommendation: any = null;

  try {
    diagnosis = competency.analysis?.diagnosis
      ? JSON.parse(competency.analysis.diagnosis)
      : null;

    recommendation = competency.analysis?.recommendation
      ? JSON.parse(competency.analysis.recommendation)
      : null;

  } catch {
    diagnosis = null;
    recommendation = null;
  }

  return createPortal(
    <div className="fixed inset-0 z-[1000] flex justify-end">

      {/* OVERLAY */}
      <div
        onClick={onClose}
        className="absolute inset-0 bg-black/40 backdrop-blur-sm"
      />

      {/* PANEL */}
      <div className="relative w-[560px] h-screen flex flex-col bg-white dark:bg-slate-900 border-l border-gray-200 dark:border-gray-800 shadow-2xl animate-slide-in">

        {/* HEADER */}
        <div className="sticky top-0 z-10 p-6 flex justify-between items-start bg-gradient-to-r from-[#00B6E8] to-sky-600 dark:from-slate-800 dark:to-slate-900 text-white">
          <div>
            <h2 className="text-lg font-semibold">
              {competency.name}
            </h2>

            <div className="text-xs opacity-80 mt-1">
              Análisis estratégico generado por IA
            </div>
          </div>

          <button
            onClick={onClose}
            className="hover:opacity-70"
          >
            <X size={20} />
          </button>
        </div>

        {/* TABS */}
        <div className="flex border-b border-gray-200 dark:border-gray-800 text-sm bg-gray-50 dark:bg-slate-900">

          <button
            onClick={() => setTab("diagnosis")}
            className={`flex-1 flex items-center justify-center gap-2 py-3 transition ${
              tab === "diagnosis"
                ? "border-b-2 border-[#00B6E8] text-[#00B6E8] font-medium"
                : "text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800"
            }`}
          >
            <ClipboardList size={16} />
            Diagnóstico
          </button>

          <button
            onClick={() => setTab("recommendation")}
            className={`flex-1 flex items-center justify-center gap-2 py-3 transition ${
              tab === "recommendation"
                ? "border-b-2 border-[#F4D03F] text-[#F4D03F] font-medium"
                : "text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800"
            }`}
          >
            <Sparkles size={16} />
            Recomendación
          </button>

        </div>

        {/* CONTENT */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6 text-sm">

          {/* ================= DIAGNÓSTICO ================= */}

          {tab === "diagnosis" && diagnosis && (

            <div className="space-y-6">

              {/* RESUMEN */}
              <div className="rounded-2xl p-5 border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-slate-800">
                <h3 className="font-semibold mb-2 text-[#00B6E8]">
                  Resumen estratégico
                </h3>

                <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                  {diagnosis.summary}
                </p>
              </div>

              <BadgeList
                title="Fortalezas detectadas"
                items={diagnosis.strengths}
                color="blue"
              />

              <BadgeList
                title="Debilidades"
                items={diagnosis.weaknesses}
                color="yellow"
              />

              <BadgeList
                title="Brechas del mercado"
                items={diagnosis.gaps}
                color="red"
              />

            </div>

          )}

          {/* ================= RECOMENDACIÓN ================= */}

          {tab === "recommendation" && recommendation && (

            <div className="space-y-6">

              <BadgeList
                title="Acciones estratégicas"
                items={recommendation.actions}
                color="green"
              />

              <BadgeList
                title="Tecnologías emergentes sugeridas"
                items={recommendation.technologies}
                color="purple"
              />

            </div>

          )}

        </div>

      </div>

    </div>,
    document.body
  );
}

/* =========================
   BADGE LIST
========================= */

function BadgeList({ title, items, color }: any) {

  if (!items || !items.length) return null;

  const colors: any = {

    blue:
      "bg-[#00B6E8]/10 text-[#00B6E8] border-[#00B6E8]/30",

    yellow:
      "bg-[#F4D03F]/20 text-yellow-700 dark:text-yellow-300 border-[#F4D03F]/40",

    red:
      "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300 border-red-200 dark:border-red-700",

    green:
      "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border-emerald-200 dark:border-emerald-700",

    purple:
      "bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border-purple-200 dark:border-purple-700",
  };

  return (
    <div>
      <h3 className="font-semibold mb-3 text-gray-700 dark:text-gray-200">
        {title}
      </h3>

      <div className="flex flex-wrap gap-2">

        {items.map((i: string, index: number) => (

          <span
            key={index}
            className={`px-3 py-1 text-xs rounded-full border ${colors[color]}`}
          >
            {i}
          </span>

        ))}

      </div>

    </div>
  );
}