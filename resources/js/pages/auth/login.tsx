import { Head, useForm } from '@inertiajs/react'
import {
LoaderCircle,
Mail,
Lock,
LogIn,
Activity,
Building2,
User
} from 'lucide-react'
import { FormEventHandler, ReactNode, useState } from 'react'

import InputError from '@/components/input-error'
import TextLink from '@/components/text-link'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type LoginForm = {
email: string
password: string
remember: boolean
}

interface LoginProps {
status?: string
canResetPassword: boolean
}

function Login({ status, canResetPassword }: LoginProps) {

const [loginType, setLoginType] = useState<'select' | 'externo'>('select')

const { data, setData, post, processing, errors, reset } =
useForm<Required<LoginForm>>({
email: '',
password: '',
remember: false,
})

const submit: FormEventHandler = (e) => {
e.preventDefault()
post(route('login'), {
onFinish: () => reset('password'),
})
}

return (
<> <Head title="Observatorio Tecnológico" />
  <div
    className="min-h-screen w-full flex items-center justify-center bg-cover  relative px-6"
    style={{ backgroundImage: "url('/logo/isil_bg2.jpeg')" }}
  >
<div className="absolute inset-0 bg-[#0B2A3A]/31" />

    <div className="relative w-full max-w-xl">

      {/* Header */}
      <div className="flex flex-col items-center mb-10 text-center text-white">

        <div className="w-16 h-16 rounded-2xl bg-[#00AEEF] flex items-center justify-center shadow-lg mb-4">
          <Activity className="w-8 h-8 text-white" />
        </div>

      <h1
  className="text-3xl font-bold"
  style={{ textShadow: "0 0 6px rgba(0,174,239,0.6)" }}
>
  Observatorio Tecnológico
</h1>

<p
  className="text-sm text-gray-200 mt-2"
  style={{ textShadow: "0 0 4px rgba(0,174,239,0.5)" }}
>
  Accede a tu plataforma de monitoreo
</p>
      </div>

      <div className="bg-[#EDEDED] rounded-xl shadow-2xl p-10">

        <div className="flex justify-center mb-8">
          <img
            src="/logo/isil_logo.jpg"
            alt="ISIL Logo"
            className="w-44 object-contain rounded-lg"
          />
        </div>

        {/* =========================
           SELECCIÓN DE LOGIN
        ========================== */}

        {loginType === 'select' && (

          <div className="space-y-4">

            <Button
              type="button"
              onClick={() => window.location.href = '/login/saml'}
              className="w-full h-12 bg-[#0B2A3A] hover:bg-[#091F2A] text-white flex items-center justify-center gap-2"
            >
              <Building2 className="h-5 w-5" />
              Ingresar con usuario ISIL
            </Button>

            <Button
              type="button"
              variant="outline"
              onClick={() => setLoginType('externo')}
              className="w-full h-12 flex items-center justify-center gap-2"
            >
              <User className="h-5 w-5" />
              Usuario externo
            </Button>

          </div>

        )}

        {/* =========================
           LOGIN EXTERNO
        ========================== */}

        {loginType === 'externo' && (

          <form className="space-y-7" onSubmit={submit}>

            {/* Email */}
            <div>
              <Label htmlFor="email" className="text-[#0B2A3A] font-semibold">
                Email
              </Label>

              <div className="relative mt-2">
                <Mail className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-500" />

                <Input
                  id="email"
                  type="email"
                  required
                  autoFocus
                  autoComplete="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  placeholder="correo@ejemplo.com"
                  className="pl-12 h-12 bg-[#F4F4F4] border border-gray-300 text-gray-900 placeholder:text-gray-400 rounded-lg focus:border-[#00AEEF] focus:ring-[#00AEEF]"
                />
              </div>

              <InputError message={errors.email} />
            </div>

            {/* Password */}
            <div>
              <Label htmlFor="password" className="text-[#0B2A3A] font-semibold">
                Contraseña
              </Label>

              <div className="relative mt-2">
                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-500" />

                <Input
                  id="password"
                  type="password"
                  required
                  autoComplete="current-password"
                  value={data.password}
                  onChange={(e) => setData('password', e.target.value)}
                  placeholder="Tu contraseña"
                  className="pl-12 h-12 bg-[#F4F4F4] border border-gray-300 text-gray-900 placeholder:text-gray-400 rounded-lg focus:border-[#00AEEF] focus:ring-[#00AEEF]"
                />
              </div>

              <InputError message={errors.password} />
            </div>

            <div className="flex items-center justify-between pt-2">
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
                  className="text-sm text-[#0077B6] hover:underline"
                >
                  ¿Olvidaste tu contraseña?
                </TextLink>
              )}
            </div>

            <Button
              type="submit"
              disabled={processing}
              className="w-full mt-4 bg-[#0B2A3A] hover:bg-[#091F2A] text-white font-semibold py-3 h-12 rounded-lg shadow-md flex items-center justify-center gap-2 transition-all"
            >
              {processing ? (
                <LoaderCircle className="h-5 w-5 animate-spin" />
              ) : (
                <LogIn className="h-5 w-5" />
              )}
              Ingresar
            </Button>

            {/* volver */}
            <Button
              type="button"
              variant="ghost"
              onClick={() => setLoginType('select')}
              className="w-full"
            >
              Volver
            </Button>

          </form>

        )}

        {status && (
          <div className="mt-6 text-center text-sm font-medium text-green-600">
            {status}
          </div>
        )}

      </div>
    </div>
  </div>
</>


)
}

Login.layout = (page: ReactNode) => page

export default Login
