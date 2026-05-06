import {
    CheckCircle,
    XCircle,
    Clock,
    Database,
    AlertTriangle,
    Link2
} from 'lucide-react';

type Props = {
    open: boolean;
    onClose: () => void;
    source: any;
};

export default function SourceDetailsModal({
    open,
    onClose,
    source
}: Props) {

    if (!open || !source) return null;

    const statusColor = {
        success: 'text-green-500',
        failed: 'text-red-500',
        running: 'text-yellow-500',
        pending: 'text-gray-500',
    };

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
                    w-full max-w-5xl
                    max-h-[92vh]
                    overflow-auto
                    rounded-3xl
                    bg-white dark:bg-gray-900
                    shadow-2xl
                    p-6
                "
            >

                {/* HEADER */}
                <div className="flex justify-between items-center mb-8">

                    <div>

                        <h2 className="text-2xl font-bold">
                            {source.source}
                        </h2>

                        <p className="text-sm text-gray-500">
                            Source #{source.id}
                        </p>

                    </div>

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

                {/* GRID */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">

                    {/* STATUS */}
                    <div className="rounded-2xl border p-5">

                        <div className="flex items-center gap-2 mb-4">

                            <CheckCircle
                                className={
                                    statusColor[
                                        source.last_status
                                    ] || ''
                                }
                            />

                            <h3 className="font-semibold">
                                Estado
                            </h3>

                        </div>

                        <div className="space-y-2 text-sm">

                            <div>
                                Último estado:
                                <strong className="ml-2">
                                    {source.last_status}
                                </strong>
                            </div>

                            <div>
                                Conexión:
                                <strong className="ml-2">
                                    {source.connection_status}
                                </strong>
                            </div>

                            <div>
                                Uptime:
                                <strong className="ml-2">
                                    {source.uptime}%
                                </strong>
                            </div>

                        </div>

                    </div>

                    {/* API */}
                    <div className="rounded-2xl border p-5">

                        <div className="flex items-center gap-2 mb-4">

                            <Link2 className="text-blue-500" />

                            <h3 className="font-semibold">
                                API
                            </h3>

                        </div>

                        <div className="space-y-3 text-sm">

                            <div>
                                <span className="font-medium">
                                    URL:
                                </span>

                                <div
                                    className="
                                        mt-1
                                        break-all
                                        text-blue-500
                                    "
                                >
                                    {source.api_url || '-'}
                                </div>
                            </div>

                            <div>
                                <span className="font-medium">
                                    App ID:
                                </span>

                                <div className="mt-1">
                                    {source.app_id || '-'}
                                </div>
                            </div>

                            <div>
                                <span className="font-medium">
                                    API Key:
                                </span>

                                <div className="mt-1">
                                    {source.api_key
                                        ? '••••••••••'
                                        : '-'}
                                </div>
                            </div>

                        </div>

                    </div>

                    {/* MÉTRICAS */}
                    <div className="rounded-2xl border p-5">

                        <div className="flex items-center gap-2 mb-4">

                            <Database className="text-purple-500" />

                            <h3 className="font-semibold">
                                Métricas
                            </h3>

                        </div>

                        <div className="grid grid-cols-2 gap-4 text-sm">

                            <div>
                                <div className="text-gray-500">
                                    Total encontrados
                                </div>

                                <div className="text-xl font-bold">
                                    {source.total_records_found}
                                </div>
                            </div>

                            <div>
                                <div className="text-gray-500">
                                    Total insertados
                                </div>

                                <div className="text-xl font-bold">
                                    {source.total_records_inserted}
                                </div>
                            </div>

                            <div>
                                <div className="text-gray-500">
                                    Última corrida
                                </div>

                                <div className="text-xl font-bold">
                                    {source.last_records_inserted}
                                </div>
                            </div>

                            <div>
                                <div className="text-gray-500">
                                    Skipped
                                </div>

                                <div className="text-xl font-bold">
                                    {source.total_records_skipped}
                                </div>
                            </div>

                        </div>

                    </div>

                    {/* FECHAS */}
                    <div className="rounded-2xl border p-5">

                        <div className="flex items-center gap-2 mb-4">

                            <Clock className="text-orange-500" />

                            <h3 className="font-semibold">
                                Fechas
                            </h3>

                        </div>

                        <div className="space-y-3 text-sm">

                            <div>
                                <span className="font-medium">
                                    Creado:
                                </span>

                                <div className="mt-1">
                                    {source.created_at || '-'}
                                </div>
                            </div>

                            <div>
                                <span className="font-medium">
                                    Último inicio:
                                </span>

                                <div className="mt-1">
                                    {source.last_started_at || '-'}
                                </div>
                            </div>

                            <div>
                                <span className="font-medium">
                                    Última finalización:
                                </span>

                                <div className="mt-1">
                                    {source.last_finished_at || '-'}
                                </div>
                            </div>

                            <div>
                                <span className="font-medium">
                                    Último éxito:
                                </span>

                                <div className="mt-1">
                                    {source.last_success_at || '-'}
                                </div>
                            </div>

                            <div>
                                <span className="font-medium">
                                    Último fallo:
                                </span>

                                <div className="mt-1">
                                    {source.last_failed_at || '-'}
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                {/* ERROR */}
                {source.last_error && (

                    <div
                        className="
                            mt-5
                            rounded-2xl
                            border border-red-300
                            bg-red-50 dark:bg-red-900/20
                            p-5
                        "
                    >

                        <div className="flex items-center gap-2 mb-3">

                            <AlertTriangle className="text-red-500" />

                            <h3 className="font-semibold text-red-500">
                                Último error
                            </h3>

                        </div>

                        <pre
                            className="
                                text-sm
                                whitespace-pre-wrap
                                break-words
                            "
                        >
                            {source.last_error}
                        </pre>

                    </div>
                )}

                {/* FOOTER */}
                <div className="flex justify-end mt-8">

                    <button
                        onClick={onClose}
                        className="
                            px-5 py-2
                            rounded-xl
                            bg-gray-900
                            text-white
                            hover:bg-black
                        "
                    >
                        Cerrar
                    </button>

                </div>

            </div>

        </div>
    );
}