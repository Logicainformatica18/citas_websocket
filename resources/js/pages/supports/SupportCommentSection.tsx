import { useEffect, useState } from 'react';
import axios from 'axios';
import { Textarea } from '@/components/ui/textarea';
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

interface InternalState {
    id: number;
    description: string;
}

const MAX_CHARACTERS = 400;

export default function SupportCommentSection({ supportDetailId }: { supportDetailId: number }) {
    const [commentText, setCommentText] = useState('');
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
            toast.error('❌ Error al cargar comentarios');
        }
    };

    const handleSubmit = async () => {
        if (!commentText.trim() || commentText.length > MAX_CHARACTERS) return;

        try {
            setLoading(true);
            await axios.post(`/support-details/${supportDetailId}/comments`, {
                support_detail_id: supportDetailId,
                comment: commentText,
                internal_state_id:
                    selectedInternalStateId === '' || Number.isNaN(selectedInternalStateId)
                        ? null
                        : selectedInternalStateId,
            });
            setCommentText('');
            setSelectedInternalStateId('');
            await fetchComments();
            toast.success('✅ Comentario agregado');
        } catch (error: any) {
            console.error(error.response?.data);
            toast.error('❌ Error al enviar comentario');
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
                {expanded ? 'Ocultar comentarios' : 'Mostrar comentarios'}
            </button>

            {expanded && (
                <div className="mt-4 space-y-4">
                    <div>
                        <Textarea
                            placeholder="Escribe un comentario..."
                            value={commentText}
                            onChange={(e) => setCommentText(e.target.value)}
                            maxLength={MAX_CHARACTERS}
                            className="min-h-[80px] w-full text-black"
                        />

                        <select
                            className="w-full mt-2 rounded-md border border-gray-300 p-2 text-sm text-black bg-white focus:ring-2 focus:ring-blue-500"
                            value={selectedInternalStateId}
                            onChange={(e) => {
                                const value = e.target.value;
                                setSelectedInternalStateId(value === '' ? '' : parseInt(value));
                            }}
                        >
                            <option value="">Selecciona un estado interno (opcional)</option>
                            {internalStates.map((state) => (
                                <option key={state.id} value={state.id}>
                                    {state.description}
                                </option>
                            ))}
                        </select>

                        <div className="flex justify-between mt-1 text-xs text-gray-600">
                            <span>
                                {commentText.length}/{MAX_CHARACTERS} caracteres
                            </span>
                            {commentText.length > MAX_CHARACTERS && (
                                <span className="text-red-500">⚠ Demasiados caracteres</span>
                            )}
                        </div>

                        <div className="flex justify-end mt-2">
                            <Button
                                onClick={handleSubmit}
                                disabled={
                                    loading ||
                                    !commentText.trim() ||
                                    commentText.length > MAX_CHARACTERS
                                }
                            >
                                {loading ? 'Enviando...' : 'Enviar'}
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-3 border-t pt-4">
                        {comments.length === 0 ? (
                            <p className="text-sm text-gray-400 italic">No hay comentarios aún.</p>
                        ) : (
                            comments.map((c) => (
                                <div
                                    key={c.id}
                                    className="border p-3 rounded-md bg-white dark:bg-zinc-800 shadow-sm"
                                >
                                    <p className="text-sm text-black dark:text-gray-100">{c.comment}</p>
                                    {c.internal_state && (
                                        <p className="text-xs text-blue-700 font-semibold mt-1">
                                            Estado interno: {c.internal_state.description}
                                        </p>
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
