interface Props {
  summary: any;
}

export default function CourseAlignmentKpis({ summary }: Props) {
  return (
    <div className="grid grid-cols-4 gap-6">

      <KpiCard
        title="Estratégicamente alineados"
        value={summary.strategic}
        color="bg-green-100"
      />

      <KpiCard
        title="Altamente alineados"
        value={summary.high}
        color="bg-blue-100"
      />

      <KpiCard
        title="Alineados"
        value={summary.basic}
        color="bg-yellow-100"
      />

      <KpiCard
        title="No alineados"
        value={summary.gap}
        color="bg-red-100"
      />

    </div>
  );
}

function KpiCard({ title, value, color }: any) {
  return (
    <div className={`p-4 rounded-xl ${color}`}>
      <div className="text-sm text-muted-foreground">{title}</div>
      <div className="text-2xl font-bold">{value}</div>
    </div>
  );
}
