import { router, usePage } from "@inertiajs/react";

interface Row {
    city: string;
    region: string;
    country: string;
    total_jobs: number;
    percentage: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

export default function JobDemandCityTable({
    ranking,
}: {
    ranking: PaginatedData<Row>;
}) {
    const pageProps = usePage().props as any;
const filters = pageProps?.filters ?? {};

    const goTo = (url: string | null) => {
        if (!url) return;

        router.get(
            url,
            { ...filters },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            }
        );
    };

    return (
        <div className="bg-white dark:bg-[#0F2A3A] border rounded-xl p-4 flex flex-col gap-3">
            <h3 className="font-semibold text-slate-900 dark:text-slate-100">
                Ranking de ciudades por demanda
            </h3>

            {/* TABLA */}
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
                    {ranking.data.map((row, i) => (
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

            {/* PAGINACIÓN */}
            <div className="flex items-center justify-between pt-3 border-t text-xs text-slate-500 dark:text-slate-400">
                <span>
                    Mostrando{" "}
                    <strong>
                        {(ranking.current_page - 1) * ranking.per_page + 1}
                    </strong>{" "}
                    –{" "}
                    <strong>
                        {Math.min(
                            ranking.current_page * ranking.per_page,
                            ranking.total
                        )}
                    </strong>{" "}
                    de <strong>{ranking.total}</strong>
                </span>

                <div className="flex gap-1">
                    {ranking.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => goTo(link.url)}
                            className={`
                                px-2 py-1 rounded-md border text-xs transition
                                ${
                                    link.active
                                        ? "bg-[#00B6E8] text-white border-[#00B6E8]"
                                        : "bg-white dark:bg-[#0F2A3A] border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-[#14344A]"
                                }
                                ${
                                    !link.url
                                        ? "opacity-40 cursor-not-allowed"
                                        : ""
                                }
                            `}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
