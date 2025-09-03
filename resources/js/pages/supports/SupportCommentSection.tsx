import { useEffect, useState } from 'react';
import axios from 'axios';
import { Textarea } from '@/components/ui/Textarea';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';
import { ChevronDown, ChevronUp } from 'lucide-react';

interface Comment {
    id: number;
    comment: string;
    user: {
        names: string;
        roles: { name: string }[];
    };
    created_at: string;
    internal_state?: {
        id: number;
        description: string;
    };
}
interface SupportCommentSectionProps {
    supportDetailId: number;
    state?: number; // opcional, si a veces no lo usas
}
interface InternalState {
    id: number;
    description: string;
}

const MAX_CHARACTERS = 400;

const getInternalStateBadgeClass = (description: string) => {
    switch (description) {
        case 'Atendido':
            return 'bg-green-100 text-green-800';
        case 'Pendiente':
            return 'bg-blue-100 text-blue-800';
        case 'Observado':
            return 'bg-yellow-100 text-yellow-800';
        case 'Cerrado':
            return 'bg-gray-200 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-600';
    }
};

export default function SupportCommentSection({
    supportDetailId,
    state,
}: SupportCommentSectionProps) {
    const [commentText, setCommentText] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [comments, setComments] = useState<Comment[]>([]);
    const [internalStates, setInternalStates] = useState<InternalState[]>([]);
    const [selectedInternalStateId, setSelectedInternalStateId] = useState<number | ''>('');
    const [loading, setLoading] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const [initialLoaded, setInitialLoaded] = useState(false);

    const formatLimaDateTime = (isoDate: string) => {
        const date = new Date(isoDate);
        return date.toLocaleString('es-PE', {
            timeZone: 'America/Lima',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const fetchComments = async () => {
        try {
            const res = await axios.get(`/support-details/${supportDetailId}/comments`);
            setComments(res.data.comments);
            setInternalStates(res.data.internal_states);
            setInitialLoaded(true);
        } catch {
            toast.error('❌ Error al cargar datos');
        }
    };

    const handleSubmit = async () => {
        if (!commentText.trim() || commentText.length > MAX_CHARACTERS) return;

        try {
            setLoading(true);
            const data = new FormData();
            data.append('support_detail_id', String(supportDetailId));
            data.append('comment', commentText);
            if (selectedInternalStateId !== '') {
                data.append('internal_state_id', String(selectedInternalStateId));
            }
            if (file) {
                data.append('file_1', file); // ✅ Aquí va como lo espera tu backend
            }

            await axios.post(`/support-details/${supportDetailId}/comments`, data, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            setCommentText('');
            setSelectedInternalStateId('');
            setFile(null);
            await fetchComments();
            toast.success('✅ Seguimiento agregado');
        } catch (error: any) {
            console.error(error.response?.data);
            toast.error('❌ Error al enviar Seguimiento');
        } finally {
            setLoading(false);
        }
    };

    const toggleExpanded = () => {
        setExpanded((prev) => !prev);
        if (!initialLoaded) fetchComments();
    };

    return (
        <div className="mt-4 border border-blue-300 bg-blue-50 dark:bg-blue-950 rounded-lg p-4 w-full">
            <button
                onClick={toggleExpanded}
                className="flex items-center gap-2 text-sm font-semibold text-blue-600 hover:underline"
            >
                {expanded ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                {expanded ? 'Ocultar Seguimiento de atención' : 'Mostrar Seguimiento de atención'}
            </button>

            {expanded && (
                <div className="mt-4 space-y-4">
                    <div className="space-y-2">
                        <select
                            className="w-full rounded-md border border-gray-300 p-2 text-sm text-black bg-white focus:ring-2 focus:ring-blue-500"
                            value={selectedInternalStateId}
                            onChange={(e) => {
                                const value = e.target.value;
                                setSelectedInternalStateId(value === '' ? '' : parseInt(value));
                            }}
                        >
                            <option value="">Selecciona un estado interno</option>
                            {internalStates.map((state) => (
                                <option key={state.id} value={state.id}>
                                    {state.description}
                                </option>
                            ))}
                        </select>

                        <input
                            type="file"
                            onChange={(e) => setFile(e.target.files?.[0] || null)}
                            className="w-full text-sm text-black bg-white border border-gray-300 rounded-md p-2"
                        />

                        <Textarea
                            placeholder="Escribe un comentario..."
                            value={commentText}
                            onChange={(e) => setCommentText(e.target.value)}
                            maxLength={MAX_CHARACTERS}
                            className="min-h-[80px] w-full text-black"
                        />

                        <div className="flex justify-between text-xs text-gray-600">
                            <span>
                                {commentText.length}/{MAX_CHARACTERS} caracteres
                            </span>
                            {commentText.length > MAX_CHARACTERS && (
                                <span className="text-red-500">⚠ Demasiados caracteres</span>
                            )}
                        </div>

                     <Button
  className="w-full mt-2"
  onClick={handleSubmit}
  disabled={
    loading ||
    !commentText.trim() ||
    commentText.length > MAX_CHARACTERS ||
    state === 5 // 🚨 Ejemplo: desactiva si ticket está cerrado
  }
>
  {loading ? 'Enviando...' : 'Enviar comentario'}
</Button>

                    </div>

                    <div className="space-y-3 border-t pt-4">
                        {comments.length === 0 ? (
                            <p className="text-sm text-gray-400 italic">Sin seguimiento.</p>
                        ) : (
                            comments.map((c) => (
                                <div
                                    key={c.id}
                                    className="border p-3 rounded-md bg-white dark:bg-zinc-800 shadow-sm"
                                >
                                    <p className="text-sm text-black dark:text-gray-100">{c.comment}</p>

{(c as any).file_1 && (
    <a
        href={`/uploads/${(c as any).file_1}`}
        target="_blank"
        rel="noopener noreferrer"
        className="mt-2 block text-blue-600 underline text-sm"
    >
        📎 Ver archivo adjunto
    </a>
)}


                                    {c.internal_state?.description && (
                                        <span className="inline-block mt-2 rounded-full p-[2px] bg-gradient-to-r from-red-500 to-red-700">
                                            <span
                                                className={`block px-2 py-0.5 text-xs font-semibold rounded-full ${getInternalStateBadgeClass(
                                                    c.internal_state.description
                                                )} bg-white dark:bg-zinc-800`}
                                            >
                                                {c.internal_state.description}
                                            </span>
                                        </span>
                                    )}

                                    <p className="text-xs text-gray-500 mt-1 font-medium">
                                        {c.user?.roles?.[0]?.name ?? 'Sin rol'} — {c.user?.names}
                                        <span className="mx-2">•</span>
                                        {formatLimaDateTime(c.created_at)}
                                    </p>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
