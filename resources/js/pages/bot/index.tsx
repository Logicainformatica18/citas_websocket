import axios from 'axios';
import AppLayout from '@/layouts/app-layout';
import { useState, useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

interface ImageAnalysis {
    id?: number;
    filename: string;
    response: string;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

export default function BotIndex({ analyses: initialPagination }: { analyses: Pagination<ImageAnalysis> }) {
    const [analyses, setAnalyses] = useState<ImageAnalysis[]>(initialPagination.data);
    const [pagination, setPagination] = useState(initialPagination);
    const [files, setFiles] = useState<File[]>([]);
    const [loading, setLoading] = useState(false);
    const [analyzedFilenames, setAnalyzedFilenames] = useState<Set<string>>(new Set());

    const fileInputRef = useRef<HTMLInputElement>(null);

    // 🚀 Cargar filenames analizados al montar
    useEffect(() => {
        axios.get('/analyses/filenames').then(res => {
            setAnalyzedFilenames(new Set(res.data));
        });
    }, []);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            setFiles(Array.from(e.target.files));
        }
    };

    const handleRemoveImage = (index: number) => {
        const newFiles = files.filter((_, i) => i !== index);
        setFiles(newFiles);
        if (newFiles.length === 0 && fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

   const handleUpload = async () => {
    if (files.length === 0) return;
    setLoading(true);

    const formData = new FormData();
    const validFiles = files.filter(file => !analyzedFilenames.has(file.name));

    if (validFiles.length === 0) {
        alert('No hay archivos nuevos para analizar.');
        setLoading(false);
        return;
    }

    validFiles.forEach((file) => formData.append('images[]', file));

    try {
        const res = await axios.post('/analyze-images', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const analyzed: ImageAnalysis[] = res.data.data || [];

        await axios.post('/analyses', { items: analyzed });

        const refreshed = await axios.get('/analyses/fetch');
        setAnalyses(refreshed.data.data);
        setPagination(refreshed.data);
        setFiles([]); // Limpia el input solo si todo salió bien
        fileInputRef.current!.value = '';

        // ⚠️ Actualiza lista de archivos analizados
        const updatedFilenames = await axios.get('/analyses/filenames');
        setAnalyzedFilenames(new Set(updatedFilenames.data));

    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {};
            const errorMessages = Object.values(validationErrors).flat();
            const errorFileNames = Object.keys(validationErrors);

            // Filtra los archivos válidos que sí se pueden volver a subir
            const remainingFiles = files.filter(
                (file) => !errorFileNames.includes(`images.${files.indexOf(file)}`)
            );
            setFiles(remainingFiles);

            if (errorMessages.length === files.length) {
                alert('Ninguna imagen fue válida. Revisa los formatos o nombres.');
            } else {
                alert('Algunas imágenes no fueron válidas y se eliminaron de la lista.');
            }

        } else {
            console.error('Error al analizar o guardar imágenes:', error);
            alert('Ocurrió un error inesperado. Revisa la consola.');
        }
    } finally {
        setLoading(false);
    }
};


    const fetchPage = async (url: string) => {
        try {
            const res = await axios.get(url);
            setAnalyses(res.data.data);
            setPagination(res.data);
        } catch (e) {
            console.error('Error al cargar la página', e);
        }
    };

    const handleDelete = async (id: number) => {
        if (confirm('¿Seguro que deseas eliminar este registro?')) {
            try {
                await axios.delete(`/analyses/${id}`);
                setAnalyses((prev) => prev.filter((a) => a.id !== id));

                // Actualizar lista de filenames
                const updated = await axios.get('/analyses/filenames');
                setAnalyzedFilenames(new Set(updated.data));
            } catch (e) {
                console.error('Error al eliminar', e);
            }
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Bot', href: '/bot' }]}>
            <Head title="Análisis de Imágenes" />

            <div className="p-8">
                <h1 className="text-2xl font-bold mb-6">Análisis de Imágenes con OpenAI</h1>

                <div className="mb-4">
                    <input
                        type="file"
                        multiple
                        accept="image/*"
                        ref={fileInputRef}
                        onChange={handleFileChange}
                        className="mb-2"
                    />

                    <div className="flex flex-wrap gap-4">
                        {files.map((file, index) => {
                            const alreadyAnalyzed = analyzedFilenames.has(file.name);
                            const preview = URL.createObjectURL(file);

                            return (
                                <div
                                    key={index}
                                    className={`relative w-32 h-32 border rounded overflow-hidden ${alreadyAnalyzed ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                >
                                    <img
                                        src={preview}
                                        alt={`preview-${index}`}
                                        className="object-cover w-full h-full opacity-90"
                                    />
                                    {alreadyAnalyzed && (
                                        <div className="absolute top-0 left-0 w-full text-center text-xs text-white bg-red-600 bg-opacity-75">
                                            Ya analizado
                                        </div>
                                    )}
                                    <button
                                        onClick={() => handleRemoveImage(index)}
                                        className="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                        title="Eliminar"
                                    >
                                        ×
                                    </button>
                                </div>
                            );
                        })}
                    </div>

                    <button
                        onClick={handleUpload}
                        disabled={loading || files.length === 0}
                        className="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        {loading ? 'Analizando...' : 'Analizar Imágenes'}
                    </button>

                    <div className="mt-8 overflow-x-auto">
                        <table className="min-w-full table-auto border border-gray-300 text-sm">
                            <thead className="bg-gray-100 text-left">
                                <tr>
                                    <th className="border px-4 py-2">#</th>
                                    <th className="border px-4 py-2">Imagen</th>
                                    <th className="border px-4 py-2">Respuesta</th>
                                    <th className="border px-4 py-2">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {analyses.map((item, index) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="border px-4 py-2">{index + 1}</td>
                                        <td className="border px-4 py-2 text-xs">{item.filename}</td>
                                        <td className="border px-4 py-2 whitespace-pre-wrap text-sm max-w-md">
                                            {item.response || 'Sin respuesta'}
                                        </td>
                                        <td className="border px-4 py-2">
                                            <button
                                                onClick={() => handleDelete(item.id!)}
                                                className="flex items-center gap-1 text-red-600 hover:underline"
                                            >
                                                <Trash2 className="w-4 h-4" /> Eliminar
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex justify-center mt-6 space-x-2">
                        {[...Array(pagination.last_page)].map((_, index) => {
                            const page = index + 1;
                            return (
                                <button
                                    key={page}
                                    onClick={() => fetchPage(`/analyses/fetch?page=${page}`)}
                                    className={`px-3 py-1 rounded text-sm font-medium transition ${pagination.current_page === page
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                                        }`}
                                >
                                    {page}
                                </button>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
