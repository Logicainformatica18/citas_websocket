import {
  Building2,
  Crown,
  Briefcase,
  PieChart,
} from "lucide-react";
import CompanyKpiCard from "./CompanyKpiCard";

interface Props {
  meta: {
    empresas_activas: number;
    vacantes_analizadas: number;

    empresa_lider: string | null;
    empresa_lider_vacantes: number;
    concentracion_top_3: number;
  };
}

export default function CompanyKpiGrid({ meta }: Props) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

      {/* Empresas activas */}
      <CompanyKpiCard
        title="Empresas"
        value={meta.empresas_activas}
        subtitle="Con al menos una vacante"
        icon={<Building2 className="h-4 w-4" />}
      />

      {/* Empresa líder */}
      <CompanyKpiCard
        title="Empresa líder"
        value={meta.empresa_lider}
        subtitle={
          meta.empresa_lider
            ? `${meta.empresa_lider_vacantes.toLocaleString()} vacantes`
            : "Sin datos"
        }
        icon={<Crown className="h-4 w-4" />}
        highlight
      />

      {/* Vacantes empresa líder */}
      <CompanyKpiCard
        title="Vacantes líder"
        value={meta.empresa_lider_vacantes}
        subtitle="Publicadas por la empresa líder"
        icon={<Briefcase className="h-4 w-4" />}
      />

      {/* Concentración */}
      <CompanyKpiCard
        title="Concentración Top 3"
        value={`${meta.concentracion_top_3}%`}
        subtitle="Del total de vacantes"
        icon={<PieChart className="h-4 w-4" />}
      />
    </div>
  );
}
