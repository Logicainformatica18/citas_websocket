import { Head } from "@inertiajs/react";
import PaymentForm from "./PaymentForm";

export default function PaymentsIndex() {
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
                            <h1 className="text-2xl font-semibold tracking-tight text-gray-900 mb-1">
                                REGISTRO DE PAGOS - AYBARCORP
                            </h1>
                            <p className="text-sm text-gray-700 mb-6">
                                Formulario para registrar un nuevo pago. Será notificado al Email.
                            </p>

                            {/* El formulario separado */}
                            <PaymentForm />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
