import React, { useEffect, useMemo, useState } from "react";
import { useForm, usePage } from "@inertiajs/react";
import {
  LoaderCircle,
  Mail,
  IdCard,
  User,
  ReceiptText,
  FileText,
  Folder,
  MapPin,
  Paperclip,
  Calendar,
  Hash,
  CreditCard,
  Building2,
  Store,
  Banknote,
} from "lucide-react";
import LimitedInputWithIcon from "./LimitedInputWithIcon";

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

function SelectWithIcon({
  Icon,
  className = "",
  children,
  ...props
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

function InputWithIcon({
  Icon,
  className = "",
  ...props
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

function SolIcon({ className }: { className?: string }) {
  return <span aria-hidden className={`${className} font-semibold text-gray-400`}>S/</span>;
}

/** === Canales (valor interno -> etiqueta visible) === */
const CHANNEL_OPTIONS = [
  { value: "", label: "Seleccione un medio" },
  { value: "yape_app", label: "Yape (App)" },
  { value: "link_niubiz", label: "Link de Niubiz" },
  { value: "app_pagos_servicio", label: "APP - Pagos de servicio" },
  { value: "agente_bcp_pagos_servicio", label: "Agente BCP - Pagos de servicio" },
  { value: "pago_presencial", label: "Pago Presencial" },
  { value: "presencial_banco", label: "Presencial en Banco" },
  { value: "app_interbancario", label: "APP Interbancario" },
  { value: "tranferencia_interbancaria", label: "Transferencia Interbancaria" },
  { value: "transferencia_agente_internacional", label: "Transferencia Agente Internacional" },
  { value: "pago_directo_bcp", label: "Pago directo a BCP" },
] as const;

/** === Campos por canal (requeridos/visibles) ===
 * Mantengo mínimos imprescindibles (según lo que definimos en backend):
 * - Yape / Transferencias / Pagos de servicio / Presenciales: operation_number requerido
 * - Niubiz: transaction_code requerido; sale_id/company_name/commerce_name opcionales
 */
const FIELDS_BY_CHANNEL: Record<
  string,
  { name: keyof FormDataShape; required?: boolean }[]
> = {
  yape_app: [{ name: "operation_number", required: true }, { name: "account_last_digits" }],
  app_pagos_servicio: [{ name: "operation_number", required: true }],
  agente_bcp_pagos_servicio: [{ name: "operation_number", required: true }],
  pago_presencial: [{ name: "operation_number", required: true }],
  presencial_banco: [{ name: "operation_number", required: true }],
  app_interbancario: [
    { name: "operation_number", required: true },
    { name: "destination_account" },
    { name: "currency" },
  ],
  tranferencia_interbancaria: [
    { name: "operation_number", required: true },
    { name: "destination_account" },
    { name: "currency" },
  ],
  transferencia_agente_internacional: [
    { name: "operation_number", required: true },
    { name: "destination_account" },
    { name: "currency" },
  ],
  pago_directo_bcp: [
    { name: "operation_number", required: true },
    { name: "account_number" },
    { name: "currency" },
  ],
  link_niubiz: [
    { name: "transaction_code", required: true },
    { name: "sale_id" },
    { name: "company_name" },
    { name: "commerce_name" },
    { name: "currency" },
  ],
};

type FormDataShape = {
  email: string;
  dni: string;
  full_name: string;
  receipt_number: string;
  amount: string;
  details: string;
  project_id: number | null;
  mz_lote: string;
  date: string;
  code_client: string;
  file_1: File | null;

  // NUEVOS (canal y campos dinámicos)
  channel: string;
  operation_number: string;
  transaction_code: string;
  sale_id: string;
  company_name: string;
  commerce_name: string;
  account_holder: string;
  account_number: string;
  destination_account: string;
  salary_account: string;
  account_last_digits: string;
  currency: string;
  evidence: string;
};

export default function PaymentForm() {
  const actionUrl = typeof route === "function" ? route("payments.store") : "/payments";
  const { projects = [] } = usePage().props as { projects?: Project[] };

  const { data, setData, post, processing, errors, reset, progress, clearErrors } = useForm<FormDataShape>({
    email: "",
    dni: "",
    full_name: "",
    receipt_number: "",
    amount: "",
    details: "",
    project_id: null,
    mz_lote: "",
    date: "", // NUEVO
    code_client: "", // NUEVO
    file_1: null,

    // Canal + campos dinámicos
    channel: "",
    operation_number: "",
    transaction_code: "",
    sale_id: "",
    company_name: "",
    commerce_name: "",
    account_holder: "",
    account_number: "",
    destination_account: "",
    salary_account: "",
    account_last_digits: "",
    currency: "",
    evidence: "",
  });

  const [submitted, setSubmitted] = useState(false);
  const [fileErrors, setFileErrors] = useState<FileErrors>({});

  const hasFileErrors = useMemo(() => Boolean(fileErrors.file_1), [fileErrors]);

  const visibleFieldNames = useMemo(() => {
    if (!data.channel) return [] as (keyof FormDataShape)[];
    return (FIELDS_BY_CHANNEL[data.channel] ?? []).map((f) => f.name);
  }, [data.channel]);

  const requiredFields = useMemo(() => {
    if (!data.channel) return new Set<keyof FormDataShape>();
    const reqs = (FIELDS_BY_CHANNEL[data.channel] ?? []).filter((f) => f.required).map((f) => f.name);
    return new Set(reqs);
  }, [data.channel]);

  // Limpia campos que dejan de aplicar al cambiar canal
  useEffect(() => {
    const allDynamicKeys: (keyof FormDataShape)[] = [
      "operation_number",
      "transaction_code",
      "sale_id",
      "company_name",
      "commerce_name",
      "account_holder",
      "account_number",
      "destination_account",
      "salary_account",
      "account_last_digits",
      "currency",
      "evidence",
    ];
    const keep = new Set(visibleFieldNames);
    const cleaned: Partial<FormDataShape> = {};
    for (const k of allDynamicKeys) {
      if (!keep.has(k) && (data as any)[k]) {
        (cleaned as any)[k] = "";
      }
    }
    if (Object.keys(cleaned).length) setData((prev) => ({ ...prev, ...cleaned }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data.channel]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    setData(e.target.name as keyof FormDataShape, e.target.value as any);
  };

  const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    setFileErrors({});

    if (file && file.size > MAX_FILE_BYTES) {
      setFileErrors({ file_1: "El archivo supera 1 MB. Elija un archivo más ligero." });
      e.target.value = "";
      setData("file_1", null);
      return;
    }
    setData("file_1", file);
  };

  const onSubmit: React.FormEventHandler<HTMLFormElement> = (e) => {
    e.preventDefault();
    if (hasFileErrors) return;

    // Validación HTML5 básica para marcados required dinámicos:
    // (Si prefieres, aquí puedes hacer una validación manual adicional)

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
          "date",
          "code_client",
          "file_1",
          // dinámicos
          "channel",
          "operation_number",
          "transaction_code",
          "sale_id",
          "company_name",
          "commerce_name",
          "account_holder",
          "account_number",
          "destination_account",
          "salary_account",
          "account_last_digits",
          "currency",
          "evidence"
        );
        clearErrors();
        setFileErrors({});
      },
    });
  };

  const renderDynamicField = (name: keyof FormDataShape) => {
    const required = requiredFields.has(name);
    const commonProps = {
      name,
      value: (data as any)[name] ?? "",
      onChange: handleChange,
      disabled: processing,
      required,
    };

    switch (name) {
      case "operation_number":
        return (
          <FormRow id="operation_number" label="N° de Operación">
            <LimitedInputWithIcon Icon={ReceiptText} id="operation_number" maxLength={100} {...commonProps} />
            {(errors as any).operation_number && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).operation_number}</p>
            )}
          </FormRow>
        );
      case "transaction_code":
        return (
          <FormRow id="transaction_code" label="Código de Transacción">
            <LimitedInputWithIcon Icon={Hash} id="transaction_code" maxLength={100} {...commonProps} />
            {(errors as any).transaction_code && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).transaction_code}</p>
            )}
          </FormRow>
        );
      case "sale_id":
        return (
          <FormRow id="sale_id" label="ID de Venta (opcional)">
            <LimitedInputWithIcon Icon={Hash} id="sale_id" maxLength={100} {...commonProps} />
            {(errors as any).sale_id && <p className="mt-1 text-sm text-red-600">{(errors as any).sale_id}</p>}
          </FormRow>
        );
      case "company_name":
        return (
          <FormRow id="company_name" label="Empresa / Razón Social (opcional)">
            <LimitedInputWithIcon Icon={Building2} id="company_name" maxLength={150} {...commonProps} />
            {(errors as any).company_name && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).company_name}</p>
            )}
          </FormRow>
        );
      case "commerce_name":
        return (
          <FormRow id="commerce_name" label="Comercio (opcional)">
            <LimitedInputWithIcon Icon={Store} id="commerce_name" maxLength={150} {...commonProps} />
            {(errors as any).commerce_name && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).commerce_name}</p>
            )}
          </FormRow>
        );
      case "account_holder":
        return (
          <FormRow id="account_holder" label="Titular de la cuenta (opcional)">
            <LimitedInputWithIcon Icon={User} id="account_holder" maxLength={150} {...commonProps} />
            {(errors as any).account_holder && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).account_holder}</p>
            )}
          </FormRow>
        );
      case "account_number":
        return (
          <FormRow id="account_number" label="Cuenta bancaria (opcional)">
            <LimitedInputWithIcon Icon={Hash} id="account_number" maxLength={150} {...commonProps} />
            {(errors as any).account_number && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).account_number}</p>
            )}
          </FormRow>
        );
      case "destination_account":
        return (
          <FormRow id="destination_account" label="Cuenta de destino (opcional)">
            <LimitedInputWithIcon Icon={Hash} id="destination_account" maxLength={150} {...commonProps} />
            {(errors as any).destination_account && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).destination_account}</p>
            )}
          </FormRow>
        );
      case "salary_account":
        return (
          <FormRow id="salary_account" label="Cuenta sueldo (opcional)">
            <LimitedInputWithIcon Icon={Hash} id="salary_account" maxLength={150} {...commonProps} />
            {(errors as any).salary_account && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).salary_account}</p>
            )}
          </FormRow>
        );
      case "account_last_digits":
        return (
          <FormRow id="account_last_digits" label="Últimos dígitos (opcional)">
            <LimitedInputWithIcon Icon={Hash} id="account_last_digits" maxLength={20} {...commonProps} />
            {(errors as any).account_last_digits && (
              <p className="mt-1 text-sm text-red-600">{(errors as any).account_last_digits}</p>
            )}
          </FormRow>
        );
      case "currency":
        return (
          <FormRow id="currency" label="Moneda (opcional)">
            <SelectWithIcon Icon={Banknote} id="currency" {...(commonProps as any)}>
              <option value="">Seleccione moneda</option>
              <option value="PEN">PEN - Soles</option>
              <option value="USD">USD - Dólares</option>
            </SelectWithIcon>
            {(errors as any).currency && <p className="mt-1 text-sm text-red-600">{(errors as any).currency}</p>}
          </FormRow>
        );
      case "evidence":
        return (
          <FormRow id="evidence" label="Evidencia / Observación (opcional)">
            <LimitedInputWithIcon Icon={FileText} id="evidence" maxLength={500} {...commonProps} />
            {(errors as any).evidence && <p className="mt-1 text-sm text-red-600">{(errors as any).evidence}</p>}
          </FormRow>
        );
      default:
        return null;
    }
  };

  return (
    <form onSubmit={onSubmit} className="space-y-8">
      {/* Titular */}
      <section>
        <h2 className="text-base font-semibold mb-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2">
          INFORMACIÓN DEL TITULAR
        </h2>

        {/* Email (máx 150) */}
        <FormRow id="email" label="E-mail">
          <LimitedInputWithIcon Icon={Mail} name="email" value={data.email} onChange={handleChange} maxLength={150} />
          {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
        </FormRow>

        {/* DNI (máx 20) */}
        <FormRow id="dni" label="DNI">
          <LimitedInputWithIcon Icon={IdCard} name="dni" value={data.dni} onChange={handleChange} maxLength={20} />
          {errors.dni && <p className="mt-1 text-sm text-red-600">{errors.dni}</p>}
        </FormRow>

        {/* Nombre completo (máx 200) */}
        <FormRow id="full_name" label="Nombres y apellidos">
          <LimitedInputWithIcon Icon={User} name="full_name" value={data.full_name} onChange={handleChange} maxLength={200} />
          {errors.full_name && <p className="mt-1 text-sm text-red-600">{errors.full_name}</p>}
        </FormRow>
      </section>

      {/* Pago */}
      <section>
        <h2 className="text-base font-semibold mb-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2">
          INFORMACIÓN DEL PAGO
        </h2>

        {/* Medio de pago (canal) */}
      {/* Medio de pago (canal) */}
<FormRow id="channel" label="Medio de Pago">
  <SelectWithIcon
    Icon={CreditCard}
    id="channel"
    name="channel"
    value={data.channel}
    onChange={handleChange}
    disabled={processing}
  >
    {CHANNEL_OPTIONS.map((opt) => (
      <option key={opt.value} value={opt.value}>
        {opt.label}
      </option>
    ))}
  </SelectWithIcon>
  {(errors as any).channel && (
    <p className="mt-1 text-sm text-red-600">{(errors as any).channel}</p>
  )}
</FormRow>

{/* (opcional) Importe detectado por OCR */}
<FormRow id="amount" label="Importe detectado">
  <InputWithIcon
    Icon={SolIcon}
    id="amount"
    name="amount"
    type="number"
    step="0.01"
    min="0"
    value={data.amount}
    readOnly // 👈 ya no editable
    disabled
  />
</FormRow>

        {/* N° operación (legacy, si lo usas genérico) */}
        <FormRow id="receipt_number" label="N° de Operación (alterno)">
          <LimitedInputWithIcon
            Icon={ReceiptText}
            name="receipt_number"
            value={data.receipt_number}
            onChange={handleChange}
            maxLength={100}
          />
          {errors.receipt_number && <p className="mt-1 text-sm text-red-600">{errors.receipt_number}</p>}
        </FormRow>

        {/* Importe */}
        <FormRow id="amount" label="Importe del pago">
          <InputWithIcon
            Icon={SolIcon}
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

        {/* Fecha de pago */}
        <FormRow id="date" label="Fecha de pago">
          <InputWithIcon
            Icon={Calendar}
            id="date"
            name="date"
            type="date"
            value={data.date}
            onChange={handleChange}
            disabled={processing}
          />
          {errors.date && <p className="mt-1 text-sm text-red-600">{errors.date}</p>}
        </FormRow>

        {/* Código de cliente */}
        <FormRow id="code_client" label="Código de cliente">
          <LimitedInputWithIcon Icon={Hash} name="code_client" value={data.code_client} onChange={handleChange} maxLength={100} />
          {errors.code_client && <p className="mt-1 text-sm text-red-600">{errors.code_client}</p>}
        </FormRow>
      </section>

      {/* Datos dinámicos por canal */}
      {data.channel && (
        <section>
          <h2 className="text-base font-semibold mb-4 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2">
            DATOS SEGÚN MEDIO DE PAGO
          </h2>

          {/* Renderiza sólo los campos mapeados para el canal */}
          {visibleFieldNames.length === 0 ? (
            <p className="text-sm text-gray-600">
              Este medio no requiere datos adicionales. Adjunta el voucher y continúa.
            </p>
          ) : (
            <div className="space-y-4">
              {visibleFieldNames.map((fname) => (
                <div key={fname}>{renderDynamicField(fname)}</div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* Proyecto */}
      <section>
        <h2 className="text-base font-semibold mb-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2">
          INFORMACIÓN DEL PROYECTO
        </h2>
        <FormRow id="project_id" label="Proyecto">
          <SelectWithIcon
            Icon={Folder}
            id="project_id"
            name="project_id"
            value={String(data.project_id ?? "")}
            onChange={(e) => setData("project_id", e.target.value ? Number(e.target.value) : null)}
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

        {/* MZ - Lote */}
        <FormRow id="mz_lote" label="MZ - Lote">
          <LimitedInputWithIcon Icon={MapPin} name="mz_lote" value={data.mz_lote} onChange={handleChange} maxLength={50} />
          {errors.mz_lote && <p className="mt-1 text-sm text-red-600">{errors.mz_lote}</p>}
        </FormRow>
      </section>

      {/* Archivo único */}
      <section>
        <h2 className="text-base font-semibold mb-4 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2">
          ARCHIVO ADJUNTO
        </h2>
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
              required
            />
          </div>
          {(fileErrors.file_1 || (errors as any).file_1) && (
            <p className="mt-1 text-sm text-red-600">{fileErrors.file_1 ?? (errors as any).file_1}</p>
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
          onClick={() => {
            reset();
            setFileErrors({});
            clearErrors();
          }}
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
