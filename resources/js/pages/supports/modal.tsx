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
type PageProps = {
    permissions: string[];
};
export default function SupportModal({
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
}) {
    const [formData, setFormData] = useState({
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
        manzana: '',
        lote: '',
    });


    const [clientQuery, setClientQuery] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [areas, setAreas] = useState<{ id: number; name: string }[]>([]);
    const [uploading, setUploading] = useState(false);
    const { permissions } = usePage<PageProps>().props;
    const canEditAdvancedFields = permissions.includes('administrar') || permissions.includes('atc');
    const inputClass = "col-span-3 text-sm h-7 px-2 py-1 rounded-md";

    useEffect(() => {
        axios.get('/areas/all')
            .then((res) => setAreas(res.data))
            .catch((err) => console.error('Error al cargar áreas:', err));

        if (supportToEdit) {
            setFormData({
                ...formData,
                ...supportToEdit,
                reservation_time: supportToEdit.reservation_time || getNowPlusHours(0),
                attended_at: supportToEdit.attended_at || getNowPlusHours(1)
            });
            setClientQuery(supportToEdit.client?.names || '');
            if (supportToEdit.attachment) {
                setPreview(`/attachments/${supportToEdit.attachment}`);
            }
        }
    }, [supportToEdit]);

    const handleChange = (e: React.ChangeEvent<any>) => {
        const { name, value } = e.target;
        setFormData((prev) => ({
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
                if (value !== null) data.append(key, String(value));
            });
            if (file) data.append('attachment', file);

            const url = supportToEdit ? `/supports/${supportToEdit.id}` : '/supports';
            if (supportToEdit) data.append('_method', 'PUT');

            const response = await axios.post(url, data, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

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
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{supportToEdit ? 'Editar Atención' : 'Nuevo Registro'}</DialogTitle>
                </DialogHeader>



                <div className="grid grid-cols-1 gap-4">
                    <div className="grid grid-cols-4 items-center w-full">
                        <Label className="text-left text-sm">Cliente :</Label>
                        <div className="col-span-3">
                            <ClientSearch
                                query={clientQuery}
                                setQuery={setClientQuery}
                                onSelect={(client) => {
                                    setFormData((prev) => ({
                                        ...prev,
                                        client_id: client.id,
                                        cellphone: client.cellphone || '',
                                        dni: client.dni || '',
                                        email: client.email || '',
                                        address: client.address || '',
                                    }));
                                    setClientQuery(client.names);
                                }}
                            />
                        </div>
                    </div>
                </div>


                <div className="grid grid-cols-4 gap-4">
                    <div className="grid grid-cols-4 items-left ">
                        <Label className="text-left  ">Dni</Label>

                    </div>
                    <div className="grid grid-cols-1 items-left ">

                        <LimitedInput
                            name="dni"
                            label="DNI"
                            value={formData.dni}
                            onChange={handleChange}
                            maxLength={12}
                            inputClassName="col-span-1 text-sm h-7 px-2 py-1 rounded-md w-full" // ✅ clases dinámicas aquí
                        />

                    </div>
                    <div className="grid grid-cols-1 items-center ">
                        <Label className="text-center col-span-1">Celular</Label>



                    </div>
                    <div className="grid grid-cols-1 items-center ">
                        <LimitedInput
                            name="cellphone"
                            label="Celular"
                            value={formData.cellphone}
                            onChange={handleChange}
                            maxLength={11}
                            inputClassName=" col-span-1 text-sm h-7 px-2 py-1 rounded-md" // ✅ clases dinámicas aquí
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
                            maxLength={800} // puedes ajustar el límite si deseas
                            textareaClassName="w-full border rounded px-3 py-2 text-sm"
                        />
                    </div>
                </div>

<div className="grid grid-cols-4 gap-4">
                    <div className="grid grid-cols-4 items-left ">
                        <Label className="text-left  ">Manzana</Label>

                    </div>
                    <div className="grid grid-cols-1 items-left ">

                        <LimitedInput
                            name="manzana"
                            label="Manzana"
                            value={formData.manzana}
                            onChange={handleChange}
                            maxLength={12}
                            inputClassName="col-span-1 text-sm h-7 px-2 py-1 rounded-md w-full" // ✅ clases dinámicas aquí
                        />

                    </div>
                    <div className="grid grid-cols-1 items-center ">
                        <Label className="text-center col-span-1">Lote</Label>



                    </div>
                    <div className="grid grid-cols-1 items-center ">
                        <LimitedInput
                            name="lote"
                            label="Lote"
                            value={formData.lote}
                            onChange={handleChange}
                            maxLength={11}
                            inputClassName=" col-span-1 text-sm h-7 px-2 py-1 rounded-md" // ✅ clases dinámicas aquí
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
                              <option value="">Seleccione un proyecto...</option> {/* <-- Esta línea ayuda a evitar autoselección */}
                            {projects.map((p) => (
                                <option key={p.id_proyecto} value={p.id_proyecto}>
                                    {p.descripcion}
                                </option>
                            ))}
                        </select>
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
                        <option value="Normal">Baja Prioridad</option>
                    </select>
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
                                    <option key={a.id} value={a.id}>{a.name}</option>
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
                                <option value="">Seleccione...</option>
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
                            <Label className="text-left">Estado Externo</Label>
                            <select
                                name="external_state_id"
                                value={formData.external_state_id}
                                onChange={handleChange}
                                className={inputClass}
                            >
                                <option value="">Seleccione...</option>
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
}
