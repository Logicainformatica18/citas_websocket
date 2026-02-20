import ReactMarkdown from "react-markdown";
import {
  Sparkles,
} from "lucide-react";

interface Props {
  recommendation?: string | null;
  year?: number | null;
  generatedAt?: string | null;
}

export default function AlignmentAIRecommendationTab({
  recommendation,
  year,
  generatedAt,
}: Props) {

  if (!recommendation) {
    return (
      <div className="text-center py-20 text-slate-500">
        <Sparkles size={28} className="mx-auto mb-3 opacity-50" />
        <div className="font-semibold">
          No existe recomendación generada
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-8">

      {/* META */}
      <div className="bg-slate-50 border rounded-xl p-4 text-xs text-slate-600">
        <div><strong>Año analizado:</strong> {year}</div>
        <div><strong>Generado el:</strong> {generatedAt}</div>
      </div>

      {/* CONTENIDO MARKDOWN */}
      <div className="bg-white border rounded-xl p-8 shadow-sm prose prose-slate max-w-none">

        <ReactMarkdown
          components={{
            h2: ({children}) => (
              <h2 className="text-xl font-bold mt-8 mb-4 border-b pb-2">
                {children}
              </h2>
            ),
            h3: ({children}) => (
              <h3 className="text-base font-semibold mt-6 mb-2 text-slate-700">
                {children}
              </h3>
            ),
            ul: ({children}) => (
              <ul className="list-disc pl-6 space-y-1">
                {children}
              </ul>
            ),
            p: ({children}) => (
              <p className="text-sm leading-relaxed text-slate-700">
                {children}
              </p>
            ),
          }}
        >
          {recommendation}
        </ReactMarkdown>

      </div>

    </div>
  );
}
