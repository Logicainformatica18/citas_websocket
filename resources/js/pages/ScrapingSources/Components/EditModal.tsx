import React, { useState } from "react";
import axios from "axios";

export default function EditModal({ data, onClose }: any) {
    const [form, setForm] = useState({ ...data });

    const submit = async () => {
        await axios.put(`/scraping-sources/${data.id}`, form);
        onClose();
        location.reload();
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white dark:bg-gray-800 p-6 rounded-xl w-full max-w-lg shadow-lg">
                <h2 className="text-xl font-bold mb-4">Editar Fuente</h2>

                <div className="grid gap-3">
                    <input className="form-input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                    <input className="form-input" value={form.url} onChange={(e) => setForm({ ...form, url: e.target.value })} />
                    <input className="form-input" value={form.frequency} onChange={(e) => setForm({ ...form, frequency: e.target.value })} />
                    <textarea className="form-input h-20" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />

                    <div className="grid grid-cols-2 gap-3 text-sm">
                        <label><input type="checkbox" checked={form.has_pdf} onChange={() => setForm({ ...form, has_pdf: !form.has_pdf })} /> Tiene PDF</label>
                        <label><input type="checkbox" checked={form.web_only} onChange={() => setForm({ ...form, web_only: !form.web_only })} /> Solo Web</label>
                        <label><input type="checkbox" checked={form.has_api} onChange={() => setForm({ ...form, has_api: !form.has_api })} /> Tiene API</label>
                        <label><input type="checkbox" checked={form.scrapable} onChange={() => setForm({ ...form, scrapable: !form.scrapable })} /> Scrapeable</label>
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <button onClick={onClose} className="px-4 py-2 bg-gray-400 text-white rounded-lg">Cancelar</button>
                    <button onClick={submit} className="px-4 py-2 bg-blue-600 text-white rounded-lg">Actualizar</button>
                </div>
            </div>
        </div>
    );
}
