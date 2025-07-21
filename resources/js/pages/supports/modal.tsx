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
import SupportCommentSection from './SupportCommentSection'; // ajusta la ruta si es necesaria

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
interface SupportDetailRaw {
    id: number;
    subject?: string;
    description?: string;
    priority?: string;
    type?: string;
    status?: string;
    reservation_time?: string;
    attended_at?: string;
    derived?: string;
    Manzana?: string;
    comment?: string;
    project_id?: number;
    area_id?: number;
    id_motivos_cita?: number;
    id_tipo_cita?: number;
    id_dia_espera?: number;
    internal_state_id?: number;
    external_state_id?: number;
    type_id?: number;
    project?: any;
    area?: any;
    motivo_cita?: any;
    tipo_cita?: any;
    dia_espera?: any;
    internal_state?: any;
    external_state?: any;
    support_type?: any;
}

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

        cellphone: '',
        dni: '',
        email: '',
        address: '',
        created_by: 1,
        client_id: 1,

        status_global: 'No', // ✅ este es el que falta

    });

    const [clientQuery, setClientQuery] = useState<string>(''); // ✅

    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    //   const [areas, setAreas] = useState<{ id: number; name: string }[]>([]);
    const [uploading, setUploading] = useState(false);
    const { permissions } = usePage<{ permissions: string[] }>().props;
    const canEditAdvancedFields = permissions.includes('administrar') || permissions.includes('solicitudes.acciones_avanzadas');
    const canEdiAdminAtcReservaFields = permissions.includes('administrar') || permissions.includes('solicitudes.acciones_avanzadas') || permissions.includes('reserva');
    const canEditChannelSelect = permissions.includes('Canal.whatsapp_presencial');

    const inputClass = 'col-span-3 text-sm h-7 px-2 py-1 rounded-md';
    const [selectedClient, setSelectedClient] = useState<any | null>(null);
    const [details, setDetails] = useState<any[]>([]);
    const [salesFromClient, setSalesFromClient] = useState<any[]>([]);
    const [availableLots, setAvailableLots] = useState<string[]>([]);

    const [supportDetails, setSupportDetails] = useState<any[]>([]);



    const projectMapEntries = salesFromClient
        .map((s) => {
            const project = s.project || projects.find(p => p.id_proyecto === s.project_id);
            return [s.project_id, project] as [number, any];
        })
        .filter(([_, project]) => !!project); // eliminar nulos

    const clientProjects = Array.from(new Map(projectMapEntries).values());
    function formatDateTimeLocal(datetimeString: string | null): string {
        if (!datetimeString) return '';
        const date = new Date(datetimeString);
        const offset = date.getTimezoneOffset(); // diferencia entre UTC y hora local
        date.setMinutes(date.getMinutes() - offset); // convierte a hora local real
        return date.toISOString().slice(0, 16); // formato: yyyy-MM-ddTHH:mm
    }



    const [currentDetail, setCurrentDetail] = useState<any>({
        id: null,
        subject: '',
        description: '',
        priority: 'Baja',
        type: 'Consulta',
        status: 'Pendiente',
        reservation_time: getNowPlusHours(0),
        attended_at: getNowPlusHours(1),
        derived: '',
        project_id: null,
        area_id: null, // ✅ valor numérico
        id_motivos_cita: null,
        id_tipo_cita: 1,
        id_dia_espera: null,
        internal_state_id: 3,
        external_state_id: 1,
        type_id: null,
        Manzana: '',
        comment: '',
        attachment: null,

        // Relaciones enriquecidas
        project: null,
        area: null,
        motivo_cita: null,
        tipo_cita: null,
        dia_espera: null,
        internal_state: null,
        external_state: null,

        // Extras
        ticket: '',
        attended_start: '',
        attended_end: '',
        ticket_start: '',
        ticket_end: '',
        channel: '',
    });


    const handleDetailChange = (e: React.ChangeEvent<any>) => {


        const { name, value } = e.target;
        const numericValue = Number(value); // 👈 conversión común


        // Relacionar IDs con objetos cuando aplica
        if (name === 'internal_state_id') {
            const selected = internalStates.find(i => i.id === numericValue);
            setCurrentDetail(prev => ({
                ...prev,
                internal_state_id: numericValue,
                internal_state: selected || null,
            }));
            return;
        }

        if (name === 'external_state_id') {
            const selected = externalStates.find(e => e.id === numericValue);
            setCurrentDetail(prev => ({
                ...prev,
                external_state_id: numericValue,
                external_state: selected || null,
            }));
            return;
        }

        if (name === 'area_id') {
            const selected = areas.find(a => a.id_area === numericValue);
            setCurrentDetail(prev => ({
                ...prev,
                area_id: numericValue,
                area: selected || null,
            }));
            return;
        }

        if (name === 'project_id') {
            const lots = salesFromClient
                .filter((s) => s.project_id === numericValue)
                .map((s) => s.mz_lote);
            const selected = projects.find(p => p.id_proyecto === numericValue);
            setAvailableLots(lots);
            setCurrentDetail(prev => ({
                ...prev,
                project_id: numericValue,
                project: selected || null,
                Manzana: '',
                comment: '',
            }));
            return;
        }

        if (name === 'id_motivos_cita') {
            const selected = motives.find(m => m.id === numericValue);
            setCurrentDetail(prev => ({
                ...prev,
                id_motivos_cita: numericValue,
                motivo_cita: selected || null,
            }));
            return;
        }

        if (name === 'id_tipo_cita') {
            const selected = appointmentTypes.find(t => t.id === numericValue);
            setCurrentDetail(prev => ({
                ...prev,
                id_tipo_cita: numericValue,
                tipo_cita: selected || null,
            }));
            return;
        }

        if (name === 'id_dia_espera') {
            const selected = waitingDays.find(d => d.id === numericValue);
            setCurrentDetail(prev => ({
                ...prev,
                id_dia_espera: numericValue,
                dia_espera: selected || null,
            }));
            return;
        }

        if (name === 'type_id') {
            const selected = types.find(t => t.id === numericValue);
            setCurrentDetail(prev => ({
                ...prev,
                type_id: numericValue,
                support_type: selected || null,
            }));
            return;
        }

        // Por defecto: solo actualiza el valor (para campos tipo texto)
        setCurrentDetail(prev => ({ ...prev, [name]: value }));
    };






    const handleAddDetail = () => {

        // Validación básica
        if (!currentDetail.subject?.trim()) {
            toast.error("El asunto es obligatorio");
            return;
        }

        // Campos numéricos a forzar
        const numericFields = [
            'project_id',
            'area_id',
            'id_motivos_cita',
            'id_tipo_cita',
            'id_dia_espera',
            'internal_state_id',
            'external_state_id',
            'type_id',
        ];

        // Convertir campos numéricos a number o null
        const numericValues = Object.fromEntries(
            numericFields.map((key) => [
                key,
                currentDetail[key] === '' || currentDetail[key] === null
                    ? null
                    : Number(currentDetail[key])
            ])
        );

        const sanitizedDetail = {
            ...currentDetail,
            ...numericValues,

            // Relaciones enriquecidas
            project: projects.find(p => p.id_proyecto === numericValues.project_id) || null,
            //  area: areas.find(a => a.id_area === numericValues.area_id) || { id_area: 1, descripcion: 'solicitudes.acciones_avanzadas' },
            motivo_cita: motives.find(m => m.id === numericValues.id_motivos_cita) || null,
            tipo_cita: appointmentTypes.find(t => t.id === numericValues.id_tipo_cita) || null,
            dia_espera: waitingDays.find(d => d.id === numericValues.id_dia_espera) || null,
            internal_state: internalStates.find(i => i.id === numericValues.internal_state_id) || { id: 3, description: 'Pendiente' },
            external_state: externalStates.find(e => e.id === numericValues.external_state_id) || { id: 1, description: 'Por Asignar' },
            support_type: types.find(t => t.id === numericValues.type_id) || null,
            priority: currentDetail.priority?.trim() || 'Media',
        };


        // Agregar detalle

        setSupportDetails((prev) => {
            const updated = [...prev, sanitizedDetail];

            return updated;
        });


        // Reiniciar formulario con tipos coherentes
        setCurrentDetail({
            id: null,
            subject: '',
            description: '',
            priority: 'Baja',
            type: 'Consulta',
            status: 'Pendiente',
            reservation_time: getNowPlusHours(0),
            attended_at: getNowPlusHours(1),
            derived: '',
            Manzana: '',
            comment: '',
            attachment: null,

            project_id: null,
            area_id: null,
            id_motivos_cita: null,
            id_tipo_cita: 1,
            id_dia_espera: null,
            internal_state_id: 3,
            external_state_id: 1,
            type_id: null,

            ticket: '',
            attended_start: '',
            attended_end: '',
            ticket_start: '',
            ticket_end: '',
            channel: '',

            // Relaciones limpias
            project: null,
            area: null,
            motivo_cita: null,
            tipo_cita: null,
            dia_espera: null,
            internal_state: null,
            external_state: null,
            support_type: null,
        });
    };

    const [hasLoadedSupport, setHasLoadedSupport] = useState(false);


    useEffect(() => {
        if (!supportToEdit || hasLoadedSupport) return;

        const { client, details, ...supportFields } = supportToEdit;

        const cleanedSupport = Object.fromEntries(
            Object.entries(supportFields).map(([key, val]) => [
                key,
                val === null || typeof val === 'undefined' ? '' : val,
            ])
        );

        setFormData((prev: any) => ({
            ...prev,
            ...cleanedSupport,
            client_id: client?.id_cliente ?? '',
            dni: client?.dni ?? '',
            cellphone: client?.Telefono ?? '',
            email: client?.Email ?? '',
            address: client?.Direccion ?? '',
            status_global: supportToEdit.status_global || 'No',
        }));

        if (client) {
            setSelectedClient({
                id: client.id_cliente,
                names: client.Razon_Social,
                dni: client.DNI,
                cellphone: client.Telefono,
                email: client.Email,
                address: client.Direccion,
            });

            setClientQuery(client.Razon_Social);

            const enrichedSales = (client.sales || []).map((s) => ({
                ...s,
                project: s.project || projects.find((p) => p.id_proyecto === s.project_id),
            }));
            setSalesFromClient(enrichedSales);
        }

        if (details && Array.isArray(details)) {
            const enrichedDetails = details.map((detail: any, idx: number) => ({
                ...detail,
                area: detail.area ?? areas.find((a) => a.id_area === detail.area_id) ?? null,
                motivo_cita: detail.motivo_cita ?? motives.find((m) => m.id === detail.id_motivos_cita) ?? null,
                tipo_cita: detail.tipo_cita ?? appointmentTypes.find((t) => t.id === detail.id_tipo_cita) ?? null,
                dia_espera: detail.dia_espera ?? waitingDays.find((d) => d.id === detail.id_dia_espera) ?? null,
                internal_state: detail.internal_state ?? internalStates.find((i) => i.id === detail.internal_state_id) ?? null,
                external_state: detail.external_state ?? externalStates.find((e) => e.id === detail.external_state_id) ?? null,
                support_type: detail.support_type ?? types.find((t) => t.id === detail.type_id) ?? null,
                project: detail.project ?? projects.find((p) => p.id_proyecto === detail.project_id) ?? null,
            }));

            setSupportDetails(enrichedDetails);

            const detail = enrichedDetails[0];
            if (detail) {
                setCurrentDetail((prev) => ({
                    ...prev,
                    id: detail.id ?? null,
                    subject: detail.subject ?? '',
                    description: detail.description ?? '',
                    priority: detail.priority ?? 'Media',
                    type: detail.type ?? 'Consulta',
                    status: detail.status ?? 'Pendiente',
                    reservation_time: detail.reservation_time ?? getNowPlusHours(0),
                    attended_at: detail.attended_at ?? getNowPlusHours(1),
                    derived: detail.derived ?? '',
                    Manzana: detail.Manzana ?? '',
                    comment: detail.comment ?? '',

                    // IDs numéricos
                    project_id: detail.project_id ? Number(detail.project_id) : null,
                    area_id: detail.area_id ? Number(detail.area_id) : null,
                    id_motivos_cita: detail.id_motivos_cita ? Number(detail.id_motivos_cita) : null,
                    id_tipo_cita: detail.id_tipo_cita ? Number(detail.id_tipo_cita) : 1,
                    id_dia_espera: detail.id_dia_espera ? Number(detail.id_dia_espera) : null,
                    internal_state_id: detail.internal_state_id ? Number(detail.internal_state_id) : 3,
                    external_state_id: detail.external_state_id ? Number(detail.external_state_id) : 1,
                    type_id: detail.type_id ? Number(detail.type_id) : null,

                    // Relaciones enriquecidas
                    project: detail.project,
                    area: detail.area,
                    motivo_cita: detail.motivo_cita,
                    tipo_cita: detail.tipo_cita,
                    dia_espera: detail.dia_espera,
                    internal_state: detail.internal_state,
                    external_state: detail.external_state,
                    support_type: detail.support_type,

                    attachment: null,
                    ticket_start: formatDateTimeLocal(detail.ticket_start),
                    ticket_end: formatDateTimeLocal(detail.ticket_end),
                    ticket: detail.ticket || '',
                    channel: detail.channel || '',
                }));

                if (detail.project_id) {
                    const lots = (client?.sales || [])
                        .filter((s) => s.project_id === Number(detail.project_id))
                        .map((s) => s.mz_lote);
                    setAvailableLots(lots);
                }
            }
        }

        setHasLoadedSupport(true);
    }, [supportToEdit, hasLoadedSupport]);




    const handleChange = (e: React.ChangeEvent<any>) => {
        const { name, value } = e.target;
        setFormData((prev: any) => ({
            ...prev,
            [name]: value,
        }));
    };

    // const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    //     const selected = e.target.files?.[0] || null;
    //     setFile(selected);
    //     if (selected) {
    //         setPreview(selected.type.startsWith('image') ? URL.createObjectURL(selected) : selected.name);
    //     }
    // };

    const handleSubmit = async () => {
        try {
            setUploading(true);
            // if (canEditChannelSelect && !formData.channel) {
            //     toast.warning('El campo "Canal" es obligatorio');
            //     return;
            // }


            const data = new FormData();

            // 1. Campos del soporte general
            Object.entries(formData).forEach(([key, value]) => {
                data.append(key, String(value ?? ''));
            });

            // 2. Archivo general
            if (file) {
                data.append('attachment', file);
            }

            // 3. Archivos por detalle
            supportDetails.forEach((detail, index) => {
                if (detail.attachment) {
                    data.append(`attachments[${index}]`, detail.attachment);
                }
            });

            // 4. Determinar origen de detalles
            let updatedSupportDetails = [...supportDetails];

            // Si estás editando (PUT), reemplaza currentDetail en la lista
            if (supportToEdit && currentDetail.id) {
                updatedSupportDetails = supportDetails.map((detail) =>
                    detail.id === currentDetail.id ? { ...detail, ...currentDetail } : detail
                );
            }

            // Si estás creando (POST), ya diste clic en Agregar y solo hay 1 detalle
            if (!supportToEdit && !currentDetail.id) {
                // Sanidad extra: asegúrate que tenga un detalle válido
                if (supportDetails.length === 0) {
                    toast.warning('Debes agregar al menos un detalle de atención');
                    setUploading(false);
                    return;
                }
            }

            // 5. Mapeo limpio
            const mappedDetails = updatedSupportDetails.map((detail) => ({
                subject: detail.subject?.trim() || '',
                description: detail.description?.trim() || '',
                priority: detail.priority,
                type: detail.type,
                status: detail.status,
                reservation_time: detail.reservation_time,
                attended_at: detail.attended_at,
                derived: detail.derived,
                project_id: detail.project?.id_proyecto ?? detail.project_id ?? null,
                area_id: detail.area?.id_area ?? detail.area_id ?? null,
                id_motivos_cita: detail.motivo_cita?.id ?? detail.id_motivos_cita ?? null,
                id_tipo_cita: detail.tipo_cita?.id ?? detail.id_tipo_cita ?? 1,
                id_dia_espera: detail.dia_espera?.id ?? detail.id_dia_espera ?? null,
                internal_state_id: detail.internal_state?.id ?? detail.internal_state_id ?? 3,
                external_state_id: detail.external_state?.id ?? detail.external_state_id ?? 1,
                type_id: detail.support_type?.id ?? detail.type_id ?? null,
                Manzana: detail.Manzana?.trim() || '',
                comment: detail.comment?.trim() || '',
            }));

            // 6. Validación obligatoria para nuevos
            if (!supportToEdit && mappedDetails.length === 0) {
                toast.warning('Debes agregar al menos un detalle de atención');
                setUploading(false);
                return;
            }

            // 7. Agrega detalles
            data.append('details', JSON.stringify(mappedDetails));

            // 8. Método y URL
            const url = supportToEdit ? `/supports/${supportToEdit.id}` : '/supports';
            if (supportToEdit) data.append('_method', 'PUT');

            // 9. Enviar
            const response = await axios.post(url, data);

            if (response.data?.support) {
                toast.success(supportToEdit ? 'Solicitud actualizada ✅' : 'Solicitud creada ✅');
                onSaved(response.data.support);
                onClose();
            } else if (response.data?.message) {
                toast.warning(response.data.message, {
                    duration: Infinity,
                    action: { label: 'Cerrar', onClick: () => { } },
                });
            } else {
                toast.error('Error desconocido del servidor');
            }

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
                    <DialogTitle>{supportToEdit ? 'Editar Solicitud' : 'Nueva Solicitud'}</DialogTitle>
                </DialogHeader>
                {canEditChannelSelect && (
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Canal
                        </label>
                        <select
                            value={formData.channel || ''}
                            onChange={(e) =>
                                setFormData({ ...formData, channel: e.target.value })
                            }
                            className="w-full border border-gray-300 rounded-md px-3 py-2 shadow-sm focus:ring focus:ring-blue-200 focus:border-blue-400"
                        >
                            <option value="">Seleccione un canal</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="presencial">Presencial</option>
                        </select>
                    </div>
                )}



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

                                        // ✅ Guardar ventas del cliente
                                        setSalesFromClient(client.sales || []);

                                        // ✅ Reiniciar proyecto/lote si cambia de cliente
                                        setCurrentDetail((prev) => ({
                                            ...prev,
                                            project_id: '',
                                            Manzana: '',
                                            comment: '',
                                        }));

                                        // Limpiar también lotes disponibles
                                        setAvailableLots([]);
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


                {/* {canEdiAdminAtcReservaFields && (
                    <div
                        className={`grid grid-cols-4 items-center gap-4 p-2 rounded-md shadow-md
      ${formData.status_global === 'Sí'
                                ? 'border-blue-600 bg-blue-100 dark:bg-blue-900 shadow-blue-400'
                                : 'border-red-600 bg-red-100 dark:bg-red-900 shadow-red-400'
                            }
    `}
                    >
                        <Label
                            className={`text-left font-semibold
        ${formData.status_global === 'Sí'
                                    ? 'text-blue-800 dark:text-blue-200'
                                    : 'text-red-800 dark:text-red-200'
                                }
      `}
                        >
                            📅 ¿Con cita?
                        </Label>

                        <div className="col-span-3 flex gap-6 items-center text-sm font-semibold">
                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="status_global"
                                    value="Sí"
                                    checked={formData.status_global === 'Sí'}
                                    onChange={handleChange}
                                />
                                Sí
                            </label>

                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="status_global"
                                    value="No"
                                    checked={formData.status_global === 'No'}
                                    onChange={handleChange}
                                />
                                No
                            </label>
                        </div>
                    </div>


                )} */}
                <div className="rounded-md bg-[#E0F4F7] p-4 space-y-4">
                    {/* Título */}
                    <div className="text-lg font-semibold flex items-center gap-2 text-yellow-700 dark:text-yellow-200">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-3-3v6m-4 4h8a2 2 0 002-2v-8a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0012.586 4H8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                       SOLICITUD A GESTIONAR
                    </div>




                    {/* Proyecto */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label className="text-left col-span-1">Proyecto</Label>
                        <div className="col-span-3">
                            <select
                                name="project_id"
                                value={currentDetail.project_id}
                                onChange={handleDetailChange}
                                className="w-full border rounded px-3 py-2 text-sm"
                            >
                                <option value="">Seleccione un proyecto</option>
                                {clientProjects.map((p) => (
                                    <option key={p.id_proyecto} value={p.id_proyecto}>
                                        {p.descripcion}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Manzana y Prioridad */}
                    {/* Manzana / Lote */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label className="text-left col-span-1">Manzana / Lote</Label>
                        <div className="col-span-3">
                            <select
                                name="Manzana"
                                value={currentDetail.Manzana}
                                onChange={handleDetailChange}
                                className="w-full border rounded px-3 py-2 text-sm"
                            >
                                <option value="">Seleccione Manzana y Lote</option>
                                {availableLots.map((mz) => (
                                    <option key={mz} value={mz}>{mz}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label className="text-left col-span-1">Solicitud</Label>
                        <div className="col-span-3">
                            <select
                                name="subject"
                                value={currentDetail.subject}
                                onChange={handleDetailChange}
                                className="w-full h-8 rounded-md border px-2 text-sm dark:bg-black dark:text-white"
                            >
                                <option value="">Seleccione la Solicitud</option>
                                {[
                                    'Avance de Proyecto',
                                    'Boletas',
                                    'Cesion',
                                    'Cita con legal',
                                    'Certificado de lote',
                                    'Constancia de no adeudo',
                                    'Desestimiento',
                                    'EE.CC',
                                    'Formalización',
                                    'Información de su lote',
                                    'Pagos',
                                    'Recojo de contrato',
                                    'Recojo de Letras',
                                    'Traspaso de aportes',
                                    'Visita a proyecto',
                                ].sort().map((label) => (
                                    <option key={label} value={label}>{label}</option>
                                ))}
                            </select>
                        </div>
                    </div>


                    <div className="grid grid-cols-4 items-start gap-4">
                        <Label className="text-left col-span-1">Descripción</Label>
                        <div className="col-span-3">
                            <LimitedTextarea
                                name="description"
                                value={currentDetail.description}
                                onChange={handleDetailChange}
                                maxLength={800}
                                textareaClassName="w-full border rounded px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                    {/* Prioridad */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label className="text-left col-span-1">Prioridad</Label>
                        <div className="col-span-3">
                            <select
                                name="priority"
                                value={currentDetail.priority}
                                onChange={handleDetailChange}
                                className="w-full border rounded px-3 py-2 text-sm"
                            >
                                <option value="Alta">Alta</option>
                                <option value="Media">Media</option>
                                <option value="Baja">Baja</option>
                            </select>
                        </div>
                    </div>


                 

                       <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-left">Archivo</Label>
                            <input
                                type="file"
                                name="attachment"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] || null;
                                    setCurrentDetail((prev: any) => ({
                                        ...prev,
                                        attachment: file,
                                        attachment_name: file?.name || null,
                                    }));
                                }}
                                className="col-span-3 text-sm"
                            />

                            {preview && preview.startsWith('blob:') && (
                                <img src={preview} alt="preview" className="col-span-3 w-20 h-20 object-cover rounded" />
                            )}
                        </div>
                          {canEditAdvancedFields && (
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-left">Área Responsable</Label>
                                <select
                                    name="area_id"
                                    value={String(currentDetail.area_id ?? '')} // ✅ Convertimos a string
                                    onChange={handleDetailChange}
                                    className={inputClass}
                                >
                                    <option value="">Seleccione un área</option>
                                    {areas.map((a) => (
                                        <option key={a.id_area} value={String(a.id_area)}>
                                            {a.descripcion}
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
                                    value={currentDetail.internal_state_id}
                                    onChange={handleDetailChange}
                                    className={inputClass}
                                >
                                    <option value="">Seleccione un Estado Interno</option>
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
                                    value={currentDetail.external_state_id}
                                    onChange={handleDetailChange}
                                    className={inputClass}
                                >
                                    <option value="">Seleccione un Atención  </option>
                                    {externalStates.map(e => (
                                        <option key={e.id} value={e.id}>
                                            {e.description}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                </div>


                <div className="rounded-md bg-[#FAF3E0] p-4 space-y-0 mt-0">
                    <div className="text-lg font-semibold flex items-center gap-2 text-[#7A5C2E]">
                      
                    
                    </div>
                    <div className="grid grid-cols-2 gap-4 mt-2">

                      



                     

                        {/* {canEditAdvancedFields && (
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-left">Motivo de Cita</Label>
                                <select
                                    name="id_motivos_cita"
                                    value={currentDetail.id_motivos_cita}
                                    onChange={handleDetailChange}
                                    className={inputClass}
                                >
                                    <option value="">Seleccione un Motivo</option>
                                    {motives.map(m => (
                                        <option key={m.id} value={m.id}>
                                            {m.nombre_motivo}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )} */}

                        {/* {canEditAdvancedFields && (
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-left">Tipo de Cita</Label>
                                <select name="id_tipo_cita" value={currentDetail.id_tipo_cita} onChange={handleDetailChange} className={inputClass}>
                                    <option value="">Seleccione un Tipo</option>
                                    {appointmentTypes.map(t => <option key={t.id} value={t.id}>{t.tipo}</option>)}
                                </select>
                            </div>
                        )} */}

                        {/* {canEditAdvancedFields && (
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-left">Día de Espera</Label>
                                <select
                                    name="id_dia_espera"
                                    value={currentDetail.id_dia_espera}
                                    onChange={handleDetailChange}
                                    className={inputClass}
                                >
                                    <option value="">Seleccione un Día de espera</option>
                                    {waitingDays.map(d => (
                                        <option key={d.id} value={d.id}>
                                            {d.dias}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )} */}


                       

                        {/* {canEditAdvancedFields && (
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-left">Tipo</Label>
                                <select name="type_id" value={currentDetail.type_id} onChange={handleDetailChange} className={inputClass}>

                                    {types.map(t => <option key={t.id} value={t.id}>{t.description}</option>)}
                                </select>
                            </div>


                        )} */}

                        


                        <>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-left text-blue-800 font-semibold">Inicio de Ticket</Label>
                                <Input
                                    type="datetime-local"
                                    name="ticket_start"
                                    value={currentDetail.ticket_start}
                                    onChange={handleDetailChange}
                                    readOnly
                                    className="col-span-3 border-2 border-blue-800 bg-blue-50 text-blue-900 font-semibold rounded-md"
                                />
                            </div>

                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-left text-blue-800 font-semibold">Cierre de Ticket</Label>
                                <Input
                                    type="datetime-local"
                                    name="ticket_end"
                                    value={currentDetail.ticket_end}
                                    onChange={handleDetailChange}
                                    readOnly
                                    className="col-span-3 border-2 border-blue-800 bg-blue-50 text-blue-900 font-semibold rounded-md"
                                />
                            </div>


                        </>






                    </div>




                </div>
                    <div className="mt-4">

                        <SupportCommentSection supportDetailId={currentDetail.id} />

                    </div>
                <button
                    type="button"
                    onClick={handleAddDetail}
                    className={`px-3 py-1 rounded ${supportDetails.length >= 1 ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white'}`}
                    disabled={supportDetails.length >= 1}
                >
                    Agregar Solicitud (Máximo 1)
                </button>



                <table className="w-full mt-4 text-sm border">
                    <thead>
                        <tr className="bg-gray-200">
                            <th className="border px-2">#</th>
                            <th className="border px-2">Asunto</th>

                            <th className="border px-2">Proyecto</th>
                            <th className="border px-2">Area</th>
                            <th className="border px-2">Asunto</th>
                            <th className="border px-2">Prioridad</th>
                            <th className="border px-2">Estado Interno</th>
                            <th className="border px-2">Estado de Atención</th>
                            {/* <th className="border px-2">Acciones</th> */}
                        </tr>
                    </thead>
                    <tbody>
                        {supportDetails.map((detail, idx) => (
                            <tr key={idx}>
                                <td className="border px-2">{idx + 1}</td>
                                <td className="border px-2">{detail.subject}</td>

                                <td className="border px-2">{detail.project?.descripcion}</td>
                                <td className="border px-2">{detail.area?.descripcion}</td>
                                <td className="border px-2">{detail.subject}</td>
                                <td className="border px-2">{detail.priority}</td>
                                <td className="border px-2">{detail.internal_state?.description}</td>
                                <td className="border px-2">{detail.external_state?.description}</td>
                                {/* <td className="border px-2">
                                    <button
                                        onClick={() =>
                                            setSupportDetails((prev) => prev.filter((_, i) => i !== idx))
                                        }
                                        className="text-red-600 text-xs"
                                    >
                                        Eliminar
                                    </button>
                                </td> */}
                            </tr>
                        ))}
                    </tbody>
                </table>




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
