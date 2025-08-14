import React, { useMemo, useState } from "react";
import { Head, useForm, usePage } from "@inertiajs/react";
import { LoaderCircle } from "lucide-react";

type Project = { id_proyecto: number; descripcion: string };

type FileErrors = {
  file_1?: string;
  file_2?: string;
  file_3?: string;
};

const MAX_FILE_BYTES = 1 * 1024 * 1024; // 1 MB

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

      {/* Fondo a pantalla completa */}
      <div className="fixed inset-0 -z-10">
        <img src="logo/f_login.png" alt="" className="h-full w-full object-cover" />
        {/* Overlay (oscurecer un poco para mejorar contraste) */}
        <div className="absolute inset-0 bg-black/40" />
      </div>

      {/* Contenido sobre el fondo */}
      <div className="min-h-screen flex items-center justify-center py-10">
        <div className="w-full max-w-3xl mx-auto px-4">
          {/* Card con efecto glass */}
          <div className="bg-white/80 backdrop-blur-md shadow-2xl ring-1 ring-white/30 rounded-2xl p-6 sm:p-8">
            <h1 className="text-2xl font-semibold tracking-tight text-gray-900 mb-1">
              Registro de Pagos - AybarCorp
            </h1>
            <p className="text-sm text-gray-700 mb-6">
              Formulario para registrar un nuevo pago. Será notificado al Email.
            </p>

            {/* Mensaje de éxito local */}
            {submitted && (
              <div className="mb-6 rounded-lg border border-green-200 bg-green-50/90 px-4 py-3 text-green-800">
                Pago registrado correctamente y se notificó a su correo.
              </div>
            )}

            {/* Barra de carga (mientras sube archivos) */}
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

            <form onSubmit={onSubmit} className="space-y-6">
              {/* Información del Cliente */}
              <section>
                <h2 className="text-base font-medium text-gray-900 mb-4">
                  Información del Cliente
                </h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-900 mb-1">
                      Correo electrónico
                    </label>
                    <input
                      id="email"
                      name="email"
                      type="email"
                      className="w-full rounded-xl border-gray-300 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60"
                      placeholder="cliente@email.com"
                      value={data.email}
                      onChange={handleChange}
                      required
                      disabled={processing}
                    />
                    {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                  </div>
                  <div>
                    <label htmlFor="dni" className="block text-sm font-medium text-gray-900 mb-1">
                      DNI
                    </label>
                    <input
                      id="dni"
                      name="dni"
                      type="text"
                      className="w-full rounded-xl border-gray-300 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60"
                      placeholder="Documento de identidad"
                      value={data.dni}
                      onChange={handleChange}
                      required
                      disabled={processing}
                    />
                    {errors.dni && <p className="mt-1 text-sm text-red-600">{errors.dni}</p>}
                  </div>
                  <div className="sm:col-span-2">
                    <label
                      htmlFor="full_name"
                      className="block text-sm font-medium text-gray-900 mb-1"
                    >
                      Nombres y apellidos
                    </label>
                    <input
                      id="full_name"
                      name="full_name"
                      type="text"
                      className="w-full rounded-xl border-gray-300 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60"
                      placeholder="Nombre completo del cliente"
                      value={data.full_name}
                      onChange={handleChange}
                      required
                      disabled={processing}
                    />
                    {errors.full_name && (
                      <p className="mt-1 text-sm text-red-600">{errors.full_name}</p>
                    )}
                  </div>
                </div>
              </section>

              {/* Información del Pago */}
              <section>
                <h2 className="text-base font-medium text-gray-900 mb-4">Información del Pago</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label
                      htmlFor="receipt_number"
                      className="block text-sm font-medium text-gray-900 mb-1"
                    >
                      N° de comprobante
                    </label>
                    <input
                      id="receipt_number"
                      name="receipt_number"
                      type="text"
                      className="w-full rounded-xl border-gray-300 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60"
                      placeholder="Boleta / Factura / N°"
                      value={data.receipt_number}
                      onChange={handleChange}
                      disabled={processing}
                    />
                    {errors.receipt_number && (
                      <p className="mt-1 text-sm text-red-600">{errors.receipt_number}</p>
                    )}
                  </div>
                  <div>
                    <label htmlFor="amount" className="block text-sm font-medium text-gray-900 mb-1">
                      Monto
                    </label>
                    <input
                      id="amount"
                      name="amount"
                      type="number"
                      step="0.01"
                      min="0"
                      className="w-full rounded-xl border-gray-300 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60"
                      placeholder="0.00"
                      value={data.amount}
                      onChange={handleChange}
                      required
                      disabled={processing}
                    />
                    {errors.amount && <p className="mt-1 text-sm text-red-600">{errors.amount}</p>}
                  </div>
                  <div className="sm:col-span-2">
                    <label htmlFor="details" className="block text-sm font-medium text-gray-900 mb-1">
                      Detalles
                    </label>
                    <textarea
                      id="details"
                      name="details"
                      rows={4}
                      className="w-full rounded-xl border-gray-300 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60"
                      placeholder="Detalles adicionales (opcional)"
                      value={data.details}
                      onChange={handleChange}
                      disabled={processing}
                    />
                    {errors.details && <p className="mt-1 text-sm text-red-600">{errors.details}</p>}
                  </div>
                </div>
              </section>

              {/* Información del Proyecto */}
              <section>
                <h2 className="text-base font-medium text-gray-900 mb-4">
                  Información del Proyecto
                </h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label
                      htmlFor="project_id"
                      className="block text-sm font-medium text-gray-900 mb-1"
                    >
                      Proyecto
                    </label>
                    <select
                      id="project_id"
                      name="project_id"
                      className="w-full rounded-xl border-gray-300 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60"
                      value={String(data.project_id ?? "")}
                      onChange={(e) =>
                        setData("project_id", e.target.value ? Number(e.target.value) : null)
                      }
                      required
                      disabled={processing}
                    >
                      <option value="">Seleccione un proyecto</option>
                      {projects.map((p) => (
                        <option key={p.id_proyecto} value={p.id_proyecto}>
                          {p.descripcion}
                        </option>
                      ))}
                    </select>
                    {errors.project_id && (
                      <p className="mt-1 text-sm text-red-600">{errors.project_id}</p>
                    )}
                  </div>
                  <div>
                    <label htmlFor="mz_lote" className="block text-sm font-medium text-gray-900 mb-1">
                      MZ - Lote
                    </label>
                    <input
                      id="mz_lote"
                      name="mz_lote"
                      type="text"
                      className="w-full rounded-xl border-gray-300 focus:border-blue-600 focus:ring-blue-600 disabled:opacity-60"
                      placeholder="Ej: MZ A - Lote 12"
                      value={data.mz_lote}
                      onChange={handleChange}
                      disabled={processing}
                    />
                    {errors.mz_lote && <p className="mt-1 text-sm text-red-600">{errors.mz_lote}</p>}
                  </div>
                </div>
              </section>

              {/* Archivos adjuntos */}
   <section>
  <h2 className="text-base font-medium text-gray-900 mb-4">
    Archivos adjuntos
  </h2>
  
  <div className="space-y-4">
    <div>
      <label
        htmlFor="file_1"
        className="block text-sm font-medium text-gray-900 mb-1"
      >
        Archivo 1 (máx. 1 MB)
      </label>
      <input
        id="file_1"
        name="file_1"
        type="file"
        className="block w-full text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-blue-700 hover:file:bg-blue-100 disabled:opacity-60"
        onChange={handleFile("file_1")}
        accept="image/*,application/pdf"
        disabled={processing}
      />
      {(fileErrors.file_1 || errors.file_1) && (
        <p className="mt-1 text-sm text-red-600">
          {fileErrors.file_1 ?? (errors.file_1 as string)}
        </p>
      )}
    </div>

    <div>
      <label
        htmlFor="file_2"
        className="block text-sm font-medium text-gray-900 mb-1"
      >
        Archivo 2 (máx. 1 MB)
      </label>
      <input
        id="file_2"
        name="file_2"
        type="file"
        className="block w-full text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-blue-700 hover:file:bg-blue-100 disabled:opacity-60"
        onChange={handleFile("file_2")}
        accept="image/*,application/pdf"
        disabled={processing}
      />
      {(fileErrors.file_2 || errors.file_2) && (
        <p className="mt-1 text-sm text-red-600">
          {fileErrors.file_2 ?? (errors.file_2 as string)}
        </p>
      )}
    </div>

    <div>
      <label
        htmlFor="file_3"
        className="block text-sm font-medium text-gray-900 mb-1"
      >
        Archivo 3 (máx. 1 MB)
      </label>
      <input
        id="file_3"
        name="file_3"
        type="file"
        className="block w-full text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-blue-700 hover:file:bg-blue-100 disabled:opacity-60"
        onChange={handleFile("file_3")}
        accept="image/*,application/pdf"
        disabled={processing}
      />
      {(fileErrors.file_3 || errors.file_3) && (
        <p className="mt-1 text-sm text-red-600">
          {fileErrors.file_3 ?? (errors.file_3 as string)}
        </p>
      )}
    </div>
  </div>

  {progress && typeof progress.percentage === "number" && (
    <div className="mt-3 text-sm text-gray-800">
      Subiendo… {progress.percentage}%
    </div>
  )}
</section>


              <div className="flex items-center gap-3 pt-2">
                <button
                  type="submit"
                  disabled={processing || hasFileErrors}
                  className="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-5 py-2.5 font-medium text-white shadow hover:bg-blue-700 disabled:opacity-60"
                  title={hasFileErrors ? "Corrige los errores de archivos" : undefined}
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
                  className="inline-flex items-center justify-center rounded-2xl bg-gray-100 px-5 py-2.5 font-medium text-gray-900 shadow hover:bg-gray-200 disabled:opacity-60"
                >
                  Limpiar
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </>
  );
}
