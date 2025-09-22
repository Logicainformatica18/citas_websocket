import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

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

export default function Login({ status, canResetPassword }: LoginProps) {
  const { data, setData, post, processing, errors, reset } = useForm<Required<LoginForm>>({
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
    <div
      className="min-h-screen flex items-center justify-center bg-gray-100"
      style={{ backgroundImage: "url('/logo/isil_bg.jpg')" }}
    >
      <div className="bg-white shadow-2xl rounded-2xl p-10 w-full max-w-md border-t-4 border-[#00AEEF]">
        {/* Logo */}
        <div className="flex justify-center mb-6">
          <img src="/logo/isil_logo.jpg" alt="ISIL Logo" className="w-100" />
        </div>

        {/* Título */}
        {/* <h2 className="text-center text-2xl font-bold mb-6 text-[#002F6C]">
          Iniciar Sesión
        </h2> */}

        {/* Formulario */}
        <form className="space-y-6" onSubmit={submit}>
          <div>
            <Label htmlFor="email" className="text-[#002F6C]">Email</Label>
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
            <Label htmlFor="password" className="text-[#002F6C]">Contraseña</Label>
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

          <div className="flex items-center space-x-2">
            <Checkbox
              id="remember"
              name="remember"
              checked={data.remember}
              onClick={() => setData('remember', !data.remember)}
            />
            <Label htmlFor="remember" className="text-sm text-[#333] hover:text-[#00AEEF]">
              Recordarme
            </Label>
          </div>

          <div className="flex items-center justify-between">
            {canResetPassword && (
              <TextLink href={route('password.request')} className="text-sm text-[#002F6C] hover:text-[#00AEEF]">
                ¿Olvidaste tu contraseña?
              </TextLink>
            )}
          </div>

          {/* Botón */}
          <Button
            type="submit"
            className="w-full bg-[#002F6C] hover:bg-[#001f4d] text-white font-semibold py-2 rounded-lg shadow-md"
            disabled={processing}
          >
            {processing && <LoaderCircle className="h-4 w-4 animate-spin mr-2" />}
            Ingresar
          </Button>
        </form>

        {/* Registro */}
        {/* <div className="text-center text-sm text-gray-600 mt-6">
          ¿No tienes cuenta?{' '}
          <TextLink href={route('register')} className="text-[#00AEEF] font-semibold hover:underline">
            Regístrate
          </TextLink>
        </div> */}

        {status && <div className="mt-4 text-center text-sm font-medium text-green-600">{status}</div>}
      </div>
    </div>
  );
}
