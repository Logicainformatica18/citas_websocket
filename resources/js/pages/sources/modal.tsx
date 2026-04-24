import { useEffect, useState } from 'react';
import axios from 'axios';
import { CheckCircle, XCircle, Loader2 } from 'lucide-react';

type Source = {
    id?: number;
    name: string;
    base_url: string;
    api_url?: string;
    api_key?: string;
};

type Props = {
    open: boolean;
    onClose: () => void;
    onSaved: () => void;
    source: Source | null;
};

export default function SourceModal({ open, onClose, onSaved, source }: Props) {
    const [form, setForm] = useState<Source>({
        name: '',
        base_url: '',
        api_url: '',
        api_key: '',
    });

    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<'success' | 'error' | null>(null);

    useEffect(() => {
        if (source) {
            setForm(source);
        } else {
            setForm({
                name: '',
                base_url: '',
                api_url: '',
                api_key: '',
            });
        }

        setTestResult(null);
    }, [source]);

    const handleSubmit = async (e: any) => {
        e.preventDefault();

        try {
            if (form.id) {
                await axios.put(`/sources/${form.id}`, form);
            } else {
                await axios.post(`/sources`, form);
            }

            onSaved();
            onClose();
        } catch (error) {
            console.error(error);
            alert('Error al guardar');
        }
    };

    // 🔥 TEST API
    const handleTest = async () => {
        setTesting(true);
        setTestResult(null);

        try {
            await axios.post('/sources/test-api', {
                api_url: form.api_url,
                api_key: form.api_key,
            });

            setTestResult('success');
        } catch (e) {
            setTestResult('error');
        } finally {
            setTesting(false);
        }
    };

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
            onClick={onClose}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                className="w-full max-w-lg p-6 space-y-5 rounded-2xl shadow-xl
                bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100"
            >
                {/* HEADER */}
                <div className="flex justify-between items-center">
                    <h2 className="text-lg font-semibold">
                        {form.id ? 'Editar fuente' : 'Nueva fuente'}
                    </h2>

                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        ✕
                    </button>
                </div>

                {/* FORM */}
                <form onSubmit={handleSubmit} className="space-y-4">

                    {/* NAME */}
                    <div>
                        <label className="text-sm">Nombre</label>
                        <input
                            value={form.name}
                            onChange={(e) =>
                                setForm({ ...form, name: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border
                            bg-white dark:bg-gray-800
                            border-gray-200 dark:border-gray-700
                            focus:ring-2 focus:ring-blue-500 outline-none"
                            required
                        />
                    </div>

                    {/* BASE URL */}
                    <div>
                        <label className="text-sm">Base URL</label>
                        <input
                            value={form.base_url}
                            onChange={(e) =>
                                setForm({ ...form, base_url: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border
                            bg-white dark:bg-gray-800
                            border-gray-200 dark:border-gray-700
                            focus:ring-2 focus:ring-blue-500 outline-none"
                            required
                        />
                    </div>

                    {/* API URL */}
                    <div>
                        <label className="text-sm">API URL</label>
                        <input
                            value={form.api_url}
                            onChange={(e) =>
                                setForm({ ...form, api_url: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border
                            bg-white dark:bg-gray-800
                            border-gray-200 dark:border-gray-700
                            focus:ring-2 focus:ring-blue-500 outline-none"
                        />
                    </div>

                    {/* API KEY */}
                    <div>
                        <label className="text-sm">API Key</label>
                        <input
                            value={form.api_key}
                            onChange={(e) =>
                                setForm({ ...form, api_key: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border
                            bg-white dark:bg-gray-800
                            border-gray-200 dark:border-gray-700
                            focus:ring-2 focus:ring-blue-500 outline-none"
                        />
                    </div>

                    {/* 🔥 TEST BUTTON */}
                    <div className="flex items-center justify-between pt-2">
                        <button
                            type="button"
                            onClick={handleTest}
                            disabled={testing}
                            className="px-4 py-2 rounded-lg text-sm font-medium
                            bg-gray-100 dark:bg-gray-800
                            hover:bg-gray-200 dark:hover:bg-gray-700
                            transition flex items-center gap-2"
                        >
                            {testing && <Loader2 className="w-4 h-4 animate-spin" />}
                            Test API
                        </button>

                        {/* RESULT */}
                        {testResult === 'success' && (
                            <div className="flex items-center gap-2 text-green-500 text-sm">
                                <CheckCircle className="w-4 h-4" />
                                Conexión exitosa
                            </div>
                        )}

                        {testResult === 'error' && (
                            <div className="flex items-center gap-2 text-red-500 text-sm">
                                <XCircle className="w-4 h-4" />
                                Error de conexión
                            </div>
                        )}
                    </div>

                    {/* ACTIONS */}
                    <div className="flex justify-end gap-3 pt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 rounded-lg
                            bg-gray-100 dark:bg-gray-800
                            text-gray-700 dark:text-gray-200
                            hover:bg-gray-200 dark:hover:bg-gray-700"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            className="px-5 py-2 rounded-lg text-white font-medium
                            bg-gradient-to-r from-blue-600 to-blue-500
                            shadow-md hover:opacity-90 transition"
                        >
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}