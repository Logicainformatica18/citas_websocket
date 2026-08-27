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

import { Loader2, Tag, AlignLeft } from 'lucide-react';

export default function CategoryModal({
    open,
    onClose,
    onSaved,
    categoryToEdit,
}: {
    open: boolean;
    onClose: () => void;
    onSaved: (category: any) => void;
    categoryToEdit?: any;
}) {
    const [formData, setFormData] = useState({
        description: '',
        detail: '',
    });

    const [uploading, setUploading] = useState(false);

    useEffect(() => {
        if (categoryToEdit) {
            setFormData({
                description: categoryToEdit.description || '',
                detail: categoryToEdit.detail || '',
            });
        } else {
            handleReset();
        }
    }, [categoryToEdit]);

    const handleChange = (e: any) => {
        const { name, value } = e.target;

        setFormData({
            ...formData,
            [name]: value,
        });
    };

    const handleSubmit = async () => {
        try {
            setUploading(true);

            const data = new FormData();

            Object.entries(formData).forEach(([k, v]) => {
                data.append(k, v as string);
            });

            const url = categoryToEdit ? `/categories/${categoryToEdit.id}` : '/categories';

            if (categoryToEdit) {
                data.append('_method', 'PUT');
            }

            const res = await axios.post(url, data);

            toast.success(categoryToEdit ? 'Categoría actualizada' : 'Categoría creada');

            onSaved(res.data.category);

            handleReset();
            onClose();
        } catch (err: any) {
            const errores = err?.response?.data?.errors;

            if (errores) {
                toast.error(Object.values(errores).flat().join(' '));
            } else {
                toast.error('Error al guardar');
            }
        } finally {
            setUploading(false);
        }
    };

    const handleReset = () => {
        setFormData({
            description: '',
            detail: '',
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {categoryToEdit ? 'Editar categoría' : 'Nueva categoría'}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <div>
                        <Label className="flex items-center gap-2">
                            <Tag size={16} /> Descripción *
                        </Label>

                        <Input
                            name="description"
                            value={formData.description}
                            onChange={handleChange}
                            maxLength={255}
                        />
                    </div>

                    <div>
                        <Label className="flex items-center gap-2">
                            <AlignLeft size={16} /> Detalle
                        </Label>

                        <Input
                            name="detail"
                            value={formData.detail}
                            onChange={handleChange}
                            maxLength={255}
                        />
                    </div>
                </div>

                <DialogFooter className="flex justify-between">
                    <Button
                        variant="outline"
                        onClick={handleReset}
                        disabled={uploading}
                    >
                        Limpiar
                    </Button>

                    <Button onClick={handleSubmit} disabled={uploading}>
                        {uploading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}

                        {categoryToEdit ? 'Actualizar' : 'Guardar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
