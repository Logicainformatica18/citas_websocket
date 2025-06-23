import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import axios from 'axios';
import React from 'react';

interface AreaModalProps {
    open: boolean;
    onClose: () => void;
    supportId: number;
    detail: {
        id: number;
        area?: { id_area: number };
        motivo_cita?: { id: number };
        internal_state?: { id: number };
    } | null;
    areas: { id_area: number; descripcion: string }[];
    motives: { id: number; nombre_motivo: string }[];
    internalStates: { id: number; description: string }[];
    onUpdated: (updatedDetail: any) => void;
}

export default function AreaModal({
    open,
    onClose,
    supportId,
    detail,
    areas,
    motives,
    internalStates,
    onUpdated,
}: AreaModalProps) {
    const [selectedArea, setSelectedArea] = useState<number | ''>('');
    const [selectedMotive, setSelectedMotive] = useState<number | ''>('');
    const [selectedInternalState, setSelectedInternalState] = useState<number | ''>('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
          console.log('🧪 ID del detalle recibido:', detail?.id);
        if (detail) {
            setSelectedArea(detail.area?.id_area || '');
            setSelectedMotive(detail.motivo_cita?.id || '');
            setSelectedInternalState(detail.internal_state?.id || '');
        }
    }, [detail]);

    const handleSave = async () => {
        if (!detail?.id) {
            toast.error('No se encontró el ID del detalle');
            return;
        }

        if (!selectedArea || !selectedMotive || !selectedInternalState) {
            toast.warning('Debe completar los tres campos');
            return;
        }

        setLoading(true);
        try {
            const response = await axios.put(`/support-details/${detail.id}/area-motivo`, {
                support_id: supportId,
                area_id: selectedArea,
                id_motivos_cita: selectedMotive,
                internal_state_id: selectedInternalState,
            });

            toast.success('Detalle actualizado correctamente ✅');
            onUpdated(response.data);
            onClose();
        } catch (error) {
            console.error(error);
            toast.error('Error al actualizar el detalle ❌');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="sm:max-w-md bg-white z-[9999] border shadow-xl">
                <DialogHeader>
                    <DialogTitle>
                        Editar Área, Motivo y Estado Interno{' '}
                        {detail?.id ? `- ID: ${detail.id}` : ''}

                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    {/* ⚠️ Indicador temporal para verificar render */}
                    <div className="text-sm text-blue-600">El modal está visible (debug)</div>

                    <div>
                        <Label className="block mb-1">Área</Label>
                        <select
                            value={selectedArea}
                            onChange={(e) => setSelectedArea(Number(e.target.value))}
                            className="w-full border rounded px-3 py-2 text-sm"
                        >
                            <option value="">Seleccione un área</option>
                            {areas.map((a) => (
                                <option key={a.id_area} value={a.id_area}>
                                    {a.descripcion}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <Label className="block mb-1">Motivo de Cita</Label>
                        <select
                            value={selectedMotive}
                            onChange={(e) => setSelectedMotive(Number(e.target.value))}
                            className="w-full border rounded px-3 py-2 text-sm"
                        >
                            <option value="">Seleccione un motivo</option>
                            {motives.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.nombre_motivo}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <Label className="block mb-1">Estado Interno</Label>
                        <select
                            value={selectedInternalState}
                            onChange={(e) => setSelectedInternalState(Number(e.target.value))}
                            className="w-full border rounded px-3 py-2 text-sm"
                        >
                            <option value="">Seleccione un estado interno</option>
                            {internalStates.length > 0 ? (
                                internalStates.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.description}
                                    </option>
                                ))
                            ) : (
                                <option value="" disabled>
                                    No hay estados disponibles
                                </option>
                            )}
                        </select>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button onClick={handleSave} disabled={loading}>
                        {loading ? 'Guardando...' : 'Guardar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
