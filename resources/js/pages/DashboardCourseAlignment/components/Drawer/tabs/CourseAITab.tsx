import { useEffect, useState } from "react";
import { Bot, Target, Award, Layers } from "lucide-react";

export default function CourseAITab({ course }: any) {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!course?.id) return;

    setLoading(true);

    fetch(`/indicators/course/${course.id}/ai`)
      .then((res) => res.json())
      .then((json) => setData(json))
      .finally(() => setLoading(false));
  }, [course?.id]);

  if (loading) {
    return (
      <p className="text-sm text-gray-500 dark:text-gray-400">
        Cargando análisis IA...
      </p>
    );
  }

  if (!data || !data.diagnosis) {
    return (
      <div className="rounded-2xl p-6 border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-slate-900/50">
        <p className="text-sm text-gray-500 dark:text-gray-400">
          No existe recomendación IA generada para este curso.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-8">

      {/* ================= DIAGNÓSTICO ================= */}
      <div
        className="
          relative overflow-hidden rounded-2xl p-6
          border border-sky-200 dark:border-sky-800
          bg-white
          dark:bg-gradient-to-br dark:from-slate-800 dark:to-slate-900
          shadow-sm dark:shadow-xl
          transition
        "
      >
        <div className="flex items-center gap-3 mb-4">
          <div className="p-2 rounded-lg bg-sky-100 dark:bg-sky-900/40">
            <Bot size={18} className="text-sky-600 dark:text-sky-400" />
          </div>

          <div>
            <h3 className="font-semibold text-sm text-gray-800 dark:text-white">
              Diagnóstico estratégico
            </h3>
            <p className="text-xs text-gray-400 dark:text-gray-500">
              Generado por VERA IA
            </p>
          </div>
        </div>

        <p className="text-sm leading-relaxed text-gray-600 dark:text-gray-300">
          {data.diagnosis}
        </p>
      </div>

      {/* ================= TECNOLOGÍAS ================= */}
      {data.suggested_entities?.length > 0 && (
        <Section
          icon={<Target size={15} />}
          title="Tecnologías sugeridas"
          items={data.suggested_entities}
        />
      )}

      {/* ================= CERTIFICACIONES ================= */}
      {data.suggested_certifications?.length > 0 && (
        <Section
          icon={<Award size={15} />}
          title="Certificaciones recomendadas"
          items={data.suggested_certifications}
        />
      )}

      {/* ================= METODOLOGÍAS ================= */}
      {data.suggested_methodologies?.length > 0 && (
        <Section
          icon={<Layers size={15} />}
          title="Metodologías sugeridas"
          items={data.suggested_methodologies}
        />
      )}
    </div>
  );
}

/* ======================================================
   SECTION COMPONENT
====================================================== */
function Section({ icon, title, items }: any) {
  return (
    <div>
      <div className="flex items-center gap-2 text-sm font-semibold mb-4 text-gray-700 dark:text-gray-200">
        <span className="text-sky-600 dark:text-sky-400">
          {icon}
        </span>
        {title}
      </div>

      <div className="flex flex-wrap gap-3">
        {items.map((item: string) => (
          <span
            key={item}
            className="
              px-3 py-1.5 rounded-full text-[11px] font-medium
              border
              bg-sky-50 text-sky-700 border-sky-200
              dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-700
              hover:scale-105 hover:shadow-sm
              transition-all
            "
          >
            {item}
          </span>
        ))}
      </div>
    </div>
  );
}