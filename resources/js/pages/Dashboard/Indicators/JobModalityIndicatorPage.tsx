import AppLayout from "@/layouts/app-layout";
import { Head, router, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { useEffect, useRef, useState } from "react";
import axios from "axios";

import ModalityKpiGrid from "./components/KPIs/ModalityKpiGrid";
import ModalityDoughnutChart from "./components/Charts/ModalityDoughnutChart";
import ModalitySummaryTable from "./components/Table/ModalitySummaryTable";
import ModalityMethodologyCard from "./components/Methodology/ModalityMethodologyCard";
import { JobModalityIndicatorHeader } from "./components/Header/JobModalityIndicatorHeader";
import ModalityTrendChart from "./components/Charts/ModalityTrendChart";
import { useModalityInsights } from "./components/hooks/useModalityInsights";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: "Dashboard", href: "/dashboard" },
    { title: "Indicadores", href: "/dashboard/indicadores" },
    {
        title: "Modalidad laboral",
        href: "/dashboard/indicadores/modalidad-laboral",
    },
];

/* =========================================================
   Types
========================================================= */
type ModalityItem = {
    modalidad: string;
    vacantes: number;
    porcentaje: number;
};
type TrendItem = {
    month: string;
    remoto: number;
    hibrido: number;
    presencial: number;
}
type PageProps = {
    data: ModalityItem[];
    trendData: TrendItem[]; // 👈 AÑADIR
    filters: {
        region?: string | null;
        country?: string | null;
        city?: string | null;
        source?: string | null;
        year: number;
        period: "s1" | "s2";
    };
    meta: {
        year: number;
        period: "s1" | "s2";
        periodo_label: string;
        total_vacantes: number;
    };
};

