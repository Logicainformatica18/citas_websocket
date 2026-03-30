import { useEffect, useState, useRef } from "react";
import {
  Bot,
  Target,
  Award,
  Layers,
  AlertTriangle,
} from "lucide-react";
import { useReactToPrint } from "react-to-print";

export default function CourseAITab({ course }: any) {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  const pdfRef = useRef<HTMLDivElement>(null);

  const handleDownloadPDF = useReactToPrint({
    contentRef: pdfRef,
    documentTitle: `diagnostico-${course?.name || "curso"}`,
  });

  useEffect(() => {
    if (!course?.id) return;

    setLoading(true);

    fetch(`/indicators/course/${course.id}/ai`)
      .then((res) => res.json())
      .then((json) => setData(json))
      .finally(() => setLoading(false));
  }, [course?.id]);

  if (loading) return <p className="text-sm text-gray-500">Cargando IA...</p>;

  if (!data?.diagnosis) {
    return (
      <div className="rounded-xl p-6 border bg-gray-50">
        <p className="text-sm text-gray-500">
          No existe análisis IA.
        </p>
      </div>
    );
  }

  const d =
    typeof data.diagnosis === "string"
      ? JSON.parse(data.diagnosis)
      : data.diagnosis;

  return (
    <div className="space-y-6">

      {/* BOTÓN */}
      <div className="flex justify-end">
        <button
          onClick={handleDownloadPDF}
          className="px-4 py-2 rounded-lg bg-sky-600 text-white text-sm hover:bg-sky-700"
        >
          Descargar PDF
        </button>
      </div>

      <div ref={pdfRef} className="space-y-6">

        {/* HEADER */}
        <div className="p-5 rounded-2xl border bg-white shadow-sm">
          <h2 className="text-base font-semibold mb-3">{course.name}</h2>

          <div className="flex flex-wrap gap-2">
            <Badge color="sky" label="Alineación" value={d.strategic?.alignment_level} />
            <Badge color="purple" label="Tipo" value={d.strategic?.course_type} />
            <Badge color="indigo" label="Rol" value={d.strategic?.curricular_role} />
            <Badge color="amber" label="Obsolescencia" value={d.obsolescence?.level} />
          </div>
        </div>

        {/* BRECHAS */}
        <SectionBlock title="Brechas críticas">
          <div className="grid md:grid-cols-4 gap-3">
            {Object.entries(d.gaps || {}).map(([k, v]: any) => (
              <MiniCard key={k} title={k}>
                {v}
              </MiniCard>
            ))}
          </div>
        </SectionBlock>

        {/* TECNOLOGÍA */}
        <SectionBlock title="Evaluación tecnológica">
          <div className="space-y-2 text-sm">
            <p><b>Relevancia:</b> {d.technology_evaluation?.relevance}</p>
            <p><b>Pros / Contras:</b> {d.technology_evaluation?.pros_cons}</p>
            <p><b>Uso en mercado:</b> {d.technology_evaluation?.market_usage}</p>
          </div>
        </SectionBlock>

        {/* RECOMENDACIÓN */}
        <SectionBlock title="Recomendación estratégica">
          <div className="grid md:grid-cols-3 gap-3">
            <MiniCard title="Qué">{d.recommendations?.what_to_change}</MiniCard>
            <MiniCard title="Dónde">{d.recommendations?.where_to_change}</MiniCard>
            <MiniCard title="Cómo">{d.recommendations?.how_to_change}</MiniCard>
          </div>
        </SectionBlock>

        {/* SESIONES */}
        {d.sessions_analysis?.length > 0 && (
          <SectionBlock title="Análisis por sesiones">
            <div className="overflow-x-auto">
              <table className="w-full text-xs border">
                <thead className="bg-gray-50 text-gray-600">
                  <tr>
                    <th className="p-2">Sesión</th>
                    <th className="p-2">Problema</th>
                    <th className="p-2">Brecha</th>
                    <th className="p-2">Recomendación</th>
                    <th className="p-2">Prioridad</th>
                  </tr>
                </thead>
                <tbody>
                  {d.sessions_analysis.map((s: any, i: number) => (
                    <tr key={i} className="border-t">
                      <td className="p-2">{s.session}</td>
                      <td className="p-2">{s.issue}</td>
                      <td className="p-2">{s.gap}</td>
                      <td className="p-2">{s.recommendation}</td>
                      <td className="p-2">
                        <PriorityTag value={s.priority} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </SectionBlock>
        )}

        {/* IMPACTO */}
        <SectionBlock title="Impacto esperado">
          <div className="grid md:grid-cols-3 gap-3">
            <MiniCard title="Empleabilidad">{d.impact?.employability}</MiniCard>
            <MiniCard title="Alineación">{d.impact?.alignment_improvement}</MiniCard>
            <MiniCard title="Valor">{d.impact?.value_perception}</MiniCard>
          </div>
        </SectionBlock>

        {/* RIESGO */}
        <div className="p-4 rounded-xl bg-red-50 border border-red-200 flex gap-2 text-sm">
          <AlertTriangle className="text-red-500" size={16} />
          {d.risk}
        </div>

        {/* APLICABILIDAD */}
        <SectionBlock title="Aplicabilidad real">
          <div className="grid md:grid-cols-2 gap-3">
            <MiniCard title="Roles">
              {d.applicability?.roles?.join(", ")}
            </MiniCard>
            <MiniCard title="Tareas">
              {d.applicability?.real_tasks?.join(", ")}
            </MiniCard>
          </div>
        </SectionBlock>

        {/* TAGS */}
        <TagSection title="Tecnologías sugeridas" items={data.suggested_entities} />
        <TagSection title="Certificaciones" items={data.suggested_certifications} />
        <TagSection title="Metodologías" items={data.suggested_methodologies} />

      </div>
    </div>
  );
}

/* ================= UI COMPONENTS ================= */

function Badge({ label, value, color }: any) {
  const map: any = {
    sky: "bg-sky-100 text-sky-700",
    purple: "bg-purple-100 text-purple-700",
    indigo: "bg-indigo-100 text-indigo-700",
    amber: "bg-amber-100 text-amber-700",
  };

  return (
    <span className={`px-3 py-1 text-xs rounded-full font-medium ${map[color]}`}>
      {label}: {value}
    </span>
  );
}

function MiniCard({ title, children }: any) {
  return (
    <div className="p-3 rounded-xl border bg-white text-sm">
      <p className="text-[11px] font-semibold text-gray-500 mb-1">{title}</p>
      <p className="text-gray-700 leading-snug">{children}</p>
    </div>
  );
}

function SectionBlock({ title, children }: any) {
  return (
    <div>
      <h3 className="text-sm font-semibold mb-3 text-gray-700">{title}</h3>
      {children}
    </div>
  );
}

function TagSection({ title, items }: any) {
  if (!items?.length) return null;

  return (
    <div>
      <h3 className="text-sm font-semibold mb-2">{title}</h3>
      <div className="flex flex-wrap gap-2">
        {items.map((i: string) => (
          <span
            key={i}
            className="px-3 py-1 rounded-full text-xs bg-sky-100 text-sky-700 border border-sky-200"
          >
            {i}
          </span>
        ))}
      </div>
    </div>
  );
}

function PriorityTag({ value }: any) {
  const map: any = {
    Alta: "bg-red-100 text-red-600",
    Media: "bg-amber-100 text-amber-600",
    Baja: "bg-green-100 text-green-600",
  };

  return (
    <span className={`px-2 py-1 rounded text-[10px] font-semibold ${map[value]}`}>
      {value}
    </span>
  );
}