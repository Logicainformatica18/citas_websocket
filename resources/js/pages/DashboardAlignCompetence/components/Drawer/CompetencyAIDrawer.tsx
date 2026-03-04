import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { X, Bot, ClipboardList } from "lucide-react";

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

      <div
        onClick={onClose}
        className="absolute inset-0 bg-black/40 backdrop-blur-sm"
      />

      <div className="relative w-[560px] h-screen flex flex-col bg-white dark:bg-slate-900 border-l border-gray-200 dark:border-gray-800 shadow-2xl animate-slide-in">

        {/* HEADER */}
        <div className="sticky top-0 z-10 p-6 flex justify-between items-start bg-gradient-to-r from-indigo-600 to-indigo-700 dark:from-slate-800 dark:to-slate-900 text-white">
          <div>
            <h2 className="text-lg font-semibold">
              {competency.name}
            </h2>

            <div className="text-xs opacity-80 mt-1">
              Recomendación estratégica IA
            </div>
          </div>

          <button onClick={onClose}>
            <X size={20} />
          </button>
        </div>

        {/* TABS */}
        <div className="flex border-b border-gray-200 dark:border-gray-800 text-sm bg-gray-50 dark:bg-slate-900">

          <button
            onClick={() => setTab("diagnosis")}
            className={`flex-1 flex items-center justify-center gap-2 py-3 transition ${
              tab === "diagnosis"
                ? "border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400 font-medium"
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
                ? "border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400 font-medium"
                : "text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-800"
            }`}
          >
            <Bot size={16} />
            Recomendación
          </button>

        </div>

        {/* CONTENT */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6 text-sm">

          {/* DIAGNÓSTICO */}
          {tab === "diagnosis" && diagnosis && (

            <div className="rounded-2xl p-6 border border-indigo-200 dark:border-indigo-800 bg-white dark:bg-slate-800 space-y-5">

              <div>
                <h3 className="font-semibold mb-2">Resumen</h3>
                <p className="text-gray-600 dark:text-gray-300">
                  {diagnosis.summary}
                </p>
              </div>

              <List title="Fortalezas" items={diagnosis.strengths} />
              <List title="Debilidades" items={diagnosis.weaknesses} />
              <List title="Brechas detectadas" items={diagnosis.gaps} />

            </div>
          )}

          {/* RECOMENDACIÓN */}
          {tab === "recommendation" && recommendation && (

            <div className="rounded-2xl p-6 border border-sky-200 dark:border-sky-800 bg-white dark:bg-slate-800 space-y-5">

              <List title="Acciones estratégicas" items={recommendation.actions} />
              <List title="Tecnologías emergentes" items={recommendation.technologies} />

            </div>

          )}

        </div>

      </div>

    </div>,
    document.body
  );
}

/* =========================
   LIST COMPONENT
========================= */

function List({ title, items }: any) {

  if (!items || !items.length) return null;

  return (
    <div>
      <h3 className="font-semibold mb-2">{title}</h3>

      <ul className="list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-300">
        {items.map((i: string, index: number) => (
          <li key={index}>{i}</li>
        ))}
      </ul>
    </div>
  );
}