import KpiCard from "./KpiCard";

export default function KpiGrid({ items }: { items: any[] }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
      {items.map((kpi, i) => (
        <KpiCard key={i} {...kpi} />
      ))}
    </div>
  );
}
