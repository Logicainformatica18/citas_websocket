import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type LoginForm = {
  email: string;
  password: string;
  remember: boolean;
};

interface LoginProps {
  status?: string;
  canResetPassword: boolean;
}

function Login({ status, canResetPassword }: LoginProps) {
  const { data, setData, post, processing, errors, reset } =
    useForm<Required<LoginForm>>({
      email: '',
      password: '',
      remember: false,
    });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post(route('login'), {
      onFinish: () => reset('password'),
    });
  };

  return (
    <>
      <Head title="Iniciar sesión" />

      <div
        className="min-h-screen w-full grid place-items-center bg-cover bg-center"
        style={{ backgroundImage: "url('/logo/isil_bg.jpg')" }}
      >
        {/* Overlay */}
        <div className="absolute inset-0 bg-black/30" />

        {/* Card */}
        <div className="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl p-10 border-t-4 border-[#00AEEF]">
          {/* Logo */}
          <div className="flex justify-center mb-8">
            <img
              src="/logo/isil_logo.jpg"
              alt="ISIL Logo"
              className="w-44 object-contain"
            />
          </div>

          {/* Form */}
          <form className="space-y-6" onSubmit={submit}>
            <div>
              <Label htmlFor="email" className="text-[#002F6C]">
                Email
              </Label>
              <Input
                id="email"
                type="email"
                required
                autoFocus
                autoComplete="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                placeholder="correo@ejemplo.com"
                className="border-gray-300 focus:border-[#00AEEF] focus:ring-[#00AEEF]"
              />
              <InputError message={errors.email} />
            </div>

            <div>
              <Label htmlFor="password" className="text-[#002F6C]">
                Contraseña
              </Label>
              <Input
                id="password"
                type="password"
                required
                autoComplete="current-password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                placeholder="Tu contraseña"
                className="border-gray-300 focus:border-[#00AEEF] focus:ring-[#00AEEF]"
              />
              <InputError message={errors.password} />
            </div>

            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Checkbox
                  id="remember"
                  checked={data.remember}
                  onClick={() => setData('remember', !data.remember)}
                />
                <Label htmlFor="remember" className="text-sm text-gray-700">
                  Recordarme
                </Label>
              </div>

              {canResetPassword && (
                <TextLink
                  href={route('password.request')}
                  className="text-sm text-[#002F6C] hover:text-[#00AEEF]"
                >
                  ¿Olvidaste tu contraseña?
                </TextLink>
              )}
            </div>

            <Button
              type="submit"
              className="w-full bg-[#002F6C] hover:bg-[#001f4d] text-white font-semibold py-2 rounded-lg shadow-md flex items-center justify-center gap-2"
              disabled={processing}
            >
              {processing && (
                <LoaderCircle className="h-4 w-4 animate-spin" />
              )}
              Ingresar
            </Button>
          </form>

          {status && (
            <div className="mt-6 text-center text-sm font-medium text-green-600">
              {status}
            </div>
          )}
        </div>
      </div>
    </>
  );
}

/* ======================================================
   🔥 CLAVE ABSOLUTA: SIN LAYOUT PADRE
====================================================== */
Login.layout = (page: ReactNode) => page;

export default Login;
