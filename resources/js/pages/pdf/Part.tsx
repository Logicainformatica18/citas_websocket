import AppLayout from "@/layouts/app-layout";
import { BreadcrumbItem } from "@/types";
import { Link } from "@inertiajs/react";
import { useState } from "react";

import PartSummary from "./Components/PartSummary";
import PartPages from "./Components/PartPages";
import PartTables from "./Components/PartTables";
import PartGraphs from "./Components/PartGraphs";
// import PartMetadata from "./Components/PartMetadata";

export default function Part({ pdf, part, pages, summary }) {
    const [tab, setTab] = useState("summary");

    /* ▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬
       BREADCRUMB (igual estilo que Index)
    ▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬ */
    const breadcrumbs: BreadcrumbItem[] = [
        { title: "Documentos PDF", href: "/pdf" },
        { title: pdf.title, href: `/pdf/${pdf.id}` },
        { title: `Parte ${part.part_number}`, href: "#" },
    ];

    const tabs = [
        { key: "summary", label: "Resumen" },
        { key: "pages", label: "Páginas" },
        { key: "tables", label: "Tablas" },
        { key: "graphs", label: "Gráficos" },
        // { key: "meta", label: "Metadata" },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8 bg-gray-50 dark:bg-gray-900 min-h-screen text-gray-900 dark:text-gray-100">

                {/* HEADER */}
                <div className="mb-6 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <h1 className="text-3xl font-bold flex items-center gap-2">
                        Parte {part.part_number}
                    </h1>

                    <p className="text-gray-600 dark:text-gray-400 mt-1">
                        Documento:{" "}
                        <Link
                            href={`/pdf/${pdf.id}`}
                            className="font-semibold text-blue-600 hover:underline"
                        >
                            {pdf.title}
                        </Link>
                    </p>
                </div>

                {/* TABS */}
                <div className="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <nav className="flex gap-2 overflow-x-auto pb-1">
                        {tabs.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => setTab(t.key)}
                                className={`px-4 py-2 rounded-t-md text-sm font-semibold transition-all ${
                                    tab === t.key
                                        ? "bg-blue-600 text-white shadow-sm"
                                        : "text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                                }`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </nav>
                </div>

                {/* CONTENIDO */}
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm rounded-lg p-6">

                    {tab === "summary" && <PartSummary summary={summary} />}
                    {tab === "pages" && <PartPages pages={pages} />}
                    {tab === "tables" && <PartTables pages={pages} />}
                    {tab === "graphs" && <PartGraphs pages={pages} />}
                    {/* {tab === "meta" && <PartMetadata part={part} />} */}

                </div>

            </div>
        </AppLayout>
    );
}
