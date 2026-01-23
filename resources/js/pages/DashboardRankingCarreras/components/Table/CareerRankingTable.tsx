export function CareerRankingTable({ rows }: any) {
  return (
    <div className="rounded-xl border overflow-hidden">
      <table className="min-w-full text-sm">
        <thead className="bg-[#1CBCE8]/10 text-[#0C647A]">
          <tr>
            <th className="px-4 py-2 text-left">Carrera</th>
            <th className="px-4 py-2 text-right">Vacantes</th>
          </tr>
        </thead>

        <tbody>
          {rows.map((r: any) => (
            <tr key={r.id} className="border-t hover:bg-[#ECFAFD] dark:hover:bg-[#1CBCE8]/10">
              <td className="px-4 py-2 font-medium">{r.name}</td>
              <td className="px-4 py-2 text-right font-semibold">
                {r.total_jobs.toLocaleString()}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
