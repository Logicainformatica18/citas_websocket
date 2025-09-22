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
      <div className="flex items-center justify-center bg-white p-10">
        <div className="w-full max-w-md">
          {/* Logo */}
          <div className="flex justify-center mb-6">
            <img src="/logo/isil_logo.jpg" alt="ISIL Logo" className="w-50" />
          </div>

          {/* Título */}
          <h2 className="text-center text-2xl font-bold mb-4 text-[#002F6C]">
            Recuperar contraseña
          </h2>
          <p className="text-center text-gray-600 text-sm mb-6">
            Ingresa tu correo electrónico y te enviaremos un enlace de restablecimiento.
          </p>

          {/* Mensaje de estado */}
          {status && (
            <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>
          )}

          {/* Formulario */}
          <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
              <Label htmlFor="email" className="text-[#002F6C]">Correo electrónico</Label>
              <Input
                id="email"
                type="email"
                name="email"
                autoComplete="off"
                value={data.email}
                autoFocus
                onChange={(e) => setData('email', e.target.value)}
                placeholder="correo@ejemplo.com"
                className="border-gray-300 focus:border-[#00AEEF] focus:ring-[#00AEEF]"
              />
              <InputError message={errors.email} />
            </div>

            <div className="my-6">
              <Button
                type="submit"
                className="w-full bg-[#00AEEF] hover:bg-[#008fc2] text-white font-semibold py-2 rounded-md shadow"
                disabled={processing}
              >
                {processing && <LoaderCircle className="h-4 w-4 animate-spin mr-2" />}
                Enviar enlace de recuperación
              </Button>
            </div>
          </form>

          {/* Link volver a login */}
          <div className="text-center text-sm text-gray-600 mt-6">
            ¿Ya tienes cuenta?{' '}
            <TextLink href={route('login')} className="text-[#00AEEF] font-semibold hover:underline">
              Iniciar sesión
            </TextLink>
          </div>
        </div>
      </div>

      {/* Parte derecha - imagen institucional */}
      <div
        className="hidden xl:block bg-cover bg-center"
        style={{ backgroundImage: "url('/logo/isil_bg.jpg')" }}
      ></div>
    </div>
  );
}
