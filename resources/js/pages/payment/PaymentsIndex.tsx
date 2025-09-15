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

        const data = new FormData();
        data.append("file_1", file);

        setStep("loading");
        setProgress(10);

        try {
            const res = await axios.post("/vouchers/recognize", data, {
                headers: { "Content-Type": "multipart/form-data" },
                onUploadProgress: (evt) => {
                    if (evt.total) {
                        const percent = Math.round((evt.loaded * 100) / evt.total);
                        setProgress(percent < 95 ? percent : 95);
                    }
                },
            });

            setTimeout(() => {
                setProgress(100);
                setFormData(res.data);
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

            {/* Loading ocupa pantalla completa */}
            {step === "loading" ? (
                <div className="min-h-screen flex flex-col items-center justify-center bg-gradient-to-b from-[#054E5C] to-[#13434d]">
                    {/* Logo */}
                    <img
                        src="/logo/aybar.png"
                        alt="AybarCorp Logo"
                        className="h-28 mb-10"
                    />

                    {/* Texto */}
                    <p className="text-white text-lg font-medium mb-6">
                        Procesando su váucher. Por favor, espere...
                    </p>

                    {/* Barra de progreso */}
                    <div className="w-3/4 bg-gray-200 rounded-full h-4 overflow-hidden">
                        <div
                            className="h-4 rounded-full transition-all duration-500"
                            style={{
                                width: `${progress}%`,
                                backgroundColor: "#FFA726",
                            }}
                        ></div>
                    </div>
                </div>
            ) : (
                // Upload y Form van dentro de tarjeta blanca
                <div className="min-h-screen flex items-center justify-center bg-gradient-to-b from-[#054E5C] to-[#13434d]">
                    <div className="w-full max-w-5xl p-6">
                        <div className="bg-white rounded-2xl shadow-xl p-8">
                            {step === "upload" && (
                                <>
                                    <h1 className="text-2xl font-bold text-center text-[#13434d] mb-8">
                                        ¡REGISTRA TU PAGO FÁCIL Y RÁPIDO!
                                    </h1>

                                    {/* Pasos */}
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                                        <div className="flex flex-col items-center text-center bg-gray-50 rounded-xl p-4 shadow-sm">
                                            <img src="/logo/pago_1.png" alt="Paso 1" className="h-32 mb-4" />
                                            <p className="text-sm text-gray-700">
                                                <strong>Paso 1:</strong> Después de realizar tu pago, toma una{" "}
                                                <strong>foto clara</strong> y verifica que todos los datos sean legibles.
                                            </p>
                                        </div>

                                        <div className="flex flex-col items-center text-center bg-gray-50 rounded-xl p-4 shadow-sm">
                                            <img src="/logo/pago_2.png" alt="Paso 2" className="h-32 mb-4" />
                                            <p className="text-sm text-gray-700">
                                                <strong>Paso 2:</strong> Sube el archivo del comprobante y haz clic en{" "}
                                                <strong>"SUBIR"</strong>.
                                            </p>
                                        </div>

                                        <div className="flex flex-col items-center text-center bg-gray-50 rounded-xl p-4 shadow-sm">
                                            <img src="/logo/pago_3.png" alt="Paso 3" className="h-32 mb-4" />
                                            <p className="text-sm text-gray-700">
                                                <strong>Paso 3:</strong> Completa el formulario y recibirás la{" "}
                                                <strong>boleta de pago en tu correo</strong>.
                                            </p>
                                        </div>
                                    </div>

                                    {/* Upload */}
                                    <div className="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center mb-6">
                                        <input
                                            type="file"
                                            accept="image/*,application/pdf"
                                            id="fileInput"
                                            className="hidden"
                                            onChange={handleUpload}
                                        />
                                        <label
                                            htmlFor="fileInput"
                                            className="cursor-pointer text-[#054E5C] hover:underline"
                                        >
                                            Arrastre y suelte el archivo aquí o <span className="font-semibold">elija el archivo</span>
                                        </label>
                                        <p className="text-xs text-gray-500 mt-2">
                                            Formatos: JPG, PNG | Tamaño máximo: 25MB
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => document.getElementById("fileInput")?.click()}
                                        className="bg-[#FFA726] hover:bg-[#e69520] text-white font-semibold py-3 px-6 rounded-lg w-full"
                                    >
                                        CONTINUAR
                                    </button>
                                </>
                            )}

                            {step === "form" && (
                                <>
                                    <h1 className="text-2xl font-bold text-[#13434d] mb-2">
                                        REGISTRO DE PAGOS - AYBARCORP
                                    </h1>
                                    <p className="text-sm text-gray-700 mb-6">
                                        Completa el formulario para registrar tu pago. Una vez validado recibirás un correo.
                                    </p>

                                    <PaymentForm initialData={formData} />
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
