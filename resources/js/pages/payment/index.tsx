import React, { useMemo, useState } from "react";
import { Head, useForm, usePage } from "@inertiajs/react";
import { LoaderCircle } from "lucide-react";
import {
    Mail, IdCard, User, ReceiptText, DollarSign, FileText, Folder, MapPin, Paperclip
} from 'lucide-react';

type Project = { id_proyecto: number; descripcion: string };

type FileErrors = {
    file_1?: string;
    file_2?: string;
    file_3?: string;
};

const MAX_FILE_BYTES = 1 * 1024 * 1024; // 1 MB
function FormRow({
    id, label, children,
}: { id: string; label: string; children: React.ReactNode }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
            <label htmlFor={id} className="text-sm font-medium text-gray-900 md:text-left">
                {label}
            </label>
            <div className="md:col-span-2">{children}</div>
        </div>
    );
}

function InputWithIcon({
    Icon, className = '', ...props
}: React.InputHTMLAttributes<HTMLInputElement> & { Icon: any }) {
    return (
        <div className="relative">
            <Icon className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
                {...props}
                className={`w-full rounded-md border border-gray-300 pl-9 pr-3 py-2 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60 ${className}`}
            />
        </div>
    );
}

function TextareaWithIcon({
    Icon, className = '', ...props
}: React.TextareaHTMLAttributes<HTMLTextAreaElement> & { Icon: any }) {
    return (
        <div className="relative">
            <Icon className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-gray-400" />
            <textarea
                {...props}
                className={`w-full rounded-md border border-gray-300 pl-9 pr-3 py-2 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60 ${className}`}
            />
        </div>
    );
}

function SelectWithIcon({
    Icon, className = '', children, ...props
}: React.SelectHTMLAttributes<HTMLSelectElement> & { Icon: any }) {
    return (
        <div className="relative">
            <Icon className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <select
                {...props}
                className={`w-full rounded-md border border-gray-300 pl-9 pr-8 py-2 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60 ${className}`}
            >
                {children}
            </select>
        </div>
    );
}
// 1) Crea un "icono" de texto S/
function SolIcon({ className }: { className?: string }) {
    return (
        <span
            aria-hidden
            className={`${className} w-auto h-auto font-semibold text-gray-400 leading-none`}
        >
            S/
        </span>
    );
}

