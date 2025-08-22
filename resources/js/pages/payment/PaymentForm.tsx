import React, { useMemo, useState } from "react";
import { useForm, usePage } from "@inertiajs/react";
import { LoaderCircle, Mail, IdCard, User, ReceiptText, FileText, Folder, MapPin, Paperclip } from "lucide-react";

type Project = { id_proyecto: number; descripcion: string };
type FileErrors = { file_1?: string };

const MAX_FILE_BYTES = 1 * 1024 * 1024; // 1 MB

function FormRow({ id, label, children }: { id: string; label: string; children: React.ReactNode }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
            <label htmlFor={id} className="text-sm font-medium text-gray-900 md:text-left">
                {label}
            </label>
            <div className="md:col-span-2">{children}</div>
        </div>
    );
}

function InputWithIcon({ Icon, className = '', ...props }: React.InputHTMLAttributes<HTMLInputElement> & { Icon: any }) {
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

function TextareaWithIcon({ Icon, className = '', ...props }: React.TextareaHTMLAttributes<HTMLTextAreaElement> & { Icon: any }) {
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

function SelectWithIcon({ Icon, className = '', children, ...props }: React.SelectHTMLAttributes<HTMLSelectElement> & { Icon: any }) {
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

function SolIcon({ className }: { className?: string }) {
    return <span aria-hidden className={`${className} font-semibold text-gray-400`}>S/</span>;
}

export default function PaymentForm() {
    const actionUrl = typeof route === "function" ? route("payments.store") : "/payments";
    const { projects = [] } = usePage().props as { projects?: Project[] };

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
    });

    const [submitted, setSubmitted] = useState(false);
    const [fileErrors, setFileErrors] = useState<FileErrors>({});

    const hasFileErrors = useMemo(() => Boolean(fileErrors.file_1), [fileErrors]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        setData(e.target.name as keyof typeof data, e.target.value);
    };

    const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setFileErrors({});

        if (file && file.size > MAX_FILE_BYTES) {
            setFileErrors({ file_1: "El archivo supera 1 MB. Elija un archivo más ligero." });
            e.target.value = "";
            setData("file_1", null as any);
            return;
        }
        setData("file_1", file as any);
    };

    const onSubmit: React.FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        if (hasFileErrors) return;

        post(actionUrl, {
            forceFormData: true,
            onSuccess: () => {
                setSubmitted(true);
                reset("email","dni","full_name","receipt_number","amount","details","project_id","mz_lote","file_1");
                clearErrors();
                setFileErrors({});
            },
        });
    };

    return (
        <form onSubmit={onSubmit} className="space-y-8">
            {/* Titular */}
            <section>
                <h2 className="text-base font-semibold mb-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2">INFORMACIÓN DEL TITULAR</h2>
                <FormRow id="email" label="E-mail">
                    <InputWithIcon Icon={Mail} id="email" name="email" type="email" placeholder="cliente@email.com"
                        value={data.email} onChange={handleChange} required disabled={processing} />
                    {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                </FormRow>

                <FormRow id="dni" label="DNI">
                    <InputWithIcon Icon={IdCard} id="dni" name="dni" placeholder="Documento de identidad"
                        value={data.dni} onChange={handleChange} required disabled={processing} />
                    {errors.dni && <p className="mt-1 text-sm text-red-600">{errors.dni}</p>}
                </FormRow>

                <FormRow id="full_name" label="Nombres y apellidos">
                    <InputWithIcon Icon={User} id="full_name" name="full_name" placeholder="Nombre completo"
                        value={data.full_name} onChange={handleChange} required disabled={processing} />
                    {errors.full_name && <p className="mt-1 text-sm text-red-600">{errors.full_name}</p>}
                </FormRow>
            </section>

            {/* Pago */}
            <section>
                <h2 className="text-base font-semibold mb-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2">INFORMACIÓN DEL PAGO</h2>
                <FormRow id="receipt_number" label="N° de comprobante">
                    <InputWithIcon Icon={ReceiptText} id="receipt_number" name="receipt_number" placeholder="Boleta / Factura"
                        value={data.receipt_number} onChange={handleChange} disabled={processing} />
                </FormRow>

                <FormRow id="amount" label="Monto">
                    <InputWithIcon Icon={SolIcon} id="amount" name="amount" type="number" step="0.01" min="0"
                        placeholder="0.00" value={data.amount} onChange={handleChange} required disabled={processing} />
                    {errors.amount && <p className="mt-1 text-sm text-red-600">{errors.amount}</p>}
                </FormRow>

                <FormRow id="details" label="Comentarios">
                    <TextareaWithIcon Icon={FileText} id="details" name="details" rows={4} placeholder="Detalles adicionales"
                        value={data.details} onChange={handleChange} disabled={processing} />
                </FormRow>
            </section>

            {/* Proyecto */}
            <section>
                <h2 className="text-base font-semibold mb-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2">INFORMACIÓN DEL PROYECTO</h2>
                <FormRow id="project_id" label="Proyecto">
                    <SelectWithIcon Icon={Folder} id="project_id" name="project_id"
                        value={String(data.project_id ?? "")}
                        onChange={(e) => setData("project_id", e.target.value ? Number(e.target.value) : null)}
                        required disabled={processing}>
                        <option value="">Seleccione un proyecto</option>
                        {projects.map((p) => (
                            <option key={p.id_proyecto} value={p.id_proyecto}>{p.descripcion}</option>
                        ))}
                    </SelectWithIcon>
                </FormRow>

                <FormRow id="mz_lote" label="MZ - Lote">
                    <InputWithIcon Icon={MapPin} id="mz_lote" name="mz_lote" placeholder="Ej: MZ A - Lote 12"
                        value={data.mz_lote} onChange={handleChange} disabled={processing} />
                </FormRow>
            </section>

            {/* Archivo único */}
            <section>
                <h2 className="text-base font-semibold mb-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2">ARCHIVO ADJUNTO</h2>
                <FormRow id="file_1" label="Archivo (máx. 1 MB)">
                    <div className="relative">
                        <Paperclip className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            id="file_1"
                            name="file_1"
                            type="file"
                            className="block w-full rounded-md border border-gray-300 pl-9 pr-3 py-2 text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-blue-700 hover:file:bg-blue-100 disabled:opacity-60"
                            onChange={handleFile}
                            accept="image/*,application/pdf"
                            disabled={processing}
                        />
                    </div>
                    {(fileErrors.file_1 || errors.file_1) && (
                        <p className="mt-1 text-sm text-red-600">{fileErrors.file_1 ?? errors.file_1}</p>
                    )}
                </FormRow>
            </section>

            {/* Botones */}
            <div className="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    disabled={processing || hasFileErrors}
                    className="inline-flex items-center justify-center rounded-md bg-blue-600 px-5 py-2.5 font-medium text-white shadow hover:bg-blue-700 disabled:opacity-60"
                >
                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    {processing ? "Guardando…" : "Guardar pago"}
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

            {submitted && (
                <div className="mt-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                    Pago registrado correctamente y se notificó a su correo.
                </div>
            )}
        </form>
    );
}
