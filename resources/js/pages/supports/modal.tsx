import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';
import ClientSearch from './clientSearch';
import { usePage } from '@inertiajs/react';

import LimitedInput from '@/components/LimitedInput';
import LimitedTextarea from '@/components/LimitedTextarea';

const getNowPlusHours = (plus = 0) => {
    const now = new Date();
    now.setHours(now.getHours() + plus);
    const pad = (n: number) => n.toString().padStart(2, '0');
    const yyyy = now.getFullYear();
    const MM = pad(now.getMonth() + 1);
    const dd = pad(now.getDate());
    const hh = pad(now.getHours());
    const mm = pad(now.getMinutes());
    return `${yyyy}-${MM}-${dd}T${hh}:${mm}`;
};

const SupportModal = ({
    open,
    onClose,
    onSaved,
    supportToEdit,
    motives,
    appointmentTypes,
    waitingDays,
    internalStates,
    externalStates,
    types,
    projects,
    areas
}: {
    open: boolean;
    onClose: () => void;
    onSaved: (support: any) => void;
    supportToEdit?: any;
    motives: any[];
    appointmentTypes: any[];
    waitingDays: any[];
    internalStates: any[];
    externalStates: any[];
    types: any[];
    projects: any[];
    areas: any[];
}) => {
    const [formData, setFormData] = useState<any>({
        subject: '',
        description: '',
        priority: 'Normal',
        type: 'Consulta',
        status: 'Pendiente',
        cellphone: '',
        dni: '',
        email: '',
        address: '',
        created_by: 1,
        client_id: 1,
        area_id: '',
        reservation_time: getNowPlusHours(0),
        attended_at: getNowPlusHours(1),
        derived: '',
        id_motivos_cita: '',
        id_tipo_cita: '',
        id_dia_espera: '',
        internal_state_id: '',
        external_state_id: '',
        type_id: '',
        project_id: '',
        Manzana: '',
        Lote: '',

    });

    const [clientQuery, setClientQuery] = useState<string>(''); // ✅

    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    //   const [areas, setAreas] = useState<{ id: number; name: string }[]>([]);
    const [uploading, setUploading] = useState(false);
    const { permissions } = usePage<{ permissions: string[] }>().props;
    const canEditAdvancedFields = permissions.includes('administrar') || permissions.includes('atc');
    const inputClass = 'col-span-3 text-sm h-7 px-2 py-1 rounded-md';
    const [selectedClient, setSelectedClient] = useState<any | null>(null);

    useEffect(() => {
        if (!supportToEdit) return;

        const cleanData = Object.fromEntries(
            Object.entries(supportToEdit).map(([key, val]) => [
                key,
                val === null || typeof val === 'undefined' ? '' : val,
            ])
        );

        const client = supportToEdit.client;
        if (client) {
            setSelectedClient(client);
            setClientQuery(client.names);

            // 👇 Esto es lo que te falta (probablemente)
            setFormData((prev: any) => ({
                ...prev,
                client_id: client.id,
                dni: client.dni,
                cellphone: client.cellphone,
                email: client.email,
                address: client.address,
            }));
        }

        setFormData((prev: any) => ({
            ...prev,
            ...cleanData,
            reservation_time: supportToEdit.reservation_time ?? getNowPlusHours(0),
            attended_at: supportToEdit.attended_at ?? getNowPlusHours(1),
        }));

        if (supportToEdit.attachment) {
            setPreview(`/attachments/${supportToEdit.attachment}`);
        }
    }, [supportToEdit]);



    const handleChange = (e: React.ChangeEvent<any>) => {
        const { name, value } = e.target;
        setFormData((prev: any) => ({
            ...prev,
            [name]: value,
        }));
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files?.[0] || null;
        setFile(selected);
        if (selected) {
            setPreview(selected.type.startsWith('image') ? URL.createObjectURL(selected) : selected.name);
        }
    };

    const handleSubmit = async () => {
        try {
            setUploading(true);
            const data = new FormData();
            Object.entries(formData).forEach(([key, value]) => {
                data.append(key, String(value ?? ''));
            });
            if (file) data.append('attachment', file);

            const url = supportToEdit ? `/supports/${supportToEdit.id}` : '/supports';
            if (supportToEdit) data.append('_method', 'PUT');

            const response = await axios.post(url, data);

            toast.success(supportToEdit ? 'Soporte actualizado ✅' : 'Soporte creado ✅');
            onSaved(response.data.support);
            onClose();
        } catch (error) {
            console.error('❌ Error al guardar:', error);
            toast.error('Hubo un error al guardar');
        } finally {
            setUploading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
<DialogContent className="sm:max-w-6xl h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{supportToEdit ? 'Editar Atención' : 'Nuevo Registro'}</DialogTitle>
                </DialogHeader>

                <div className="rounded-md bg-gray-100 dark:bg-gray-800 p-4 space-y-4">
                    <div className="text-lg font-semibold flex items-center gap-2 text-gray-800 dark:text-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5.121 17.804A13.937 13.937 0 0112 16c2.612 0 5.034.75 7.121 2.038M15 11a3 3 0 11-6 0 3 3 0 016 0zM19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2z" />
                        </svg>
                        Datos del Cliente
                    </div>

                    <div className="grid grid-cols-4 items-center w-full">
                        <Label className="text-left text-sm">Cliente :</Label>
                        <div className="col-span-3">
                            {supportToEdit ? (
                                <div className="px-2 py-1 border rounded bg-gray-100 text-sm">
                                    {selectedClient?.names || 'Cliente seleccionado'}
                                </div>
                            ) : (
                                <ClientSearch
                                    query={clientQuery}
                                    setQuery={setClientQuery}
                                    selectedClient={selectedClient}
                                    onSelect={(client) => {
                                        setFormData((prev) => ({
                                            ...prev,
                                            client_id: client.id,
                                            cellphone: client.cellphone || '',
                                            dni: client.dni || '',
                                            email: client.email || '',
                                            address: client.address || '',
                                        }));
                                        setSelectedClient(client);
                                        setClientQuery(client.names);
                                    }}
                                />
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-4 gap-4">
                        <div className="grid grid-cols-4 items-left">
                            <Label className="text-left">DNI</Label>
                        </div>
                        <div className="grid grid-cols-1 items-left">
                            <LimitedInput
                                name="dni"
                                label="DNI"
                                value={formData.dni}
                                onChange={handleChange}
                                maxLength={12}
                                inputClassName="col-span-1 text-sm h-7 px-2 py-1 rounded-md w-full"
                            />
                        </div>
                        <div className="grid grid-cols-1 items-center">
                            <Label className="text-center col-span-1">Celular</Label>
                        </div>
                        <div className="grid grid-cols-1 items-center">
                            <LimitedInput
                                name="cellphone"
                                label="Celular"
                                value={formData.cellphone}
                                onChange={handleChange}
                                maxLength={11}
                                inputClassName="col-span-1 text-sm h-7 px-2 py-1 rounded-md"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-4 items-center gap-2">
                        <Label className="text-left col-span-1">Email</Label>
                        <div className="col-span-3">
                            <LimitedInput
                                name="email"
                                value={formData.email}
                                onChange={handleChange}
                                maxLength={80}
                                inputClassName="w-full text-sm h-7 px-2 py-1 rounded-md"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-4 items-center gap-2">
                        <Label className="text-left col-span-1">Dirección</Label>
                        <div className="col-span-3">
                            <LimitedInput
                                name="address"
                                value={formData.address}
                                onChange={handleChange}
                                maxLength={200}
                                inputClassName="w-full text-sm h-7 px-2 py-1 rounded-md"
                            />
                        </div>
                    </div>
                </div>


                <div className="rounded-md bg-[#E0F4F7] p-4 space-y-4">
                    <div className="text-lg font-semibold flex items-center gap-2 text-yellow-700 dark:text-yellow-200">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-3-3v6m-4 4h8a2 2 0 002-2v-8a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0012.586 4H8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Detalle de Atención
                    </div>

                    <div className="grid grid-cols-1">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left col-span-1">Asunto</Label>
                            <div className="col-span-3">
                                <LimitedInput
                                    name="subject"
                                    value={formData.subject}
                                    onChange={handleChange}
                                    maxLength={150}
                                    inputClassName="w-full text-sm h-7 px-2 py-1 rounded-md"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-4 items-start gap-4">
                        <Label className="text-left col-span-1">Descripción</Label>
                        <div className="col-span-3">
                            <LimitedTextarea
                                name="description"
                                value={formData.description}
                                onChange={handleChange}
                                maxLength={800}
                                textareaClassName="w-full border rounded px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-4 items-start gap-4">
                        <Label className="text-left col-span-1">Proyecto</Label>
                        <div className="col-span-3">
                            <select
                                name="project_id"
                                value={formData.project_id}
                                onChange={handleChange}
                                className="w-full border rounded px-3 py-2 text-sm"
                            >
                                <option value="">Seleccione un proyecto</option>
                                {projects.map((p) => (
                                    <option key={p.id_proyecto} value={p.id_proyecto}>
                                        {p.descripcion}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-4 gap-4">
                        <div className="grid grid-cols-4 items-left">
                            <Label className="text-left">Manzana</Label>
                        </div>
                        <div className="grid grid-cols-1 items-left">
                            <LimitedInput
                                name="Manzana"
                                label="Manzana"
                                value={formData.Manzana}
                                onChange={handleChange}
                                maxLength={12}
                                inputClassName="col-span-1 text-sm h-7 px-2 py-1 rounded-md w-full"
                            />
                        </div>
                        <div className="grid grid-cols-1 items-center">
                            <Label className="text-center col-span-1">Lote</Label>
                        </div>
                        <div className="grid grid-cols-1 items-center">
                            <LimitedInput
                                name="Lote"
                                label="Lote"
                                value={formData.Lote}
                                onChange={handleChange}
                                maxLength={11}
                                inputClassName="col-span-1 text-sm h-7 px-2 py-1 rounded-md"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label className="text-left">Prioridad</Label>
                        <select
                            name="priority"
                            value={formData.priority}
                            onChange={handleChange}
                            className="col-span-3 border rounded"
                        >
                            <option value="Urgente">Urgente</option>
                            <option value="Moderado">Moderado</option>
                            <option value="Normal">Normal</option>
                            <option value="Baja Prioridad">Baja Prioridad</option>
                        </select>
                    </div>
                </div>

<div className="rounded-md bg-[#FAF3E0] p-4 space-y-4 mt-0">
  <div className="text-lg font-semibold flex items-center gap-2 text-[#7A5C2E]">
    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v2m0 12v2m8.485-10.485l-1.414 1.414M4.929 19.071l-1.414-1.414M20 12h2M2 12H4m15.071 7.071l-1.414-1.414M4.929 4.929l1.414 1.414" />
    </svg>
    Configuración Avanzada
  </div>
                <div className="grid grid-cols-2 gap-4 mt-2">

                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Área</Label>
                            <select
                                name="area_id"
                                value={formData.area_id}
                                onChange={handleChange}
                                className={inputClass}
                            >

                                {areas.map((a) => (
                                    <option key={a.id_area} value={a.id}>{a.descripcion}</option>
                                ))}
                            </select>
                        </div>
                    )}


                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label className="text-left">Archivo</Label>
                        <input type="file" onChange={handleFileChange} className="col-span-3" />
                        {preview && preview.startsWith('blob:') && (
                            <img src={preview} alt="preview" className="col-span-3 w-20 h-20 object-cover rounded" />
                        )}
                    </div>

                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Motivo de Cita</Label>
                            <select
                                name="id_motivos_cita"
                                value={formData.id_motivos_cita}
                                onChange={handleChange}
                                className={inputClass}
                            >

                                {motives.map(m => (
                                    <option key={m.id} value={m.id}>
                                        {m.nombre_motivo}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Tipo de Cita</Label>
                            <select name="id_tipo_cita" value={formData.id_tipo_cita} onChange={handleChange} className={inputClass}>

                                {appointmentTypes.map(t => <option key={t.id} value={t.id}>{t.tipo}</option>)}
                            </select>
                        </div>
                    )}

                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Día de Espera</Label>
                            <select
                                name="id_dia_espera"
                                value={formData.id_dia_espera}
                                onChange={handleChange}
                                className={inputClass}
                            >

                                {waitingDays.map(d => (
                                    <option key={d.id} value={d.id}>
                                        {d.dias}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}


                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Estado Interno</Label>
                            <select
                                name="internal_state_id"
                                value={formData.internal_state_id}
                                onChange={handleChange}
                                className={inputClass}
                            >

                                {internalStates.map(i => (
                                    <option key={i.id} value={i.id}>
                                        {i.description}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Estado de Atención</Label>
                            <select
                                name="external_state_id"
                                value={formData.external_state_id}
                                onChange={handleChange}
                                className={inputClass}
                            >

                                {externalStates.map(e => (
                                    <option key={e.id} value={e.id}>
                                        {e.description}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}




                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Tipo (Catálogo)</Label>
                            <select name="type_id" value={formData.type_id} onChange={handleChange} className={inputClass}>

                                {types.map(t => <option key={t.id} value={t.id}>{t.description}</option>)}
                            </select>
                        </div>


                    )}



                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Reserva</Label>
                            <Input type="datetime-local" name="reservation_time" value={formData.reservation_time} onChange={handleChange} className="col-span-3" />
                        </div>
                    )}
                    {canEditAdvancedFields && (
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Atendido</Label>
                            <Input type="datetime-local" name="attended_at" value={formData.attended_at} onChange={handleChange} className="col-span-3" />
                        </div>
                    )}


                </div>
                {canEditAdvancedFields && (

                    <div className="grid grid-cols-4 items-start gap-4">
                        <Label className="text-left col-span-1">Derivado</Label>

                        <div className="col-span-3">
                            <LimitedInput
                                name="derived"
                                value={formData.derived}
                                onChange={handleChange}
                                maxLength={150}
                                inputClassName="w-full text-sm h-7 px-2 py-1 rounded-md"
                            />
                        </div>
                    </div>

                )}
                </div>
                <DialogFooter>
                    <Button variant="ghost" onClick={onClose} disabled={uploading}>Cerrar</Button>
                    <Button onClick={handleSubmit} disabled={uploading}>
                        {uploading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default SupportModal;
