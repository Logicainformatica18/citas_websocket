import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

/* COMPONENTES */
import JobDemandGeoHeader from "./components/Header/JobDemandGeoHeader";
import JobDemandGeoFilters from "./components/Filter/JobDemandGeoFilters";
import CityDemandHeatmap from "./components/Chart/CityDemandMap";

import JobDemandGeoKpiGrid from "./components/KPIs/JobDemandGeoKpiGrid";
import CityDemandKpiGrid from "./components/KPIs/CityDemandKpiGrid";
import JobDemandCityTable from "./components/Table/JobDemandCityTable";
import JobDemandGeoMethodologyCard from "./components/Methodology/JobDemandGeoMethodologyCard";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: "Dashboard", href: "/dashboard" },
    { title: "Indicadores", href: "/dashboard/indicadores" },
    { title: "Demanda laboral por ciudad", href: "#" },
];

export default function JobDemandGeoIndex() {
    const {
        meta,
        ranking,
        regions = [],
    } = usePage().props as any;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Demanda laboral por ciudad" />

            {/* ================= HEADER ================= */}
            <JobDemandGeoHeader meta={meta} />

            {/* ================= KPIs ================= */}
            <div className="mt-6 space-y-6">
                <CityDemandKpiGrid meta={meta} />
                <JobDemandGeoKpiGrid meta={meta} />
            </div>

            {/* ================= FILTROS ================= */}
            <JobDemandGeoFilters regions={regions} />

            {/* ================= MAPA DE CALOR ================= */}
            <div className="mt-6">
                <CityDemandHeatmap />
            </div>

            {/* ================= CONTENIDO ================= */}
            <div className="grid grid-cols-1 xl:grid-cols-12 gap-6 mt-6">
                {/* TABLA */}
                <div className="xl:col-span-6">
                   <JobDemandCityTable ranking={ranking} />

                </div>

                {/* METODOLOGÍA */}
                <div className="xl:col-span-6">
                    <JobDemandGeoMethodologyCard />
                </div>
            </div>
        </AppLayout>
    );
}
