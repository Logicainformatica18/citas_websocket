import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

/* COMPONENTES */
import JobDemandGeoHeader from "./components/Header/JobDemandGeoHeader";
import JobDemandGeoFilters from "./components/Filter/JobDemandGeoFilters";
import CityDemandHeatmap from "./components/Chart/CityDemandMap";

import CityDemandKpiGrid from "./components/KPIs/CityDemandKpiGrid";
import JobDemandCityTable from "./components/Table/JobDemandCityTable";
import JobDemandGeoMethodologyCard from "./components/Methodology/JobDemandGeoMethodologyCard";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: "Dashboard", href: "/dashboard" },
    { title: "Demanda laboral (Geo + Carrera)", href: "#" },
];

export default function JobDemandGeoIndex() {
    const {
        meta,
        ranking,
        regions = [],
        careers = [],        // 🔥 NUEVO
        filters = {},        // 🔥 NUEVO
    } = usePage().props as any;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Demanda laboral por ciudad" />

            {/* ================= HEADER ================= */}
            <JobDemandGeoHeader meta={meta} filters={filters} />

            {/* ================= KPIs ================= */}
            <div className="mt-6 space-y-6">
                <CityDemandKpiGrid meta={meta} />
            </div>

            {/* ================= FILTROS ================= */}
            <JobDemandGeoFilters
                regions={regions}
                careers={careers}     // 🔥 PASAMOS CARRERAS
                filters={filters}     // 🔥 PASAMOS FILTROS
            />

            {/* ================= MAPA DE CALOR ================= */}
            <div className="mt-6">
                <CityDemandHeatmap filters={filters} />
            </div>

            {/* ================= CONTENIDO ================= */}
            <div className="grid grid-cols-1 gap-6 mt-6">

                {/* TABLA */}
                <JobDemandCityTable
                    ranking={ranking}
                    filters={filters}
                />

                {/* METODOLOGÍA (opcional) */}
                {/* <JobDemandGeoMethodologyCard /> */}

            </div>
        </AppLayout>
    );
}
