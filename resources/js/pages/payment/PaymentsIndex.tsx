import { useState } from "react";
import { Head } from "@inertiajs/react";
import PaymentForm from "./PaymentForm";
import axios from "axios";

export default function PaymentsIndex() {
    const [step, setStep] = useState<"upload" | "loading" | "form">("upload");
    const [formData, setFormData] = useState<any>(null);
    const [progress, setProgress] = useState<number>(0);

    const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // armar FormData para enviar al backend
        const data = new FormData();
        data.append("file_1", file);

        setStep("loading");
        setProgress(10);

        try {
            // petición al backend (endpoint que llame a recognizeVoucher)
            const res = await axios.post("/vouchers/recognize", data, {
                headers: { "Content-Type": "multipart/form-data" },
                onUploadProgress: (evt) => {
                    if (evt.total) {
                        const percent = Math.round((evt.loaded * 100) / evt.total);
                        setProgress(percent < 95 ? percent : 95);
                    }
                },
            });

            // simular un pequeño delay para mostrar barra completa
            setTimeout(() => {
                setProgress(100);
                setFormData(res.data); // datos precargados del backend
                setStep("form");
            }, 1200);
        } catch (err) {
            console.error("❌ Error al reconocer voucher", err);
            setStep("upload");
            alert("Hubo un error al procesar el voucher. Intente de nuevo.");
        }
    };

    return (
        <>
            <Head title="Registrar Pago" />
            <div
                className="min-h-screen flex items-center justify-center bg-cover bg-center"
                style={{ backgroundImage: "url('/logo/f_login.png')" }}
            >
                <div className="min-h-screen flex items-center justify-center py-1 w-full">
                    <div className="w-3/5 px-5">
                        <div className="bg-white shadow-2xl ring-1 ring-white/30 rounded-2xl p-6 sm:p-8 w-full mx-auto">

                            {step === "upload" && (
                                <>
                                    <h1 className="text-2xl font-semibold tracking-tight text-gray-900 mb-4 text-center">
                                        Bienvenido al Registro de Pagos
                                    </h1>

                                    <img
                                        src="/logo/tutorial.png"
                                        alt="Instrucciones de pago"
                                        className="rounded-lg shadow mb-6 mx-auto"
                                    />

                                    <div className="flex flex-col items-center gap-4">
                                        <label className="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md cursor-pointer transition">
                                            Subir Voucher
                                            <input
                                                type="file"
                                                accept="image/*,application/pdf"
                                                className="hidden"
                                                onChange={handleUpload}
                                            />
                                        </label>
                                    </div>
                                </>
                            )}

                            {step === "loading" && (
                                <div className="flex flex-col items-center justify-center py-12">
                                    {/* ícono de reloj de arena */}
                                    <svg
                                        className="animate-spin h-12 w-12 text-blue-600 mb-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            className="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            strokeWidth="4"
                                        ></circle>
                                        <path
                                            className="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                        ></path>
                                    </svg>

                                    <p className="text-gray-700 font-medium mb-4">
                                        Procesando voucher, por favor espere...
                                    </p>

                                    {/* barra de carga */}
                                    <div className="w-3/4 bg-gray-200 rounded-full h-4">
                                        <div
                                            className="bg-blue-600 h-4 rounded-full transition-all duration-500"
                                            style={{ width: `${progress}%` }}
                                        ></div>
                                    </div>
                                </div>
                            )}

                            {step === "form" && (
                                <>
                                    <h1 className="text-2xl font-semibold tracking-tight text-gray-900 mb-1">
                                        REGISTRO DE PAGOS - AYBARCORP
                                    </h1>
                                    <p className="text-sm text-gray-700 mb-6">
                                        Formulario para registrar un nuevo pago. Será notificado al Email.
                                    </p>

                                    <PaymentForm initialData={formData} />
                                </>
                            )}

                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
