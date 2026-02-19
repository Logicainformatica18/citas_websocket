import {
  Sparkles,
  ShieldCheck,
  TrendingUp,
  Rocket,
  Database,
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

  // 🔥 Dividir por secciones A) B) C) D)
  const sections = recommendation.split(/\n(?=[A-D]\))/g);

  const getIcon = (title: string) => {
    if (title.includes("Diagnóstico")) return <ShieldCheck size={18} className="text-green-600" />;
    if (title.includes("Brechas")) return <TrendingUp size={18} className="text-amber-600" />;
    if (title.includes("Recomendaciones")) return <Rocket size={18} className="text-indigo-600" />;
    if (title.includes("Ajustes")) return <Database size={18} className="text-blue-600" />;
    return <Sparkles size={18} />;
  };

  return (
    <div className="space-y-8">

      {/* META */}
      <div className="bg-slate-50 border rounded-xl p-4 text-xs text-slate-600">
        <div><strong>Año analizado:</strong> {year}</div>
        <div><strong>Generado el:</strong> {generatedAt}</div>
      </div>

      {sections.map((section, index) => {

        const lines = section.split("\n").filter(Boolean);
        const title = lines[0];

        return (
          <div key={index} className="space-y-4">

            {/* TÍTULO */}
            <div className="flex items-center gap-2">
              {getIcon(title)}
              <h3 className="font-semibold text-slate-800">
                {title}
              </h3>
            </div>

            {/* CONTENIDO */}
            <div className="space-y-2 text-sm text-slate-700">

              {lines.slice(1).map((line, i) => {

                // Bullet
                if (line.trim().startsWith("-")) {
                  return (
                    <div key={i} className="pl-4 border-l-2 border-slate-200">
                      {formatBold(line.replace(/^-/, "").trim())}
                    </div>
                  );
                }

                // Numeración
                if (/^\d+\./.test(line.trim())) {
                  return (
                    <div key={i} className="font-medium">
                      {formatBold(line.trim())}
                    </div>
                  );
                }

                return (
                  <p key={i}>
                    {formatBold(line)}
                  </p>
                );
              })}

            </div>

          </div>
        );
      })}

    </div>
  );
}

/* 🔥 Convierte **texto** en negrita real */
function formatBold(text: string) {
  const parts = text.split(/(\*\*.*?\*\*)/g);

  return parts.map((part, i) => {
    if (part.startsWith("**") && part.endsWith("**")) {
      return (
        <strong key={i}>
          {part.replace(/\*\*/g, "")}
        </strong>
      );
    }
    return <span key={i}>{part}</span>;
  });
}
