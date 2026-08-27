import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import axios from 'axios';
import { toast } from 'sonner';

import { Loader2, Tag, AlignLeft, ListFilter } from 'lucide-react';

export default function SelectionModal({
    open,
    onClose,
    onSaved,
    selectionToEdit,
    availableSelections,
}: {
    open: boolean;
    onClose: () => void;
    onSaved: (selection: any) => void;
    selectionToEdit?: any;
    availableSelections?: Array<{ id: number; description: string }>;
}) {
    const [formData, setFormData] = useState({
        description: '',
        detail: '',
        state: '',
        associate_id: '',
    });

    const [uploading, setUploading] = useState(false);

    useEffect(() => {
        if (selectionToEdit) {
            setFormData({
                description: selectionToEdit.description || '',
                detail: selectionToEdit.detail || '',
                state: selectionToEdit.state || '',
                associate_id: selectionToEdit.associate_id ? String(selectionToEdit.associate_id) : '',
            });
        } else {
            handleReset();
        }
    }, [selectionToEdit]);

    const handleChange = (e: any) => {
        const { name, value } = e.target;
        setFormData({ ...formData, [name]: value });
    };

    const handleSubmit = async () => {
        try {
            setUploading(true);

            const data = new FormData();
            Object.entries(formData).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) {
                    data.append(k, String(v));
                }
            });

            const url = selectionToEdit ? `/selections/${selectionToEdit.id}` : '/selections';
            if (selectionToEdit) {
                data.append('_method', 'PUT');
            }

            const res = await axios.post(url, data);

            toast.success(selectionToEdit ? 'Selección actualizada' : 'Selección creada');
            onSaved(res.data.selection);
            handleReset();
            onClose();
        } catch (err: any) {
            const errores = err?.response?.data?.errors;
            if (errores) {
                toast.error(Object.values(errores).flat().join(' '));
            } else {
                toast.error(err?.response?.data?.message || 'Error al guardar');
            }
        } finally {
            setUploading(false);
        }
    };

    const handleReset = () => {
        setFormData({
            description: '',
            detail: '',
            state: '',
            associate_id: '',
        });
    };

    const selectableSelections = (availableSelections || []).filter(
        (item) => !selectionToEdit || item.id !== selectionToEdit.id
    );

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {selectionToEdit ? 'Editar selección' : 'Nueva selección'}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <div>
                        <Label className="flex items-center gap-2"><Tag size={16} /> Descripción *</Label>
                        <Input name="description" value={formData.description} onChange={handleChange} maxLength={255} />
                    </div>

                    <div>
                        <Label className="flex items-center gap-2"><AlignLeft size={16} /> Detalle</Label>
                        <Input name="detail" value={formData.detail} onChange={handleChange} maxLength={255} />
                    </div>

                    <div>
                        <Label className="flex items-center gap-2"><ListFilter size={16} /> Estado</Label>
                        <Input name="state" value={formData.state} onChange={handleChange} maxLength={255} />
                    </div>

                    <div>
                        <Label className="flex items-center gap-2"><ListFilter size={16} /> Selección asociada</Label>
                        <select
                            name="associate_id"
                            value={formData.associate_id}
                            onChange={handleChange}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value="">Ninguna</option>
                            {selectableSelections.map((item) => (
                                <option key={item.id} value={item.id}>{item.description}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <DialogFooter className="flex justify-between">
                    <Button variant="outline" onClick={handleReset} disabled={uploading}>Limpiar</Button>
                    <Button onClick={handleSubmit} disabled={uploading}>
                        {uploading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />} 
                        {selectionToEdit ? 'Actualizar' : 'Guardar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
