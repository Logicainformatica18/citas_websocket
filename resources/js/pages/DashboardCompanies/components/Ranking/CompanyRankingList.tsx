export default function CompanyRankingList({ ranking }: any) {
  return (
    <div className="rounded-xl border overflow-hidden">
      <table className="w-full text-sm">
        <thead className="bg-muted">
          <tr>
            <th className="p-3 text-left">#</th>
            <th className="p-3 text-left">Empresa</th>
            <th className="p-3 text-right">Vacantes</th>
          </tr>
        </thead>
        <tbody>
          {ranking.map((row: any, i: number) => (
            <tr key={row.company} className="border-t">
              <td className="p-3">{i + 1}</td>
              <td className="p-3 font-medium">{row.company}</td>
              <td className="p-3 text-right font-semibold">
                {row.total_vacancies}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
