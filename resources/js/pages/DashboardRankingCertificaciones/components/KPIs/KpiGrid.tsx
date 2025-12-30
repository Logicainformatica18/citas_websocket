import KpiCard from "./KpiCard";

type KpisProps = {
  top_certification?: {
    name: string;
    vendor: string;
    score?: number;
  };
  alta_demanda?: number;
  alta_proyeccion?: number;
  area_destacada?: string;
};

export default function KpiGrid({ items }: { items: KpisProps }) {
  if (!items) return null;

  const kpiCards = [
    items.top_certification && {
      title: "Certificación Top",
      value: items.top_certification.name,
      subtitle: items.top_certification.vendor,
      score: items.top_certification.score,
    },
    {
      title: "Alta Demanda",
      value: items.alta_demanda ?? 0,
    },
    {
      title: "Alta Proyección",
      value: items.alta_proyeccion ?? 0,
    },
    {
      title: "Área Destacada",
      value: items.area_destacada ?? "-",
    },
  ].filter(Boolean);

  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
      {kpiCards.map((kpi, i) => (
        <KpiCard key={i} {...kpi} />
      ))}
    </div>
  );
}
