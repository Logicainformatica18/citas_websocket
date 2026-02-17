import { useEffect, useState } from "react";
import { Bot, Sparkles, Target, Award, Layers } from "lucide-react";

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
  }, [course.id]);

  if (loading) {
    return <p className="text-sm text-muted-foreground">Cargando análisis IA...</p>;
  }

  if (!data || !data.diagnosis) {
    return (
      <div className="bg-muted/40 rounded-xl p-4">
        <p className="text-sm text-muted-foreground">
          No existe recomendación IA generada para este curso.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">

      {/* DIAGNÓSTICO */}
      <div className="bg-muted/40 rounded-xl p-4">
        <div className="flex items-center gap-2 mb-2">
          <Bot size={16} className="text-[#1CBCE8]" />
          <h3 className="font-semibold text-sm">
            Diagnóstico estratégico
          </h3>
        </div>

        <p className="text-sm text-muted-foreground leading-relaxed">
          {data.diagnosis}
        </p>
      </div>

      {/* ENTIDADES SUGERIDAS */}
      {data.suggested_entities?.length > 0 && (
        <Section
          icon={<Target size={14} />}
          title="Tecnologías Sugeridas"
          items={data.suggested_entities}
        />
      )}

      {/* CERTIFICACIONES */}
      {data.suggested_certifications?.length > 0 && (
        <Section
          icon={<Award size={14} />}
          title="Certificaciones recomendadas"
          items={data.suggested_certifications}
        />
      )}

      {/* METODOLOGÍAS */}
      {data.suggested_methodologies?.length > 0 && (
        <Section
          icon={<Layers size={14} />}
          title="Metodologías sugeridas"
          items={data.suggested_methodologies}
        />
      )}
    </div>
  );
}

function Section({ icon, title, items }: any) {
  return (
    <div>
      <div className="flex items-center gap-2 text-sm font-semibold mb-3">
        {icon}
        {title}
      </div>

      <div className="flex flex-wrap gap-2">
        {items.map((item: string) => (
          <span
            key={item}
            className="bg-[#E6F7FD] text-[#1CBCE8] px-3 py-1 rounded-full text-[11px]"
          >
            {item}
          </span>
        ))}
      </div>
    </div>
  );
}
