import React, { useState } from "react";
import PartSummary from "./Components/PartSummary";
import PartPages from "./Components/PartPages";
import PartTables from "./Components/PartTables";
import PartGraphs from "./Components/PartGraphs";
import PartMetadata from "./Components/PartMetadata";

export default function Part({ pdf, part, pages, summary }) {
    const [tab, setTab] = useState("summary");

    return (
        <div className="container mx-auto p-6">

            <h1 className="text-2xl font-bold mb-4">
                Parte {part.part_number} del documento: {pdf.title}
            </h1>

            {/* TABS */}
            <div className="flex gap-4 border-b mb-6 pb-2">
                <button onClick={() => setTab("summary")}>Resumen</button>
                <button onClick={() => setTab("pages")}>Páginas</button>
                <button onClick={() => setTab("tables")}>Tablas</button>
                <button onClick={() => setTab("graphs")}>Gráficos</button>
                {/* <button onClick={() => setTab("meta")}>Metadata</button> */}
            </div>

            {/* CONTENIDO */}
            {tab === "summary" && <PartSummary summary={summary} />}
            {tab === "pages" && <PartPages pages={pages} />}
            {tab === "tables" && <PartTables pages={pages} />}
            {tab === "graphs" && <PartGraphs pages={pages} />}
            {/* {tab === "meta" && <PartMetadata part={part} />} */}
        </div>
    );
}
