interface Row {
    city: string;
    region: string;
    country: string;
    total_jobs: number;
    percentage: number;
}

export default function JobDemandCityTable({ data }: { data: Row[] }) {
    return (
        <div className="bg-white dark:bg-[#0F2A3A] border rounded-xl p-4">
            <h3 className="font-semibold mb-3 text-slate-900 dark:text-slate-100">
                Ranking de ciudades por demanda
            </h3>

            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-slate-500">
                        <th>Ciudad</th>
                        <th>Región</th>
                        <th>País</th>
                        <th className="text-right">Ofertas</th>
                        <th className="text-right">%</th>
                    </tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr
                            key={i}
                            className="border-t text-slate-700 dark:text-slate-300"
                        >
                            <td className="py-2">{row.city}</td>
                            <td>{row.region}</td>
                            <td>{row.country}</td>
                            <td className="text-right font-medium">
                                {row.total_jobs}
                            </td>
                            <td className="text-right">
                                {row.percentage}%
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
