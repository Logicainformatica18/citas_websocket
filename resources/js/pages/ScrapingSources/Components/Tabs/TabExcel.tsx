import React from "react";

interface Props {
    handleFile: (e: React.ChangeEvent<HTMLInputElement>, field: string) => void;
}

export default function TabExcel({ handleFile }: Props) {
    return (
        <div>
            <label className="text-sm font-medium">Subir Excel o CSV</label>
            <input
                type="file"
                accept=".xlsx,.xls,.csv"
                className="mt-2"
                onChange={(e) => handleFile(e, "excel_file")}
            />
        </div>
    );
}
