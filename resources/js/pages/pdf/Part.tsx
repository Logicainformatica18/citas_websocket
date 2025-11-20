import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { Link } from "@inertiajs/react";
import { useState } from "react";

import { FileText, FileSearch, Table2, BarChart3, Hourglass } from "lucide-react";

import PartSummary from "./Components/PartSummary";
import PartPages from "./Components/PartPages";
import PartTables from "./Components/PartTables";
import PartGraphs from "./Components/PartGraphs";

export default function Part({ pdf, part, pages, summary }) {
    const [tab, setTab] = useState("summary");

    /* ------------------------------------------
       🧠 Estado por sección (según datos recibidos)
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
        { title: "Documentos PDF", href: "/pdf" },
        { title: pdf.title, href: `/pdf/${pdf.id}` },
        { title: `Parte ${part.part_number}`, href: "#" },
    ];

    /* -------------------------------------------------------
       TABS dinámicos con íconos + estados
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

                    <p className="text-gray-600 dark:text-gray-400 mt-1">
                        Documento:{" "}
                        <Link
                            href={`/pdf/${pdf.id}`}
                            className="font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            {pdf.title}
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
                                    className={`
                                        px-5 py-2 rounded-t-md text-sm font-semibold transition-all flex items-center gap-2
                                        ${
                                            tab === t.key
                                                ? "bg-blue-600 text-white shadow dark:bg-blue-500"
                                                : disabled
                                                    ? "text-gray-400 dark:text-gray-600 cursor-not-allowed"
                                                    : "text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                                        }
                                    `}
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

                    {/* Si la sección NO está lista → Mensaje UX */}
                    {status[tab] === "loading" && (
                        <div className="flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400">
                            <Hourglass className="w-10 h-10 mb-3 animate-spin text-blue-500" />
                            <p className="text-lg font-medium">Procesando esta sección…</p>
                            <p className="text-sm mt-1">Vuelve a intentarlo en unos segundos.</p>
                        </div>
                    )}

                    {/* Render de contenidos solo si está listo */}
                    {status.summary === "ready" && tab === "summary" && <PartSummary summary={summary} />}
                    {status.pages === "ready" && tab === "pages" && <PartPages pages={pages} />}
                    {status.tables === "ready" && tab === "tables" && <PartTables pages={pages} />}
                    {status.graphs === "ready" && tab === "graphs" && <PartGraphs pages={pages} />}

                </div>
            </div>
        </AppLayout>
    );
}
