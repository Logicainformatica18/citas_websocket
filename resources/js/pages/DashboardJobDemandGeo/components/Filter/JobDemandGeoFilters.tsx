import { useEffect, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
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

    /* =====================
       AUTOCOMPLETE SERVER
    ===================== */
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

    /* =====================
       Escribir → request
    ===================== */
    useEffect(() => {
        if (!region) return;

        clearTimeout(timeout.current);

        timeout.current = setTimeout(() => {
            searchCountries(query);
        }, 300);
    }, [query]);

    /* =====================
       Cambiar región
    ===================== */
    useEffect(() => {
        setQuery("");
        setResults([]);
        setOpen(false);

        if (region) {
            searchCountries("");
        }
    }, [region]);

    /* =====================
       Click fuera
    ===================== */
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

    /* =====================
       Aplicar filtros
    ===================== */
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
        <div className="relative z-40 mt-6 flex flex-wrap gap-4 rounded-2xl border bg-white p-4 shadow-sm dark:bg-[#0F2A3A]">

            {/* ================= CARRERA ================= */}
            <div className="flex flex-col gap-1 min-w-[220px]">
                <span className="text-xs font-semibold">Carrera</span>

                <div className="relative">
                    <GraduationCap className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#00B6E8]" />

                    <select
                        value={careerId}
                        onChange={(e) => {
                            const v = e.target.value;
                            setCareerId(v);
                            applyFilters({ career_id: v || null });
                        }}
                        className="w-full rounded-xl border px-9 py-2 text-sm font-semibold"
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
                            <X className="h-4 w-4 text-slate-400" />
                        </button>
                    )}
                </div>
            </div>

            {/* ================= REGIÓN ================= */}
            <div className="flex flex-col gap-1 min-w-[200px]">
                <span className="text-xs font-semibold">Región</span>

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
                        className="w-full rounded-xl border px-9 py-2 text-sm font-semibold"
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
                            <X className="h-4 w-4 text-slate-400" />
                        </button>
                    )}
                </div>
            </div>

            {/* ================= PAÍS ================= */}
            <div
                ref={containerRef}
                className="relative flex flex-col gap-1 min-w-[260px]"
            >
                <span className="text-xs font-semibold">País</span>

                <div className="relative">
                    <MapPin className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#00B6E8]" />

                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onFocus={() => region && setOpen(true)}
                        placeholder="Seleccionar país"
                        className="w-full rounded-xl border px-9 py-2 text-sm font-semibold"
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
                            <X className="h-4 w-4 text-slate-400" />
                        </button>
                    )}
                </div>

                {open && results.length > 0 && (
                    <div className="absolute bottom-full z-50 mt-1 max-h-64 w-full overflow-auto rounded-xl border bg-white shadow-xl dark:bg-[#102C3C]">
                        {results.map((c) => (
                            <button
                                key={c}
                                onClick={() => {
                                    setQuery(c);
                                    setOpen(false);
                                    applyFilters({ country: c });
                                }}
                                className="block w-full px-4 py-2 text-left text-sm hover:bg-[#E6F7FD]"
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
