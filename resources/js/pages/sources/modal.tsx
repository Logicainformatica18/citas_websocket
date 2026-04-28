import { useEffect, useState } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import { CheckCircle, XCircle, Loader2 } from 'lucide-react';

type Source = {
    id?: number;
    name: string;
    api_url?: string;
    api_key?: string;
    app_id?: string; // 🔥 agregar
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
     
        api_url: '',
        api_key: '',
        app_id: '', // 🔥 agregar
    });

    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<'success' | 'error' | null>(null);

    useEffect(() => {
       if (source) {
    setForm({
        id: source.id,
        name: source.name || '',
         
        api_url: source.api_url || '',
        api_key: source.api_key || '',
        app_id: source.app_id || '',
    });
} else {
            setForm({
                name: '',
                
                api_url: '',
                api_key: '',
                app_id: '', // 🔥 agregar
            });
        }

        setTestResult(null);
    }, [source]);
const [jobs, setJobs] = useState<any[]>([]);
    // 🔥 VALIDACIÓN FLEXIBLE
    const validateForm = () => {
        if (!form.name || form.name.trim() === '') {
            Swal.fire({
                icon: 'warning',
                title: 'Campo requerido',
                text: 'El nombre (título) es obligatorio'
            });
            return false;
        }
        return true;
    };

    // 🔥 SUBMIT
    const handleSubmit = async (e: any) => {
        e.preventDefault();

        if (!validateForm()) return;

        const confirm = await Swal.fire({
            title: form.id ? '¿Actualizar fuente?' : '¿Crear fuente?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.isConfirmed) return;

        try {
            Swal.fire({
                title: 'Guardando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            // 🔥 MAPEAMOS A BACKEND
            const payload = {
                source: form.name,
                
                api_url: form.api_url || null,
                api_key: form.api_key || null,
                    app_id: form.app_id || null, // 🔥 FALTABA ESTO
            };

            if (form.id) {
                await axios.put(`/sources/${form.id}`, payload);
            } else {
                await axios.post(`/sources`, payload);
            }

            Swal.fire({
                icon: 'success',
                title: 'Guardado correctamente',
                timer: 1400,
                showConfirmButton: false
            });

            onSaved();
            onClose();

        } catch (error: any) {

            const message =
                error.response?.data?.message ||
                'Error al guardar';

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message
            });

            console.error(error);
        }
    };

    // 🔥 TEST API (solo si hay URL)
   const handleTest = async () => {
    if (!form.api_url) {
        Swal.fire({
            icon: 'info',
            title: 'Campo opcional vacío',
            text: 'No hay API URL para probar'
        });
        return;
    }

    setTesting(true);
    setTestResult(null);

    try {
        Swal.fire({
            title: 'Probando API...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const res = await axios.post('/sources/test-data', {
            api_url: form.api_url,
            api_key: form.api_key,
            app_id: form.app_id,
        });

        setJobs(res.data.jobs || []);

        Swal.close();

        setTestResult('success');

    } catch (e: any) {

        Swal.fire({
            icon: 'error',
            title: 'Error al obtener datos'
        });

        setJobs([]);
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
                <div className="flex justify-between items-center">
                    <h2 className="text-lg font-semibold">
                        {form.id ? 'Editar fuente' : 'Nueva fuente'}
                    </h2>

                    <button onClick={onClose}>✕</button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">

                    {/* 🔥 SOLO ESTE ES OBLIGATORIO */}
                    <div>
                        <label className="text-sm">Nombre *</label>
                        <input
                           value={form.name || ''}
                            onChange={(e) =>
                                setForm({ ...form, name: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border"
                        />
                    </div>

                    {/* OPCIONAL */}
                    {/* <div>
                        <label className="text-sm">Base URL (opcional)</label>
                        <input
                            value={form.base_url}
                            onChange={(e) =>
                                setForm({ ...form, base_url: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border"
                        />
                    </div> */}
                    <div>
                        <label className="text-sm">App ID (opcional)</label>
                        <input
                          value={form.app_id || ''}
                            onChange={(e) =>
                                setForm({ ...form, app_id: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border
        bg-white dark:bg-gray-800
        border-gray-200 dark:border-gray-700
        focus:ring-2 focus:ring-blue-500 outline-none"
                        />
                    </div>
                    {/* OPCIONAL */}
                    <div>
                        <label className="text-sm">API URL (opcional)</label>
                        <input
                            value={form.api_url || ''}
                            onChange={(e) =>
                                setForm({ ...form, api_url: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border"
                        />
                    </div>

                    {/* OPCIONAL */}
                    <div>
                        <label className="text-sm">API Key (opcional)</label>
                        <input
                           value={form.api_key || ''}
                            onChange={(e) =>
                                setForm({ ...form, api_key: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border"
                        />
                    </div>

                    {/* TEST */}
                    <div className="flex items-center justify-between pt-2">
                        {/* <button
                            type="button"
                            onClick={handleTest}
                            disabled={testing}
                            className="px-4 py-2 rounded-lg bg-gray-200 flex items-center gap-2"
                        >
                            {testing && <Loader2 className="w-4 h-4 animate-spin" />}
                            Test API
                        </button> */}

                        {testResult === 'success' && (
                            <CheckCircle className="text-green-500 w-5 h-5" />
                        )}

                        {testResult === 'error' && (
                            <XCircle className="text-red-500 w-5 h-5" />
                        )}
                        {jobs.length > 0 && (
    <div className="mt-4 border rounded-lg overflow-hidden">
        <table className="w-full text-sm">
            <thead className="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th className="p-2 text-left">Título</th>
                    <th className="p-2 text-left">Empresa</th>
                    <th className="p-2 text-left">Ubicación</th>
                </tr>
            </thead>
            <tbody>
                {jobs.map((job, i) => (
                    <tr key={i} className="border-t">
                        <td className="p-2">{job.title}</td>
                        <td className="p-2">{job.company}</td>
                        <td className="p-2">{job.location}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    </div>
)}
                    </div>

                    <div className="flex justify-end gap-3 pt-4">
                        <button type="button" onClick={onClose}>
                            Cancelar
                        </button>

                        <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
