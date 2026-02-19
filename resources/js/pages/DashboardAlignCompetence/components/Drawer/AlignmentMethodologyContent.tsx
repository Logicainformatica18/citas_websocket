import { BarChart3, Briefcase, Sparkles } from "lucide-react";

export default function AlignmentMethodologyContent() {
  return (
    <div className="space-y-8 text-sm text-slate-700">

      {/* INTRO */}
      <section className="space-y-3">
        <h3 className="text-base font-semibold flex items-center gap-2">
          <BarChart3 size={18} />
          ¿Qué evalúa este indicador?
        </h3>

        <p>
          Este indicador mide el nivel de alineación estratégica de cada
          competencia dentro de una carrera, considerando:
        </p>

        <ul className="list-disc pl-6 space-y-1">
          <li>Demanda laboral real (vacantes cruzadas)</li>
          <li>Tendencias estratégicas (reportes prospectivos)</li>
        </ul>

        <div className="bg-slate-100 p-4 rounded-xl font-medium">
          Resultado Final =
          (Peso Mercado × Score Mercado) +
          (Peso Tendencia × Score Tendencia)
        </div>
      </section>

      {/* MODELO ESTRUCTURAL */}
      <section className="space-y-3">
        <h3 className="text-base font-semibold">
          ¿Cómo se construye el cálculo?
        </h3>

        <p>
          La evaluación sigue esta estructura:
        </p>

        <div className="bg-slate-50 border p-4 rounded-xl space-y-2">
          <p><strong>Carrera</strong></p>
          <p>→ Competencias</p>
          <p>→ Cursos asociados</p>
          <p>→ Lenguajes / Tecnologías / Metodologías</p>
          <p>→ Vacantes laborales + Tendencias</p>
        </div>

        <p>
          Las entidades técnicas de cada curso se cruzan con empleo real y
          reportes estratégicos para medir su impacto.
        </p>
      </section>

      {/* EJEMPLO */}
      <section className="space-y-3">
        <h3 className="text-base font-semibold">
          Ejemplo práctico
        </h3>

        <div className="bg-slate-100 p-4 rounded-xl space-y-2">
          <p>
            Carrera: <strong>Ingeniería de Sistemas</strong>
          </p>

          <p>
            Competencia: <strong>Configurar redes y servicios</strong>
          </p>

          <p>Cursos asociados:</p>

          <ul className="list-disc pl-6 space-y-1">
            <li>CCNA Security</li>
            <li>Servidores y Almacenamiento</li>
            <li>Proyecto Tecnológico</li>
          </ul>

          <p>
            Cada curso contiene tecnologías y metodologías que generan
            vacantes y tendencias.
          </p>

          <p>
            Si algunos cursos concentran mayor volumen de empleo que otros,
            la competencia se evaluará de forma comparativa frente a las
            demás competencias de la carrera.
          </p>
        </div>

        <div className="bg-blue-50 border border-blue-200 p-4 rounded-xl text-xs">
          🔎 El modelo es comparativo dentro de la carrera.
          No mide solo si existe empleo, sino qué tan fuerte es una
          competencia frente a las demás.
        </div>
      </section>

      {/* CLASIFICACIÓN */}
      <section className="space-y-3">
        <h3 className="text-base font-semibold flex items-center gap-2">
          <Briefcase size={18} />
          Clasificación final
        </h3>

        <ul className="space-y-2">
          <li><strong>Fuerte</strong> → ≥ 80%</li>
          <li><strong>Media</strong> → 60% – 79%</li>
          <li><strong>Débil</strong> → 40% – 59%</li>
          <li><strong>Baja</strong> → 1% – 39%</li>
          <li><strong>Crítica</strong> → 0%</li>
        </ul>

        <div className="bg-emerald-50 border border-emerald-200 p-4 rounded-xl text-xs">
          “Crítica” solo se asigna cuando no existen señales ni de
          mercado ni de tendencias.
        </div>
      </section>

    </div>
  );
}
