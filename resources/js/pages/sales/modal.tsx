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
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import axios from 'axios';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';
import ClientSearch from '../supports/clientSearch';

export type ProjectOption = { id_proyecto: number; descripcion: string; habilitado?: boolean | number };
export type ClientRef = { id: number; names: string; dni?: string };

interface Props {
    open: boolean;
    onClose: () => void;
    onSaved: (sale: any) => void;
    itemToEdit?: any;
    projects: ProjectOption[];
    /** Cliente fijo (atajo "Registrar lote" desde la solicitud). */
    presetClient?: ClientRef | null;
}

/** "Mz. G Lt. 36" → { manzana: 'G', lote: '36' }; null si no tiene ese formato. */
export function parseMzLote(value: string | null | undefined): { manzana: string; lote: string } | null {
    const m = (value ?? '').match(/^\s*Mz\.?\s*(\S+)\s+Lt\.?\s*(\S+)\s*$/i);
    return m ? { manzana: m[1], lote: m[2] } : null;
}

export function buildMzLote(manzana: string, lote: string): string {
    return `Mz. ${manzana.trim().toUpperCase()} Lt. ${lote.trim()}`;
}

const emptyForm = {
    id: undefined as number | undefined,
    code: '',
    holder: '',
    stage: '',
    state: '',
    id_cliente: null as number | null,
    project_id: '' as number | '',
    manzana: '',
    lote: '',
};

