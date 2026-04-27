import { useEffect, useState } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import { CheckCircle, XCircle, Loader2 } from 'lucide-react';

type Source = {
    id?: number;
    name: string;
    base_url?: string;
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
                base_url: form.base_url || null,
                api_url: form.api_url || null,
                api_key: form.api_key || null,
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
                title: 'Probando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            await axios.post('/sources/test-api', {
                api_url: form.api_url,
                api_key: form.api_key,
            });

            Swal.fire({
                icon: 'success',
                title: 'Conexión OK'
            });

            setTestResult('success');

        } catch (e: any) {

            Swal.fire({
                icon: 'error',
                title: 'Error de conexión'
            });

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
                            value={form.name}
                            onChange={(e) =>
                                setForm({ ...form, name: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border"
                        />
                    </div>

                    {/* OPCIONAL */}
                    <div>
                        <label className="text-sm">Base URL (opcional)</label>
                        <input
                            value={form.base_url}
                            onChange={(e) =>
                                setForm({ ...form, base_url: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border"
                        />
                    </div>

                    {/* OPCIONAL */}
                    <div>
                        <label className="text-sm">API URL (opcional)</label>
                        <input
                            value={form.api_url}
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
                            value={form.api_key}
                            onChange={(e) =>
                                setForm({ ...form, api_key: e.target.value })
                            }
                            className="w-full mt-1 px-3 py-2 rounded-lg border"
                        />
                    </div>

                    {/* TEST */}
                    <div className="flex items-center justify-between pt-2">
                        <button
                            type="button"
                            onClick={handleTest}
                            disabled={testing}
                            className="px-4 py-2 rounded-lg bg-gray-200 flex items-center gap-2"
                        >
                            {testing && <Loader2 className="w-4 h-4 animate-spin" />}
                            Test API
                        </button>

                        {testResult === 'success' && (
                            <CheckCircle className="text-green-500 w-5 h-5" />
                        )}

                        {testResult === 'error' && (
                            <XCircle className="text-red-500 w-5 h-5" />
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
