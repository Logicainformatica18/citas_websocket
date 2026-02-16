import CompetencyCard from "../Cards/CompetencyCard";

interface Props {
  competencies: any[];
  onAnalyze: (c: { id: number; name: string }) => void;
}

export default function CompetencyBoard({
  competencies,
  onAnalyze,
}: Props) {
  const aligned = competencies.filter((c) => c.status === "aligned");
  const partial = competencies.filter((c) => c.status === "partial");
  const gap = competencies.filter((c) => c.status === "gap");

  const Section = ({
    title,
    items,
    color,
  }: {
    title: string;
    items: any[];
    color: string;
  }) => {
    if (items.length === 0) return null;

    return (
      <div className="space-y-4">
        <div className="flex items-center gap-2">
          <div className={`w-3 h-3 rounded-full ${color}`} />
          <h3 className="text-lg font-semibold">
            {title} ({items.length})
          </h3>
        </div>

        <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-6">
          {items.map((c) => (
            <CompetencyCard
              key={c.id}
              competency={c}
              onAnalyze={onAnalyze}
            />
          ))}
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-10">
      <Section
        title="Competencias Alineadas"
        items={aligned}
        color="bg-emerald-500"
      />

      <Section
        title="Competencias Parciales"
        items={partial}
        color="bg-amber-500"
      />

      <Section
        title="Competencias con Brecha"
        items={gap}
        color="bg-red-500"
      />
    </div>
  );
}
