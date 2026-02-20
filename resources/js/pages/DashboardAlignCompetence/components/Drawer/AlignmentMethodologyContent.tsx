import { BarChart3, Briefcase, Layers } from "lucide-react";

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
          competencia del Perfil de Egreso, utilizando evidencia real de:
        </p>

        <ul className="list-disc pl-6 space-y-1">
          <li>📊 Demanda laboral (vacantes cruzadas)</li>
          <li>📈 Tendencias estratégicas (reportes prospectivos)</li>
        </ul>

        <div className="bg-slate-100 p-4 rounded-xl font-medium">
          Resultado Final =
          (Peso Mercado × Score Mercado) +
          (Peso Tendencia × Score Tendencia)
        </div>
      </section>

      {/* MODELO ESTRUCTURAL */}
      <section className="space-y-3">
        <h3 className="text-base font-semibold flex items-center gap-2">
          <Layers size={18} />
          ¿Cómo se construye el cálculo?
        </h3>

        <div className="bg-slate-50 border p-4 rounded-xl space-y-2">
          <p><strong>Carrera</strong></p>
          <p>→ Competencias</p>
          <p>→ Cursos asociados</p>
          <p>→ Lenguajes / Tecnologías / Metodologías</p>
          <p>→ Vacantes laborales + Tendencias</p>
        </div>

        <p>
          Las entidades técnicas de cada curso generan señales de empleo y
          tendencias que se heredan a nivel de competencia.
        </p>

        <p>
          Luego, todas las competencias se ordenan según su impacto
          y se clasifican comparativamente mediante cuartiles (NTILE 4).
        </p>
      </section>

      {/* MODELO POR CUARTILES */}
      <section className="space-y-3">
        <h3 className="text-base font-semibold">
          Modelo comparativo por cuartiles
        </h3>

        <div className="bg-blue-50 border border-blue-200 p-4 rounded-xl text-xs space-y-2">
          <p>
            Las competencias no se evalúan por umbrales fijos.
          </p>
          <p>
            Se ordenan de mayor a menor impacto y se dividen en 4 grupos:
          </p>
          <ul className="list-disc pl-5">
            <li><strong>Q1</strong> → 25% superior (máximo impacto)</li>
            <li><strong>Q2</strong> → Alto impacto</li>
            <li><strong>Q3</strong> → Impacto medio</li>
            <li><strong>Q4</strong> → Impacto bajo</li>
          </ul>
        </div>

        <p>Cada cuartil se transforma en puntaje:</p>

        <ul className="list-disc pl-6">
          <li>Q1 → 1.00</li>
          <li>Q2 → 0.75</li>
          <li>Q3 → 0.50</li>
          <li>Q4 → 0.25</li>
        </ul>

        <p>
          El puntaje final combina mercado y tendencias según los pesos
          institucionales definidos.
        </p>
      </section>

      {/* CLASIFICACIÓN */}
      <section className="space-y-3">
        <h3 className="text-base font-semibold flex items-center gap-2">
          <Briefcase size={18} />
          Clasificación estratégica final
        </h3>

        <ul className="space-y-2">
          <li><strong>Fuerte</strong> → ≥ 80%</li>
          <li><strong>Media</strong> → 60% – 79%</li>
          <li><strong>Débil</strong> → 40% – 59%</li>
          <li><strong>Baja</strong> → 1% – 39%</li>
          <li><strong>Crítica</strong> → 0% (sin señales de mercado ni tendencia)</li>
        </ul>

        <div className="bg-emerald-50 border border-emerald-200 p-4 rounded-xl text-xs">
          🔎 “Crítica” solo se asigna cuando no existen señales
          ni de mercado laboral ni de tendencias estratégicas.
          No depende del porcentaje comparativo.
        </div>
      </section>

      {/* INTERPRETACIÓN */}
      <section className="space-y-3">
        <h3 className="text-base font-semibold">
          ¿Cómo interpretar los resultados?
        </h3>

        <div className="bg-slate-100 p-4 rounded-xl text-xs space-y-2">
          <p>
            El modelo es comparativo.
            Una competencia puede tener empleo real,
            pero si otras concentran mayor volumen,
            su posición estratégica disminuye.
          </p>

          <p>
            El indicador no mide solo existencia de empleo,
            sino fuerza relativa dentro del ecosistema analizado.
          </p>
        </div>
      </section>

    </div>
  );
}