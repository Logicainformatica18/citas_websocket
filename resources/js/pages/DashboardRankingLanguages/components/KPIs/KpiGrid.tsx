import KpiCard from "./KpiCard";

type KpisProps = {
  top_language?: {
    name: string;
    score?: number;
  };
  vacantes_totales?: number;
  lenguajes_analizados?: number;
};

export default function KpiGrid({ items }: { items: KpisProps }) {
  if (!items) return null;

  const kpiCards = [
    items.top_language && {
      title: "Lenguaje más demandado",
      value: items.top_language.name,
      score: items.top_language.score,
    },
    {
      title: "Vacantes analizadas",
      value: items.vacantes_totales ?? 0,
    },
    {
      title: "Lenguajes analizados",
      value: items.lenguajes_analizados ?? 0,
    },
  ].filter(Boolean);

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      {kpiCards.map((kpi, i) => (
        <KpiCard key={i} {...kpi} />
      ))}
    </div>
  );
}
