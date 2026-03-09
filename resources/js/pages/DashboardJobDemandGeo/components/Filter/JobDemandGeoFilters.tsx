import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import { Globe, MapPin, GraduationCap, X } from "lucide-react";

interface Props {
    regions: string[];
    careers: { id: number; name: string }[];
    filters: any;
}

export default function JobDemandGeoFilters({
    regions,
    careers,
    filters,
}: Props) {
    const [region, setRegion] = useState(filters.region ?? "");
    const [careerId, setCareerId] = useState(filters.career_id ?? "");
    const [query, setQuery] = useState(filters.country ?? "");
    const [results, setResults] = useState<string[]>([]);
    const [open, setOpen] = useState(false);

    const timeout = useRef<any>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    /* ================= SEARCH COUNTRIES ================= */

    const searchCountries = (q: string) => {
        fetch(
            `/dashboard/indicators/job-demand-geo/search-countries?q=${q}&region=${region}`
        )
            .then((r) => r.json())
            .then((data) => {
                setResults(data);
                setOpen(true);
            });
    };

    /* ================= TYPE SEARCH ================= */

    useEffect(() => {
        if (!region) return;

        clearTimeout(timeout.current);

        timeout.current = setTimeout(() => {
            searchCountries(query);
        }, 300);
    }, [query]);

    /* ================= REGION CHANGE ================= */

    useEffect(() => {
        setQuery("");
        setResults([]);
        setOpen(false);

        if (region) {
            searchCountries("");
        }
    }, [region]);

    /* ================= CLICK OUTSIDE ================= */

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(e.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener("mousedown", handler);
        return () => document.removeEventListener("mousedown", handler);
    }, []);

    /* ================= APPLY FILTER ================= */

    const applyFilters = (params: any) => {
        router.get(
            "/dashboard/indicators/job-demand-geo",
            {
                ...filters,
                ...params,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    return (
        <div className="mt-6 flex flex-wrap gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-[#0F2A3A]">

            {/* ================= CARRERA ================= */}

            <div className="flex flex-col gap-1 min-w-[220px]">
                <span className="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Carrera
                </span>

                <div className="relative">

                    <GraduationCap className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#00B6E8]" />

                    <select
                        value={careerId}
                        onChange={(e) => {
                            const v = e.target.value;
                            setCareerId(v);
                            applyFilters({ career_id: v || null });
                        }}
                        className="
                        w-full rounded-xl border border-slate-200
                        bg-white px-9 py-2 text-sm font-semibold
                        text-slate-700
                        focus:outline-none focus:ring-2 focus:ring-[#00B6E8]
                        dark:border-slate-700 dark:bg-[#0B2233] dark:text-white
                        "
                    >
                        <option value="">Todas</option>

                        {careers.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>

                    {careerId && (
                        <button
                            onClick={() => {
                                setCareerId("");
                                applyFilters({ career_id: null });
                            }}
                            className="absolute right-2 top-1/2 -translate-y-1/2"
                        >
                            <X className="h-4 w-4 text-slate-400 hover:text-slate-600" />
                        </button>
                    )}
                </div>
            </div>

            {/* ================= REGION ================= */}

            <div className="flex flex-col gap-1 min-w-[200px]">
                <span className="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Región
                </span>

                <div className="relative">

                    <Globe className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#00B6E8]" />

                    <select
                        value={region}
                        onChange={(e) => {
                            const v = e.target.value;
                            setRegion(v);
                            applyFilters({
                                region: v || null,
                                country: null,
                            });
                        }}
                        className="
                        w-full rounded-xl border border-slate-200
                        bg-white px-9 py-2 text-sm font-semibold
                        text-slate-700
                        focus:outline-none focus:ring-2 focus:ring-[#00B6E8]
                        dark:border-slate-700 dark:bg-[#0B2233] dark:text-white
                        "
                    >
                        <option value="">Todas</option>

                        {regions.map((r) => (
                            <option key={r} value={r}>
                                {r}
                            </option>
                        ))}
                    </select>

                    {region && (
                        <button
                            onClick={() => {
                                setRegion("");
                                setQuery("");
                                applyFilters({
                                    region: null,
                                    country: null,
                                });
                            }}
                            className="absolute right-2 top-1/2 -translate-y-1/2"
                        >
                            <X className="h-4 w-4 text-slate-400 hover:text-slate-600" />
                        </button>
                    )}
                </div>
            </div>

            {/* ================= COUNTRY ================= */}

            <div
                ref={containerRef}
                className="relative flex flex-col gap-1 min-w-[260px]"
            >
                <span className="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    País
                </span>

                <div className="relative">

                    <MapPin className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#00B6E8]" />

                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onFocus={() => region && setOpen(true)}
                        placeholder="Seleccionar país"
                        className="
                        w-full rounded-xl border border-slate-200
                        bg-white px-9 py-2 text-sm font-semibold
                        text-slate-700
                        focus:outline-none focus:ring-2 focus:ring-[#00B6E8]
                        dark:border-slate-700 dark:bg-[#0B2233] dark:text-white
                        "
                    />

                    {query && (
                        <button
                            onClick={() => {
                                setQuery("");
                                setResults([]);
                                applyFilters({ country: null });
                            }}
                            className="absolute right-2 top-1/2 -translate-y-1/2"
                        >
                            <X className="h-4 w-4 text-slate-400 hover:text-slate-600" />
                        </button>
                    )}
                </div>

                {open && results.length > 0 && (
                    <div
                        className="
                        absolute top-full mt-1 w-full
                        max-h-64 overflow-auto
                        rounded-xl border border-slate-200
                        bg-white shadow-xl
                        z-[2000]
                        dark:border-slate-700 dark:bg-[#102C3C]
                        "
                    >
                        {results.map((c) => (
                            <button
                                key={c}
                                onClick={() => {
                                    setQuery(c);
                                    setOpen(false);
                                    applyFilters({ country: c });
                                }}
                                className="
                                block w-full px-4 py-2 text-left text-sm
                                text-slate-700
                                hover:bg-[#E6F7FD]
                                dark:text-white dark:hover:bg-[#143B50]
                                "
                            >
                                {c}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

