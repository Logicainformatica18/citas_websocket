import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import {
    Globe,
    MapPin,
    X,
} from "lucide-react";

interface Props {
    regions: string[];
}

export default function JobDemandGeoFilters({ regions }: Props) {
    const { filters } = usePage().props as any;

    const [region, setRegion] = useState<string>(filters.region ?? "");
    const [countryQuery, setCountryQuery] = useState<string>(filters.country ?? "");
    const [countryResults, setCountryResults] = useState<string[]>([]);
    const [showCountries, setShowCountries] = useState(false);

    const timeoutRef = useRef<any>(null);

    /* =====================
       Buscar países (debounce)
    ===================== */
    useEffect(() => {
        if (!countryQuery || countryQuery.length < 2) {
            setCountryResults([]);
            return;
        }

        clearTimeout(timeoutRef.current);

        timeoutRef.current = setTimeout(() => {
            fetch(
                `/dashboard/indicators/job-demand-geo/search-countries?q=${countryQuery}&region=${region ?? ""}`
            )
                .then((res) => res.json())
                .then((data) => {
                    setCountryResults(data);
                    setShowCountries(true);
                });
        }, 300);
    }, [countryQuery, region]);

    /* =====================
       Aplicar filtros
    ===================== */
    const applyFilters = (params: Partial<typeof filters>) => {
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

    /* =====================
       Limpiar
    ===================== */
    const clearCountry = () => {
        setCountryQuery("");
        applyFilters({ country: null });
    };

    const clearRegion = () => {
        setRegion("");
        setCountryQuery("");
        applyFilters({ region: null, country: null });
    };

    return (
        <div className="mt-6 flex flex-wrap items-end gap-4 rounded-2xl border bg-white p-4 shadow-sm dark:bg-[#0F2A3A]">
            {/* ================= REGIÓN ================= */}
            <div className="flex flex-col gap-1 min-w-[200px]">
                <span className="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Región
                </span>

                <div className="relative">
                    <Globe className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[#00B6E8]" />

                    <select
                        value={region}
                        onChange={(e) => {
                            setRegion(e.target.value);
                            applyFilters({ region: e.target.value || null, country: null });
                        }}
                        className="w-full rounded-xl border px-9 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-[#00B6E8]"
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
                            onClick={clearRegion}
                            className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>
            </div>

            {/* ================= PAÍS ================= */}
            <div className="relative flex flex-col gap-1 min-w-[260px]">
                <span className="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    País
                </span>

                <div className="relative">
                    <MapPin className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[#00B6E8]" />

                    <input
                        value={countryQuery}
                        onChange={(e) => setCountryQuery(e.target.value)}
                        placeholder="Buscar país…"
                        className="w-full rounded-xl border px-9 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-[#00B6E8]"
                        onFocus={() => setShowCountries(true)}
                        onBlur={() => setTimeout(() => setShowCountries(false), 200)}
                    />

                    {countryQuery && (
                        <button
                            onClick={clearCountry}
                            className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>

                {showCountries && countryResults.length > 0 && (
                    <div className="absolute top-full z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border bg-white shadow-lg dark:bg-[#102C3C]">
                        {countryResults.map((c) => (
                            <button
                                key={c}
                                onClick={() => {
                                    setCountryQuery(c);
                                    applyFilters({ country: c });
                                    setShowCountries(false);
                                }}
                                className="block w-full px-4 py-2 text-left text-sm hover:bg-[#E6F7FD] dark:hover:bg-[#123A52]"
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
