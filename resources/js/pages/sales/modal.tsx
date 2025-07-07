// resources/js/pages/sales/modal.tsx
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import axios from 'axios';
import { Loader2 } from 'lucide-react';
import ClientSearch from '../supports/clientSearch'; // ajusta la ruta si es diferente

interface Props {
    open: boolean;
    onClose: () => void;
    onSaved: (sale: any) => void;
    itemToEdit?: any;
}

export default function SaleModal({ open, onClose, onSaved, itemToEdit }: Props) {
    const [form, setForm] = useState({
        id: undefined,
        code: '',
        holder: '',
        stage: '',
        mz_lote: '',
        state: '',
        id_cliente: null, // nuevo campo
    });

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [clientQuery, setClientQuery] = useState('');
    const [selectedClient, setSelectedClient] = useState<any>(null);

    useEffect(() => {
        if (itemToEdit) {
            setForm({
                id: itemToEdit.id,
                code: itemToEdit.code || '',
                holder: itemToEdit.holder || '',
                stage: itemToEdit.stage || '',
                mz_lote: itemToEdit.mz_lote || '',
                state: itemToEdit.state || '',
                id_cliente: itemToEdit.cliente ? itemToEdit.cliente.id : null, // nuevo campo
            });
            if (itemToEdit.cliente) {
                setSelectedClient(itemToEdit.cliente);
                setClientQuery(itemToEdit.cliente.names);
            }
        } else {
            setForm({
                id: undefined,
                code: '',
                holder: '',
                stage: '',
                mz_lote: '',
                state: '',
                id_cliente: null, // nuevo campo
            });
            setSelectedClient(null);
            setClientQuery('');
        }
    }, [itemToEdit]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;
        setForm((prev) => ({
            ...prev,
            [name]: value,
        }));
    };

    const handleSubmit = async () => {
        try {
            setIsSubmitting(true);
            const res = form.id
                ? await axios.put(`/sales/${form.id}`, form)
                : await axios.post('/sales', form);

            onSaved(res.data.sale);
            onClose();
        } catch (error) {
            console.error('Error saving sale', error);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{form.id ? 'Editar Venta' : 'Nueva Venta'}</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <ClientSearch
                        query={clientQuery}
                        setQuery={setClientQuery}
                        selectedClient={selectedClient}
                        onSelect={(client) => {
                            setSelectedClient(client);
                            setForm((prev) => ({
                                ...prev,
                                id_cliente: client.id, // 👈 aquí lo enviamos correctamente
                            }));
                        }}
                    />


                    <Input
                        name="code"
                        placeholder="Código"
                        value={form.code}
                        onChange={handleChange}
                    />
                    <Input
                        name="holder"
                        placeholder="Titular"
                        value={form.holder}
                        onChange={handleChange}
                    />
                    <Input
                        name="stage"
                        placeholder="Etapa"
                        value={form.stage}
                        onChange={handleChange}
                    />
                    <Input
                        name="mz_lote"
                        placeholder="Mz-Lote"
                        value={form.mz_lote}
                        onChange={handleChange}
                    />
                    <Input
                        name="state"
                        placeholder="Estado"
                        value={form.state}
                        onChange={handleChange}
                    />
                    
                </div>

                <DialogFooter className="mt-6">
                    <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button onClick={handleSubmit} disabled={isSubmitting}>
                        {isSubmitting ? (
                            <>
                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                Guardando...
                            </>
                        ) : (
                            'Guardar'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
