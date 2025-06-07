import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm<Required<{ email: string }>>({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <div className="min-h-screen grid grid-cols-1 xl:grid-cols-2">
            {/* Parte izquierda - formulario */}
            <div className="flex items-center justify-center bg-white p-8">
                <div className="w-full max-w-md">
                    <div className="flex justify-center mb-4">
                        <img src="/logo/1.png" alt="Logo" className="w-16" />
                    </div>

                    <h2 className="text-center text-xl font-bold mb-4">Recuperar contraseña</h2>
                    <p className="text-center text-black text-sm mb-6">
                        Por favor ingresa tu correo electrónico para enviarte un enlace de restablecimiento.
                    </p>

                    {status && (
                        <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="off"
                                value={data.email}
                                autoFocus
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="correo@ejemplo.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="my-6">
                            <Button
                                type="submit"
                                className="w-full bg-[#023039] hover:bg-[#01252d] text-white font-semibold py-2 rounded-full border border-[#F6A42C]"
                                disabled={processing}
                            >
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin mr-2" />}
                                Enviar enlace de recuperación
                            </Button>
                        </div>
                    </form>

                    <div className="text-muted-foreground space-x-1 text-center text-sm">
                        <span>¿Ya tienes cuenta?</span>
                        <TextLink href={route('login')}>Iniciar sesión</TextLink>
                    </div>
                </div>
            </div>

            {/* Parte derecha - fondo imagen */}
            <div
                className="hidden xl:block bg-cover bg-center"
                style={{ backgroundImage: "url('/resource/1734104450_bg.png')" }}
            ></div>
        </div>
    );
}
