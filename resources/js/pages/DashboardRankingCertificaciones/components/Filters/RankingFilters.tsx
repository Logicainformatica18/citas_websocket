import { router, usePage } from "@inertiajs/react";

export default function RankingFilters() {
  const { filters } = usePage().props as any;

  const onChange = (params: any) => {
    router.get(
      "/dashboard/ranking-certificaciones",
      {
        category: params.category ?? filters?.category,
        career: params.career ?? filters?.career,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  return (
    <div className="flex gap-4 items-center">
      {/* =========================
          Área tecnológica
      ========================= */}
      <select
        value={filters?.category ?? ""}
        onChange={(e) => onChange({ category: e.target.value })}
        className="border rounded-lg px-4 py-2"
      >
        <option value="">Área tecnológica</option>
        <option value="cloud">Cloud Computing</option>
        <option value="ai">Inteligencia Artificial</option>
        <option value="data">Data & Analytics</option>
        <option value="security">Ciberseguridad</option>
        <option value="networking">Redes</option>
      </select>

      {/* =========================
          Carrera ISIL
      ========================= */}
      <select
        value={filters?.career ?? ""}
        onChange={(e) => onChange({ career: e.target.value })}
        className="border rounded-lg px-4 py-2"
      >
        <option value="">Carrera ISIL</option>

        <option value="architecture">Arquitectura de Datos</option>
        <option value="cyber">Ciberseguridad</option>
        <option value="data_ai">Ciencia de Datos e IA</option>
        <option value="cloud">Computación en la Nube</option>
        <option value="software">Desarrollo de Software</option>
        <option value="networks">Redes y Comunicaciones</option>
        <option value="information_systems">Sistemas de Información</option>
        <option value="systems_engineering">Ingeniería de Sistemas</option>
        <option value="it">Tecnologías de Información</option>
      </select>
    </div>
  );
}