export default function SaleModal({ open, onClose, onSaved, itemToEdit, projects, presetClient }: Props) {
    const [form, setForm] = useState(emptyForm);
    // mz_lote antiguo que no sigue el formato "Mz. X Lt. Y" (se conserva si no se completa)
    const [legacyMzLote, setLegacyMzLote] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [clientQuery, setClientQuery] = useState('');
    const [selectedClient, setSelectedClient] = useState<ClientRef | null>(null);

    const enabledProjects = projects.filter(
        (p) => p.habilitado === undefined || !!p.habilitado || p.id_proyecto === form.project_id,
    );

    useEffect(() => {
        if (!open) return;
        setErrors({});

        if (itemToEdit) {
            const parsed = parseMzLote(itemToEdit.mz_lote);
            setLegacyMzLote(parsed ? null : itemToEdit.mz_lote || null);
            setForm({
                id: itemToEdit.id,
                code: itemToEdit.code || '',
                holder: itemToEdit.holder || '',
                stage: itemToEdit.stage || '',
                state: itemToEdit.state || '',
                id_cliente: itemToEdit.client?.id ?? itemToEdit.id_cliente ?? null,
                project_id: itemToEdit.project_id ?? '',
                manzana: parsed?.manzana ?? '',
                lote: parsed?.lote ?? '',
            });
            setSelectedClient(itemToEdit.client ?? null);
            setClientQuery(itemToEdit.client?.names ?? '');
        } else {
            setLegacyMzLote(null);
            setForm({ ...emptyForm, id_cliente: presetClient?.id ?? null, holder: presetClient?.names ?? '' });
            setSelectedClient(presetClient ?? null);
            setClientQuery(presetClient?.names ?? '');
        }
        // presetClient se recrea en cada render del padre: depender solo de su id
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, itemToEdit, presetClient?.id]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        const { name, value } = e.target;
        setForm((prev) => ({ ...prev, [name]: name === 'project_id' ? (value ? Number(value) : '') : value }));
    };

    const validate = (): Record<string, string> => {
        const e: Record<string, string> = {};
        if (!form.id_cliente) e.id_cliente = 'Seleccione un cliente de la lista.';
        if (!form.project_id) e.project_id = 'Seleccione un proyecto.';
        const hasMzLt = form.manzana.trim() && form.lote.trim();
        if (!hasMzLt && !legacyMzLote) e.mz_lote = 'Ingrese manzana y lote.';
        if ((form.manzana.trim() && !form.lote.trim()) || (!form.manzana.trim() && form.lote.trim())) {
            e.mz_lote = 'Complete manzana y lote.';
        }
        return e;
    };

    const handleSubmit = async () => {
        const clientErrors = validate();
        setErrors(clientErrors);
        if (Object.keys(clientErrors).length) return;

        const payload = {
            code: form.code,
            holder: form.holder,
            stage: form.stage,
            state: form.state,
            id_cliente: form.id_cliente,
            project_id: form.project_id,
            mz_lote: form.manzana.trim() && form.lote.trim() ? buildMzLote(form.manzana, form.lote) : legacyMzLote,
        };

        try {
            setIsSubmitting(true);
            const res = form.id ? await axios.put(`/sales/${form.id}`, payload) : await axios.post('/sales', payload);
            toast.success(form.id ? 'Lote actualizado' : 'Lote registrado');
            onSaved(res.data.sale);
            onClose();
        } catch (error: any) {
            if (error.response?.status === 422) {
                const serverErrors = error.response.data.errors ?? {};
                setErrors(Object.fromEntries(Object.entries(serverErrors).map(([k, v]) => [k, (v as string[])[0]])));
            } else if (error.response?.status === 403) {
                toast.error('No tiene permiso para registrar lotes.');
            } else {
                toast.error('No se pudo guardar el lote.');
                console.error('Error saving sale', error);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const fieldError = (key: string) => errors[key] && <p className="text-xs text-red-600 mt-1">{errors[key]}</p>;
    const preview = form.manzana.trim() && form.lote.trim() ? buildMzLote(form.manzana, form.lote) : null;

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{form.id ? 'Editar lote del cliente' : 'Registrar lote del cliente'}</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div>
                        <Label>Cliente</Label>
                        {presetClient && !form.id ? (
                            <div className="px-3 py-2 border rounded bg-gray-100 text-sm dark:bg-neutral-800">
                                {presetClient.names}
                            </div>
                        ) : (
                            <ClientSearch
                                query={clientQuery}
                                setQuery={setClientQuery}
                                selectedClient={selectedClient}
                                onSelect={(client) => {
                                    setSelectedClient(client);
                                    setForm((prev) => ({ ...prev, id_cliente: client.id }));
                                }}
                            />
                        )}
                        {fieldError('id_cliente')}
                    </div>

                    <div>
                        <Label htmlFor="project_id">Proyecto</Label>
                        <select
                            id="project_id"
                            name="project_id"
                            value={form.project_id}
                            onChange={handleChange}
                            className="w-full border rounded px-3 py-2 text-sm dark:bg-black dark:text-white"
                        >
                            <option value="">Seleccione un proyecto</option>
                            {enabledProjects.map((p) => (
                                <option key={p.id_proyecto} value={p.id_proyecto}>
                                    {p.descripcion}
                                </option>
                            ))}
                        </select>
                        {fieldError('project_id')}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="manzana">Manzana</Label>
                            <Input id="manzana" name="manzana" placeholder="Ej: G" value={form.manzana} onChange={handleChange} maxLength={10} />
                        </div>
                        <div>
                            <Label htmlFor="lote">Lote</Label>
                            <Input id="lote" name="lote" placeholder="Ej: 36" value={form.lote} onChange={handleChange} maxLength={10} />
                        </div>
                    </div>
                    {preview && <p className="text-xs text-gray-500 -mt-2">Se guardará como: <strong>{preview}</strong></p>}
                    {legacyMzLote && !preview && (
                        <p className="text-xs text-amber-700 -mt-2">
                            Valor actual: «{legacyMzLote}». Complete Manzana y Lote para normalizarlo o déjelos vacíos para conservarlo.
                        </p>
                    )}
                    {fieldError('mz_lote')}

                    <Input name="holder" placeholder="Titular" value={form.holder} onChange={handleChange} />
                    <Input name="code" placeholder="Código" value={form.code} onChange={handleChange} />
                    <Input name="stage" placeholder="Etapa" value={form.stage} onChange={handleChange} />
                    <Input name="state" placeholder="Estado" value={form.state} onChange={handleChange} />
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
