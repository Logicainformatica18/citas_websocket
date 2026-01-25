import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

/* COMPONENTES */
import { JobDemandGeoHeader } from "./components/Header/JobDemandGeoHeader";

import JobDemandGeoKpiGrid from "./components/KPIs/JobDemandGeoKpiGrid";
import JobDemandCityTable from "./components/Table/JobDemandCityTable";
import JobDemandGeoMethodologyCard from "./components/Methodology/JobDemandGeoMethodologyCard";
import CityDemandKpiGrid from "./components/KPIs/CityDemandKpiGrid";



/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: "Dashboard", href: "/dashboard" },
    { title: "Indicadores", href: "/dashboard/indicadores" },
    { title: "Demanda laboral por ciudad", href: "#" },
];

export default function JobDemandGeoIndex() {
    /**
     * Props desde Inertia
     */
    const {
        filters,
        meta,
        ranking,
        regions = [], // ⬅️ IMPORTANTE: evita crash del header
    } = usePage().props as any;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Demanda laboral por ciudad" />

            {/* ================= HEADER + FILTROS ================= */}
            <JobDemandGeoHeader
                meta={meta}
                filters={filters}
                regions={regions}
            />
<CityDemandKpiGrid meta={meta} />
            {/* ================= KPIs ================= */}
            <JobDemandGeoKpiGrid meta={meta} />

            {/* ================= CONTENIDO ================= */}
            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
                {/* RANKING */}
                <div className="xl:col-span-2">
                    <JobDemandCityTable data={ranking} />
                </div>

                {/* METODOLOGÍA */}
                <div>
                    <JobDemandGeoMethodologyCard />
                </div>
            </div>

            {/* 🔥 MAPA DE CALOR IRÁ AQUÍ DESPUÉS */}
        </AppLayout>
    );
}
