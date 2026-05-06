import { useEffect, useState } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';

import {
    CheckCircle,
    XCircle,
    Settings
} from 'lucide-react';

type Source = {
    id?: number;
    name: string;
    api_url?: string;
    api_key?: string;
    app_id?: string;
};

type Props = {
    open: boolean;
    onClose: () => void;
    onSaved: () => void;
    source: Source | null;
};

export default function SourceModal({
    open,
    onClose,
    onSaved,
    source
}: Props) {

    const DEFAULT_API =
        'https://www.arbeitnow.com/api/job-board-api';

    const [form, setForm] = useState<Source>({
        name: '',
        api_url: DEFAULT_API,
        api_key: '',
        app_id: '',
    });

    const [testing, setTesting] = useState(false);

    const [testResult, setTestResult] = useState<
        'success' | 'error' | null
    >(null);

    const [showAdvanced, setShowAdvanced] =
        useState(false);

    const [jobs, setJobs] = useState<any[]>([]);

    useEffect(() => {

        if (source) {

            setForm({
                id: source.id,
                name: source.name || '',
                api_url:
                    source.api_url || DEFAULT_API,
                api_key: source.api_key || '',
                app_id: source.app_id || '',
            });

        } else {

            setForm({
                name: '',
                api_url: DEFAULT_API,
                api_key: '',
                app_id: '',
            });
        }

        setJobs([]);
        setTestResult(null);

    }, [source]);

    // 🔥 VALIDACIÓN
    const validateForm = () => {

        if (!form.name?.trim()) {

            Swal.fire({
                icon: 'warning',
                title: 'Campo requerido',
                text: 'El nombre es obligatorio'
            });

            return false;
        }

        return true;
    };

    // 🔥 GUARDAR
    const handleSubmit = async (e: any) => {

        e.preventDefault();

        if (!validateForm()) return;

        const confirm = await Swal.fire({
            title: form.id
                ? '¿Actualizar fuente?'
                : '¿Crear fuente?',
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

            const payload = {
                source: form.name,
                api_url: form.api_url || null,
                api_key: form.api_key || null,
                app_id: form.app_id || null,
            };

            if (form.id) {

                await axios.put(
                    `/sources/${form.id}`,
                    payload
                );

            } else {

                await axios.post(
                    `/sources`,
                    payload
                );
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

    // 🔥 TEST API
    const handleTest = async () => {

        if (!form.api_url) {

            Swal.fire({
                icon: 'warning',
                title: 'API URL requerida'
            });

            return;
        }

        setTesting(true);
        setTestResult(null);
        setJobs([]);

        try {

            const res = await axios.post(
                '/sources/test-api',
                {
                    api_url: form.api_url,
                    api_key: form.api_key,
                    app_id: form.app_id,
                }
            );

            const data = res.data.data || [];

            setJobs(data);

            setTestResult('success');

            Swal.fire({
                icon: 'success',
                title: 'API funcionando',
                text: `Se encontraron ${data.length} registros`
            });

        } catch (e: any) {

            setJobs([]);

            setTestResult('error');

            const message =
                e.response?.data?.message ||
                'Error al conectar';

            Swal.fire({
                icon: 'error',
                title: 'Conexión fallida',
                text: message
            });

        } finally {

            setTesting(false);
        }
    };

    if (!open) return null;

    return (
        <div
            className="
                fixed inset-0 z-50
                flex items-center justify-center
                bg-black/40 backdrop-blur-sm
            "
            onClick={onClose}
        >

            <div
                onClick={(e) => e.stopPropagation()}
                className="
                    w-full max-w-3xl
                    p-6
                    rounded-2xl shadow-2xl
                    bg-white dark:bg-gray-900
                    text-gray-800 dark:text-gray-100
                    max-h-[92vh]
                    overflow-auto
                "
            >

                {/* HEADER */}
                <div className="flex items-center justify-between mb-6">

                    <h2 className="text-xl font-semibold">
                        {form.id
                            ? 'Editar fuente'
                            : 'Nueva fuente'}
                    </h2>

                    <button
                        onClick={onClose}
                        className="
                            text-gray-500
                            hover:text-red-500
                        "
                    >
                        ✕
                    </button>

                </div>

                {/* FORM */}
                <form
                    onSubmit={handleSubmit}
                    className="space-y-5"
                >

                    {/* NOMBRE */}
                    <div>

                        <label className="text-sm font-medium">
                            Nombre *
                        </label>

                        <input
                            value={form.name || ''}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    name: e.target.value
                                })
                            }
                            placeholder="LinkedIn Jobs"
                            className="
                                w-full mt-1
                                px-3 py-2
                                rounded-xl border
                                bg-white dark:bg-gray-800
                                border-gray-200 dark:border-gray-700
                                outline-none
                                focus:ring-2 focus:ring-blue-500
                            "
                        />

                    </div>

                    {/* API URL */}
                    <div>

                        <label className="text-sm font-medium">
                            API URL
                        </label>

                        <input
                            value={form.api_url || ''}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    api_url: e.target.value
                                })
                            }
                            className="
                                w-full mt-1
                                px-3 py-2
                                rounded-xl border
                                bg-white dark:bg-gray-800
                                border-gray-200 dark:border-gray-700
                                outline-none
                                focus:ring-2 focus:ring-blue-500
                            "
                        />

                    </div>

                    {/* 🔥 CONFIG AVANZADA */}
                    <div
                        className="
                            border rounded-2xl
                            p-4
                            bg-gray-50 dark:bg-gray-800/30
                        "
                    >

                        <button
                            type="button"
                            onClick={() =>
                                setShowAdvanced(
                                    !showAdvanced
                                )
                            }
                            className="
                                w-full
                                flex items-center gap-2
                                text-sm font-medium
                            "
                        >

                            <Settings className="w-4 h-4" />

                            Configuración avanzada

                            <span className="ml-auto">
                                {showAdvanced
                                    ? '−'
                                    : '+'}
                            </span>

                        </button>

                        {showAdvanced && (

                            <div className="mt-5 space-y-4">

                                {/* APP ID */}
                                <div>

                                    <label className="text-sm">
                                        App ID
                                    </label>

                                    <input
                                        value={
                                            form.app_id || ''
                                        }
                                        onChange={(e) =>
                                            setForm({
                                                ...form,
                                                app_id:
                                                    e.target
                                                        .value
                                            })
                                        }
                                        className="
                                            w-full mt-1
                                            px-3 py-2
                                            rounded-xl border
                                            bg-white dark:bg-gray-800
                                            border-gray-200 dark:border-gray-700
                                            outline-none
                                            focus:ring-2 focus:ring-blue-500
                                        "
                                    />

                                </div>

                                {/* API KEY */}
                                <div>

                                    <label className="text-sm">
                                        API Key
                                    </label>

                                    <input
                                        value={
                                            form.api_key || ''
                                        }
                                        onChange={(e) =>
                                            setForm({
                                                ...form,
                                                api_key:
                                                    e.target
                                                        .value
                                            })
                                        }
                                        className="
                                            w-full mt-1
                                            px-3 py-2
                                            rounded-xl border
                                            bg-white dark:bg-gray-800
                                            border-gray-200 dark:border-gray-700
                                            outline-none
                                            focus:ring-2 focus:ring-blue-500
                                        "
                                    />

                                </div>

                            </div>
                        )}

                    </div>

                    {/* TEST */}
                    <div
                        className="
                            flex items-center
                            justify-between
                            gap-4
                        "
                    >

                        <button
                            type="button"
                            onClick={handleTest}
                            disabled={testing}
                            className="
                                px-5 py-2.5
                                rounded-xl
                                bg-gray-900
                                text-white
                                hover:bg-black
                                disabled:opacity-50
                                transition
                            "
                        >

                            {testing
                                ? 'Probando...'
                                : 'Test API'}

                        </button>

                        <div className="flex items-center">

                            {testResult ===
                                'success' && (
                                <CheckCircle
                                    className="
                                        text-green-500
                                        w-6 h-6
                                    "
                                />
                            )}

                            {testResult ===
                                'error' && (
                                <XCircle
                                    className="
                                        text-red-500
                                        w-6 h-6
                                    "
                                />
                            )}

                        </div>

                    </div>

                    {/* 🔥 TABLA */}
                    {jobs.length > 0 && (

                        <div
                            className="
                                mt-6
                                border rounded-2xl
                                overflow-hidden
                            "
                        >

                            <div
                                className="
                                    px-4 py-3
                                    bg-gray-100 dark:bg-gray-800
                                    font-medium
                                "
                            >
                                Datos encontrados
                            </div>

                            <div
                                className="
                                    overflow-auto
                                    max-h-[350px]
                                "
                            >

                                <table
                                    className="
                                        w-full text-sm
                                    "
                                >

                                    <thead
                                        className="
                                            bg-gray-50
                                            dark:bg-gray-900
                                            border-b
                                        "
                                    >

                                        <tr>

                                            <th
                                                className="
                                                    p-3 text-left
                                                "
                                            >
                                                Título
                                            </th>

                                            <th
                                                className="
                                                    p-3 text-left
                                                "
                                            >
                                                Empresa
                                            </th>

                                            <th
                                                className="
                                                    p-3 text-left
                                                "
                                            >
                                                Ubicación
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        {jobs.map(
                                            (
                                                job: any,
                                                i: number
                                            ) => (

                                                <tr
                                                    key={i}
                                                    className="
                                                        border-b
                                                    "
                                                >

                                                    <td
                                                        className="
                                                            p-3
                                                        "
                                                    >
                                                        {job.title ||
                                                            'N/A'}
                                                    </td>

                                                    <td
                                                        className="
                                                            p-3
                                                        "
                                                    >
                                                        {job.company_name ||
                                                            job.company ||
                                                            'N/A'}
                                                    </td>

                                                    <td
                                                        className="
                                                            p-3
                                                        "
                                                    >
                                                        {job.location ||
                                                            job.candidate_required_location ||
                                                            'Remote'}
                                                    </td>

                                                </tr>
                                            )
                                        )}

                                    </tbody>

                                </table>

                            </div>

                        </div>
                    )}

                    {/* FOOTER */}
                    <div
                        className="
                            flex justify-end
                            gap-3
                            pt-6
                        "
                    >

                        <button
                            type="button"
                            onClick={onClose}
                            className="
                                px-4 py-2
                                rounded-xl
                                border
                            "
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            className="
                                bg-blue-600
                                text-white
                                px-5 py-2
                                rounded-xl
                                hover:bg-blue-700
                            "
                        >
                            Guardar
                        </button>

                    </div>

                </form>

            </div>

        </div>
    );
}