export default function PaymentsIndex() {
    const actionUrl = typeof route === "function" ? route("payments.store") : "/payments";

    const { data, setData, post, processing, errors, reset, progress, clearErrors } = useForm({
        email: "",
        dni: "",
        full_name: "",
        receipt_number: "",
        amount: "",
        details: "",
        project_id: null as number | null,
        mz_lote: "",
        file_1: null as File | null,
        file_2: null as File | null,
        file_3: null as File | null,
    });

    const [submitted, setSubmitted] = useState(false);
    const [fileErrors, setFileErrors] = useState<FileErrors>({});
    const { projects = [] } = usePage().props as { projects?: Project[] };

    const hasFileErrors = useMemo(
        () => Boolean(fileErrors.file_1 || fileErrors.file_2 || fileErrors.file_3),
        [fileErrors]
    );

    const onSubmit: React.FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        setSubmitted(false);

        // Evita enviar si hay errores de tamaño de archivo
        if (hasFileErrors) return;

        post(actionUrl, {
            forceFormData: true,
            onSuccess: () => {
                setSubmitted(true);
                reset(
                    "email",
                    "dni",
                    "full_name",
                    "receipt_number",
                    "amount",
                    "details",
                    "project_id",
                    "mz_lote",
                    "file_1",
                    "file_2",
                    "file_3"
                );
                setFileErrors({});
                clearErrors();
            },
        });
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const { name, value } = e.target;
        setData(name as keyof typeof data, value);
    };

    const handleFile =
        (name: "file_1" | "file_2" | "file_3") => (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0] ?? null;

            // limpia error previo
            setFileErrors((prev) => ({ ...prev, [name]: undefined }));

            if (file && file.size > MAX_FILE_BYTES) {
                // excede 1MB -> mostrar error y limpiar input
                setFileErrors((prev) => ({
                    ...prev,
                    [name]: "El archivo supera 1 MB. Elija un archivo más ligero.",
                }));
                e.target.value = ""; // limpia el file input
                setData(name, null as any);
                return;
            }

            setData(name, file as any);
        };

    return (
        <>
            <Head title="Registrar Pago" />

            <div
                className="min-h-screen flex items-center justify-center bg-cover bg-center"
                style={{ backgroundImage: "url('/logo/f_login.png')" }}
            >
                <div className="min-h-screen flex items-center justify-center py-1 w-full">
                    {/* Contenedor ancho 80% */}
                    <div className="w-3/5 px-5">
                        {/* Card sin transparencia, más ancho */}
                        <div className="bg-white shadow-2xl ring-1 ring-white/30 rounded-2xl p-6 sm:p-8 w-full mx-auto">

                            <h1 className="text-2xl font-semibold tracking-tight text-gray-900 mb-1">
                                REGISTRO DE PAGOS - AYBARCORP
                            </h1>
                            <p className="text-sm text-gray-700 mb-6">
                                Formulario para registrar un nuevo pago. Será notificado al Email.
                            </p>

                            {/* Mensaje de éxito local */}
                            {submitted && (
                                <div className="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                                    Pago registrado correctamente y se notificó a su correo.
                                </div>
                            )}

                            {/* Barra de carga */}
                            {(processing || progress?.percentage) && (
                                <div className="mb-4">
                                    <div className="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                                        <div
                                            className="h-2 bg-blue-600 transition-all"
                                            style={{ width: `${progress?.percentage ?? 10}%` }}
                                        />
                                    </div>
                                    <div className="mt-1 text-right text-xs text-gray-600">
                                        {Math.round(progress?.percentage ?? 0)}%
                                    </div>
                                </div>
                            )}

                            <form onSubmit={onSubmit} className="space-y-8">

                                <section>
                                    <h2 className="text-base font-semibold mb-4
               bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/10
               border border-blue-200/70 dark:border-blue-800/40
               rounded-xl px-4 py-2 shadow-sm text-blue-900 dark:text-blue-100">
                                        INFORMACIÓN DEL TITULAR
                                    </h2>

                                    <div className="space-y-1">
                                        <FormRow id="email" label="E-mail">
                                            <InputWithIcon
                                                Icon={Mail}
                                                id="email"
                                                name="email"
                                                type="email"
                                                placeholder="cliente@email.com"
                                                value={data.email}
                                                onChange={handleChange}
                                                required
                                                disabled={processing}
                                            />
                                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                        </FormRow>

                                        <FormRow id="dni" label="DNI">
                                            <InputWithIcon
                                                Icon={IdCard}
                                                id="dni"
                                                name="dni"
                                                placeholder="Documento de identidad / CE"
                                                value={data.dni}
                                                onChange={handleChange}
                                                required
                                                disabled={processing}
                                            />
                                            {errors.dni && <p className="mt-1 text-sm text-red-600">{errors.dni}</p>}
                                        </FormRow>

                                        <FormRow id="full_name" label="Nombres y apellidos">
                                            <InputWithIcon
                                                Icon={User}
                                                id="full_name"
                                                name="full_name"
                                                placeholder="Nombre completo del cliente"
                                                value={data.full_name}
                                                onChange={handleChange}
                                                required
                                                disabled={processing}
                                            />
                                            {errors.full_name && <p className="mt-1 text-sm text-red-600">{errors.full_name}</p>}
                                        </FormRow>
                                    </div>
                                </section>

                                {/* Información del Pago */}
                                <section>
                                    <h2 className="text-base font-semibold mb-4
               bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/10
               border border-blue-200/70 dark:border-blue-800/40
               rounded-xl px-4 py-2 shadow-sm text-blue-900 dark:text-blue-100">
                                        INFORMACIÓN DEL PAGO
                                    </h2>


                                    <div className="space-y-1">
                                        <FormRow id="receipt_number" label="N° de comprobante">
                                            <InputWithIcon
                                                Icon={ReceiptText}
                                                id="receipt_number"
                                                name="receipt_number"
                                                placeholder="Boleta / Factura / N°"
                                                value={data.receipt_number}
                                                onChange={handleChange}
                                                disabled={processing}
                                            />
                                            {errors.receipt_number && <p className="mt-1 text-sm text-red-600">{errors.receipt_number}</p>}
                                        </FormRow>

                                        <FormRow id="amount" label="Monto">
                                            <InputWithIcon
                                                Icon={SolIcon}            // 👈 aquí va el prefijo S/
                                                id="amount"
                                                name="amount"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                                value={data.amount}
                                                onChange={handleChange}
                                                required
                                                disabled={processing}
                                            />


                                            {errors.amount && <p className="mt-1 text-sm text-red-600">{errors.amount}</p>}
                                        </FormRow>

                                        <FormRow id="details" label="Comentarios">
                                            <TextareaWithIcon
                                                Icon={FileText}
                                                id="details"
                                                name="details"
                                                rows={4}
                                                placeholder="Detalles adicionales (opcional)"
                                                value={data.details}
                                                onChange={handleChange}
                                                disabled={processing}
                                            />
                                            {errors.details && <p className="mt-1 text-sm text-red-600">{errors.details}</p>}
                                        </FormRow>
                                    </div>
                                </section>

                                {/* Información del Proyecto */}
                                <section>

                                    <h2 className="text-base font-medium text-gray-900 mb-4">INFORMACIÓN DEL PROYECTO</h2>
                                    <div className="space-y-1">
                                        <FormRow id="project_id" label="Proyecto">
                                            <SelectWithIcon
                                                Icon={Folder}
                                                id="project_id"
                                                name="project_id"
                                                value={String(data.project_id ?? '')}
                                                onChange={(e) => setData('project_id', e.target.value ? Number(e.target.value) : null)}
                                                required
                                                disabled={processing}
                                            >
                                                <option value="">Seleccione un proyecto</option>
                                                {projects.map((p) => (
                                                    <option key={p.id_proyecto} value={p.id_proyecto}>
                                                        {p.descripcion}
                                                    </option>
                                                ))}
                                            </SelectWithIcon>
                                            {errors.project_id && <p className="mt-1 text-sm text-red-600">{errors.project_id}</p>}
                                        </FormRow>

                                        <FormRow id="mz_lote" label="MZ - Lote">
                                            <InputWithIcon
                                                Icon={MapPin}
                                                id="mz_lote"
                                                name="mz_lote"
                                                placeholder="Ej: MZ A - Lote 12"
                                                value={data.mz_lote}
                                                onChange={handleChange}
                                                disabled={processing}
                                            />
                                            {errors.mz_lote && <p className="mt-1 text-sm text-red-600">{errors.mz_lote}</p>}
                                        </FormRow>
                                    </div>
                                </section>

                                {/* Archivos adjuntos */}
                                <section>
                                    <h2 className="text-base font-medium text-gray-900 mb-4">ARCHIVOS ADJUNTOS</h2>
                                    <div className="space-y-1">
                                        {(['file_1', 'file_2', 'file_3'] as const).map((field, i) => (
                                            <FormRow key={field} id={field} label={`Archivo ${i + 1} (máx. 1 MB)`}>
                                                <div className="relative">
                                                    <Paperclip className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                                                    <input
                                                        id={field}
                                                        name={field}
                                                        type="file"
                                                        className="block w-full rounded-md border border-gray-300 pl-9 pr-3 py-2 text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-blue-700 hover:file:bg-blue-100 disabled:opacity-60"
                                                        onChange={handleFile(field)}
                                                        accept="image/*,application/pdf"
                                                        disabled={processing}
                                                    />
                                                </div>
                                                {(fileErrors[field] || (errors as any)[field]) && (
                                                    <p className="mt-1 text-sm text-red-600">
                                                        {fileErrors[field] ?? (errors as any)[field]}
                                                    </p>
                                                )}
                                            </FormRow>
                                        ))}

                                        {progress && typeof progress.percentage === 'number' && (
                                            <div className="text-sm text-gray-800 md:col-start-2">
                                                Subiendo… {progress.percentage}%
                                            </div>
                                        )}
                                    </div>
                                </section>

                                {/* Botones */}
                                <div className="flex items-center gap-3 pt-2">
                                    <button
                                        type="submit"
                                        disabled={processing || hasFileErrors}
                                        className="inline-flex items-center justify-center rounded-md bg-blue-600 px-5 py-2.5 font-medium text-white shadow hover:bg-blue-700 disabled:opacity-60"
                                        title={hasFileErrors ? 'Corrige los errores de archivos' : undefined}
                                    >
                                        {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                        {processing ? 'Guardando…' : 'Guardar pago'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { reset(); setFileErrors({}); clearErrors(); }}
                                        disabled={processing}
                                        className="inline-flex items-center justify-center rounded-md bg-gray-100 px-5 py-2.5 font-medium text-gray-900 shadow hover:bg-gray-200 disabled:opacity-60"
                                    >
                                        Limpiar
                                    </button>
                                </div>
                            </form>
                            <br />
                            {submitted && (
                                <div className="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                                    Pago registrado correctamente y se notificó a su correo.
                                </div>
                            )}
                            <br />
                        </div>

                    </div>
                </div>
            </div>
        </>


    );
}