export default function JobModalityIndicatorPage() {
    const { data, trendData, filters, meta } = usePage<PageProps>().props;


    /* =====================================================
       Estados Autocomplete (solo geográficos)
    ===================================================== */
    const [regionQuery, setRegionQuery] = useState(filters.region ?? "");
    const [countryQuery, setCountryQuery] = useState(filters.country ?? "");
    const [cityQuery, setCityQuery] = useState(filters.city ?? "");

    const [regions, setRegions] = useState<string[]>([]);
    const [countries, setCountries] = useState<string[]>([]);
    const [cities, setCities] = useState<string[]>([]);

    const [open, setOpen] = useState<"region" | "country" | "city" | null>(null);

    const regionRef = useRef<HTMLDivElement>(null);
    const countryRef = useRef<HTMLDivElement>(null);
    const cityRef = useRef<HTMLDivElement>(null);
const { insights } = useModalityInsights(data, trendData);

    /* =====================================================
       Navegación filtros
    ===================================================== */
    const updateFilters = (newFilters: Partial<PageProps["filters"]>) => {
        router.get(
            "/dashboard/indicadores/modalidad-laboral",
            {
                ...filters,
                ...newFilters,
            },
            { preserveState: true, replace: true }
        );
    };

    const clearFilters = () => {
        router.get(
            "/dashboard/indicadores/modalidad-laboral",
            {
                year: meta.year,
                period: meta.period,
            },
            { replace: true }
        );

        setRegionQuery("");
        setCountryQuery("");
        setCityQuery("");
    };

    /* =====================================================
       Click fuera → cerrar dropdowns
    ===================================================== */
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (!regionRef.current?.contains(e.target as Node)) setOpen(null);
            if (!countryRef.current?.contains(e.target as Node)) setOpen(null);
            if (!cityRef.current?.contains(e.target as Node)) setOpen(null);
        };
        document.addEventListener("mousedown", handler);
        return () => document.removeEventListener("mousedown", handler);
    }, []);

    /* =====================================================
       Fetchers
    ===================================================== */
    const fetchRegions = (q = "") =>
        axios
            .get("/dashboard/indicadores/modalidad-laboral/regions", { params: { q } })
            .then((r) => setRegions(r.data))
            .catch(() => setRegions([]));

    const fetchCountries = (q = "") => {
        if (!filters.region) return;
        axios
            .get("/dashboard/indicadores/modalidad-laboral/countries", {
                params: { q, region: filters.region },
            })
            .then((r) => setCountries(r.data))
            .catch(() => setCountries([]));
    };

    const fetchCities = (q = "") => {
        if (!filters.country) return;
        axios
            .get("/dashboard/indicadores/modalidad-laboral/cities", {
                params: { q, country: filters.country },
            })
            .then((r) => setCities(r.data))
            .catch(() => setCities([]));
    };

    /* =====================================================
       Debounce
    ===================================================== */
    useEffect(() => {
        if (open !== "region") return;
        const t = setTimeout(() => fetchRegions(regionQuery), 250);
        return () => clearTimeout(t);
    }, [regionQuery, open]);

    useEffect(() => {
        if (open !== "country") return;
        const t = setTimeout(() => fetchCountries(countryQuery), 250);
        return () => clearTimeout(t);
    }, [countryQuery, open, filters.region]);

    useEffect(() => {
        if (open !== "city") return;
        const t = setTimeout(() => fetchCities(cityQuery), 250);
        return () => clearTimeout(t);
    }, [cityQuery, open, filters.country]);

    const removeFilter = (key: "region" | "country" | "city") => {
        const reset: any = { [key]: null };

        if (key === "region") {
            reset.country = null;
            reset.city = null;
            setCountryQuery("");
            setCityQuery("");
        }

        if (key === "country") {
            reset.city = null;
            setCityQuery("");
        }

        if (key === "city") {
            setCityQuery("");
        }

        updateFilters(reset);
    };

    /* =====================================================
       Render
    ===================================================== */
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Indicador de Modalidad Laboral | Observatorio ISIL" />

            {/* HEADER (año + semestre viven acá) */}
            <JobModalityIndicatorHeader meta={meta} />

            <div className="bg-background px-6 py-6 space-y-8">

                {/* ===== FILTROS ===== */}
                <div className="flex items-center justify-between">
                    <span className="text-sm font-semibold text-slate-700">
                        Filtros geográficos
                    </span>

                    {(filters.region || filters.country || filters.city) && (
                        <button
                            onClick={clearFilters}
                            className="text-xs font-semibold text-[#00B6E8] hover:underline"
                        >
                            Limpiar filtros
                        </button>
                    )}
                </div>

                <div className="rounded-2xl border bg-white p-4 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-3">

                        {/* REGIÓN */}
                        <div ref={regionRef} className="relative">
                            <label className="text-xs font-semibold">Región</label>
                            <input
                                value={regionQuery}
                                onFocus={() => {
                                    setOpen("region");
                                    fetchRegions(regionQuery);
                                }}
                                onChange={(e) => setRegionQuery(e.target.value)}
                                placeholder="Buscar región…"
                                className="mt-1 w-full rounded-lg border px-3 py-2 text-sm"
                            />
                            {open === "region" && regions.length > 0 && (
                                <div className="absolute z-20 mt-1 w-full max-h-60 overflow-auto rounded-lg border bg-white shadow">
                                    {regions.map((r) => (
                                        <button
                                            key={r}
                                            onClick={() => {
                                                updateFilters({ region: r, country: null, city: null });
                                                setRegionQuery(r);
                                                setCountryQuery("");
                                                setCityQuery("");
                                                setOpen(null);
                                            }}
                                            className="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100"
                                        >
                                            {r}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* PAÍS */}
                        <div ref={countryRef} className="relative">
                            <label className="text-xs font-semibold">País</label>
                            <input
                                value={countryQuery}
                                disabled={!filters.region}
                                onFocus={() => {
                                    if (!filters.region) return;
                                    setOpen("country");
                                    fetchCountries(countryQuery);
                                }}
                                onChange={(e) => setCountryQuery(e.target.value)}
                                placeholder="Buscar país…"
                                className="mt-1 w-full rounded-lg border px-3 py-2 text-sm disabled:bg-slate-100"
                            />
                            {open === "country" && countries.length > 0 && (
                                <div className="absolute z-20 mt-1 w-full max-h-60 overflow-auto rounded-lg border bg-white shadow">
                                    {countries.map((c) => (
                                        <button
                                            key={c}
                                            onClick={() => {
                                                updateFilters({ country: c, city: null });
                                                setCountryQuery(c);
                                                setCityQuery("");
                                                setOpen(null);
                                            }}
                                            className="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100"
                                        >
                                            {c}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* CIUDAD */}
                        <div ref={cityRef} className="relative">
                            <label className="text-xs font-semibold">Ciudad</label>
                            <input
                                value={cityQuery}
                                disabled={!filters.country}
                                onFocus={() => {
                                    if (!filters.country) return;
                                    setOpen("city");
                                    fetchCities(cityQuery);
                                }}
                                onChange={(e) => setCityQuery(e.target.value)}
                                placeholder="Buscar ciudad…"
                                className="mt-1 w-full rounded-lg border px-3 py-2 text-sm disabled:bg-slate-100"
                            />
                            {open === "city" && cities.length > 0 && (
                                <div className="absolute z-20 mt-1 w-full max-h-60 overflow-auto rounded-lg border bg-white shadow">
                                    {cities.map((c) => (
                                        <button
                                            key={c}
                                            onClick={() => {
                                                updateFilters({ city: c });
                                                setCityQuery(c);
                                                setOpen(null);
                                            }}
                                            className="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100"
                                        >
                                            {c}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Chips */}
                {(filters.region || filters.country || filters.city) && (
                    <div className="flex flex-wrap gap-2">
                        {filters.region && (
                            <FilterChip
                                label={`Región: ${filters.region}`}
                                onRemove={() => removeFilter("region")}
                            />
                        )}
                        {filters.country && (
                            <FilterChip
                                label={`País: ${filters.country}`}
                                onRemove={() => removeFilter("country")}
                            />
                        )}
                        {filters.city && (
                            <FilterChip
                                label={`Ciudad: ${filters.city}`}
                                onRemove={() => removeFilter("city")}
                            />
                        )}
                    </div>
                )}

                {/* KPIs */}
                <ModalityKpiGrid data={data} totalVacantes={meta.total_vacantes} />
                {/* Chart */}
                <ModalityDoughnutChart data={data} />
                <ModalityTrendChart data={trendData} />

                {/* Table */}
                <ModalitySummaryTable data={data} />

                {/* Methodology */}
                <ModalityMethodologyCard />
                
              <div className="grid gap-4 md:grid-cols-1">
                
  {insights.map(
    (i) =>
      i.visible && (
        <div
          key={i.key}
          className="rounded-xl border bg-[#ECFAFD] p-4"
        >
          <p className="font-semibold text-[#0A2540]">
            {i.title}
          </p>
          <p className="text-sm text-slate-700">
            {i.text}
          </p>
        </div>
      )
  )}
  
</div>

            </div>
        </AppLayout>
    );
}

function FilterChip({
    label,
    onRemove,
}: {
    label: string;
    onRemove: () => void;
}) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-[#E6F7FD] px-3 py-1 text-xs font-semibold text-[#005F7A]">
            {label}
            <button
                onClick={onRemove}
                className="ml-1 rounded-full px-1 hover:bg-[#D0EEF8]"
            >
                ✕
            </button>
        </span>
    );
}
