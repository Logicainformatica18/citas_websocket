import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { Link } from "@inertiajs/react";
import { useState } from "react";

import { FileText, FileSearch, Table2, BarChart3, Hourglass } from "lucide-react";

import PartSummary from "@/pages/pdf/Components/PartSummary";
import PartPages from "@/pages/pdf/Components/PartPages";
import PartTables from "@/pages/pdf/Components/PartTables";
import PartGraphs from "@/pages/pdf/Components/PartGraphs";

interface Props {
    source: any;
    part: any;
    pages: any[];
    summary: any;
    file_url: string;
}

export default function PartDetail({ source, part, pages, summary, file_url }: Props) {
    const [tab, setTab] = useState("summary");

    /* ------------------------------------------
       🧠 Estado por sección (según datos)
    ------------------------------------------- */
    const status = {
        summary: summary ? "ready" : "loading",
        pages: pages.length > 0 ? "ready" : "loading",
        tables: pages.some((p) => p.tables?.length > 0) ? "ready" : "loading",
        graphs: pages.some((p) => p.graphs?.length > 0) ? "ready" : "loading",
    };

    /* -------------------------------------------------------
       Breadcrumb estandarizado
    -------------------------------------------------------- */
    const breadcrumbs: BreadcrumbItem[] = [
        { title: "Scraping Sources", href: "/scraping-sources" },
        { title: source.name, href: `/scraping-sources/${source.id}/parts` },
        { title: `Parte ${part.part_number}`, href: "#" },
    ];

    /* -------------------------------------------------------
       Configuración de tabs (igual que tu módulo PDF)
    -------------------------------------------------------- */
    const tabs = [
        { key: "summary", label: "Resumen", icon: FileText, status: status.summary },
        { key: "pages", label: "Páginas", icon: FileSearch, status: status.pages },
        { key: "tables", label: "Tablas", icon: Table2, status: status.tables },
        { key: "graphs", label: "Gráficos", icon: BarChart3, status: status.graphs },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

                {/* HEADER */}
                <div className="mb-8 pb-4 border-b border-gray-200 dark:border-gray-800">
                    <h1 className="text-3xl font-bold flex items-center gap-2">
                        Parte {part.part_number}
                    </h1>

                    {/* LINK AL PDF */}
                    <p className="mt-2">
                        <a
                            href={file_url}
                            target="_blank"
                            className="text-blue-600 dark:text-blue-400 underline font-semibold"
                        >
                            Ver PDF original →
                        </a>
                    </p>

                    <p className="text-gray-600 dark:text-gray-400 mt-1">
                        Fuente:{" "}
                        <Link
                            href={`/scraping-sources/${source.id}/parts`}
                            className="font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            {source.name}
                        </Link>
                    </p>
                </div>

                {/* TABS */}
                <div className="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <nav className="flex gap-2 overflow-x-auto pb-1">

                        {tabs.map((t) => {
                            const Icon = t.icon;
                            const disabled = t.status !== "ready";

                            return (
                                <button
                                    key={t.key}
                                    onClick={() => !disabled && setTab(t.key)}
                                    disabled={disabled}
                                    className={`px-5 py-2 rounded-t-md text-sm font-semibold transition-all flex items-center gap-2
                                        ${tab === t.key
                                            ? "bg-blue-600 text-white shadow dark:bg-blue-500"
                                            : disabled
                                                ? "text-gray-400 dark:text-gray-600 cursor-not-allowed"
                                                : "text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                                        }`}
                                >
                                    <Icon className="w-4 h-4" />
                                    {t.label}
                                    {disabled && <Hourglass className="w-4 h-4 animate-spin" />}
                                </button>
                            );
                        })}

                    </nav>
                </div>

                {/* CONTENIDO */}
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg rounded-xl p-6">

                    {/* Loading */}
                    {status[tab] === "loading" && (
                        <div className="flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400">
                            <Hourglass className="w-10 h-10 mb-3 animate-spin text-blue-500" />
                            <p className="text-lg font-medium">Procesando esta sección…</p>
                            <p className="text-sm mt-1">Vuelve a intentarlo en unos segundos.</p>
                        </div>
                    )}

                    {/* Renders */}
                    {status.summary === "ready" && tab === "summary" && (
                        <PartSummary summary={summary} />
                    )}

                    {status.pages === "ready" && tab === "pages" && (
                        <PartPages pages={pages} />
                    )}

                    {status.tables === "ready" && tab === "tables" && (
                        <PartTables pages={pages} />
                    )}

                    {status.graphs === "ready" && tab === "graphs" && (
                        <PartGraphs pages={pages} />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
