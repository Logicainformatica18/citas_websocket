import { Head, useForm } from '@inertiajs/react'
import {
    LoaderCircle,
    Mail,
    Lock,
    LogIn,
    ClipboardList,
    Building2,
    User,
} from 'lucide-react'
import { FormEventHandler, ReactNode, useState } from 'react'

import InputError from '@/components/input-error'
import TextLink from '@/components/text-link'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import ReCAPTCHA from 'react-google-recaptcha'

/*
 * `captcha` ahora vive dentro del formulario.
 *
 * Antes estaba en un useState aparte y se intentaba mandar así:
 *
 *     post(route('login'), { data: { ...data, captcha: captchaToken } })
 *
 * El post de useForm tiene firma (url, options): los datos salen del estado
 * del formulario, y `data` no es una opción de visita válida, así que se
 * ignoraba en silencio. El token nunca llegaba al servidor.
 */
type LoginForm = {
    email: string
    password: string
    remember: boolean
    captcha: string
}

interface LoginProps {
    status?: string
    canResetPassword: boolean
}

function Login({ status, canResetPassword }: LoginProps) {
    const [loginType, setLoginType] = useState<'select' | 'externo'>('select')

    const { data, setData, post, processing, errors, reset } = useForm<
        Required<LoginForm>
    >({
        email: '',
        password: '',
        remember: false,
        captcha: '',
    })

    const submit: FormEventHandler = (e) => {
        e.preventDefault()

        post(route('login'), {
            onFinish: () => {
                reset('password', 'captcha')
            },
        })
    }

    return (
        <>
            <Head title="Plataforma de Encuestas ISIL" />

            <div
                className="relative min-h-screen w-full bg-cover bg-center px-6 py-12 flex flex-col items-center justify-center"
                style={{ backgroundImage: "url('/logo/login-surveys.jpg')" }}
            >
                {/*
                    OVERLAY EN DOS CAPAS.

                    Antes era un solo bg-[#0B2A3A]/21. El paso /21 no está en la
                    escala de opacidad por defecto de Tailwind, así que la clase
                    puede no generarse y dejar la foto sin atenuar.

                    Capa 1: degradado diagonal, más cerrado arriba a la
                    izquierda y abriéndose al cian institucional abajo a la
                    derecha.
                */}
                <div
                    className="absolute inset-0 bg-gradient-to-br from-[#0B2A3A]/85 via-[#0B2A3A]/55 to-[#00AEEF]/35"
                    aria-hidden="true"
                />

                {/*
                    Capa 2: viñeta radial. Aclara el centro para que la tarjeta
                    respire y oscurece los bordes. Va con estilo inline porque
                    los degradados radiales con paradas propias no salen de las
                    utilidades de Tailwind.
                */}
                <div
                    className="absolute inset-0"
                    style={{
                        background:
                            'radial-gradient(ellipse at 50% 35%, rgba(0,174,239,0.18) 0%, rgba(11,42,58,0.35) 55%, rgba(11,42,58,0.75) 100%)',
                    }}
                    aria-hidden="true"
                />

                <div className="relative w-full max-w-xl">
                    {/* ================= HEADER ================= */}
                    <div className="mb-10 flex flex-col items-center text-center text-white">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#00AEEF] shadow-lg shadow-[#00AEEF]/40">
                            <ClipboardList className="h-8 w-8 text-white" />
                        </div>

                        <h1
                            className="text-3xl font-bold sm:text-4xl"
                            style={{ textShadow: '0 2px 12px rgba(11,42,58,0.55)' }}
                        >
                            Plataforma de Encuestas ISIL
                        </h1>

                        <p
                            className="mt-2 text-sm text-gray-100"
                            style={{ textShadow: '0 1px 8px rgba(11,42,58,0.6)' }}
                        >
                            Accede para responder y gestionar tus encuestas
                        </p>
                    </div>

                    {/* ================= TARJETA ================= */}
                    <div className="rounded-2xl bg-[#EDEDED]/95 p-10 shadow-2xl ring-1 ring-white/40 backdrop-blur-sm">
                        <div className="mb-8 flex justify-center">
                            <img
                                src="/logo/isil_logo.jpg"
                                alt="ISIL"
                                className="w-44 rounded-lg object-contain"
                            />
                        </div>

                        {/* ---------- SELECCIÓN DE LOGIN ---------- */}
                        {loginType === 'select' && (
                            <div className="space-y-4">
                                <Button
                                    type="button"
                                    onClick={() =>
                                        (window.location.href = '/login/saml')
                                    }
                                    className="h-12 w-full bg-[#0B2A3A] text-white transition-colors hover:bg-[#091F2A] flex items-center justify-center gap-2"
                                >
                                    <Building2 className="h-5 w-5" />
                                    Ingresar con usuario ISIL
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setLoginType('externo')}
                                    className="h-12 w-full border-gray-300 bg-white transition-colors hover:bg-gray-50 flex items-center justify-center gap-2"
                                >
                                    <User className="h-5 w-5" />
                                    Usuario externo
                                </Button>

                                <p className="pt-2 text-center text-xs text-gray-500">
                                    Al ingresar aceptas las políticas de privacidad y
                                    el tratamiento de datos de ISIL.
                                </p>
                            </div>
                        )}

                        {/* ---------- LOGIN EXTERNO ---------- */}
                        {loginType === 'externo' && (
                            <form className="space-y-7" onSubmit={submit}>
                                {/* Email */}
                                <div>
                                    <Label
                                        htmlFor="email"
                                        className="font-semibold text-[#0B2A3A]"
                                    >
                                        Email
                                    </Label>

                                    <div className="relative mt-2">
                                        <Mail className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500" />

                                        <Input
                                            id="email"
                                            type="email"
                                            required
                                            autoFocus
                                            autoComplete="email"
                                            value={data.email}
                                            onChange={(e) =>
                                                setData('email', e.target.value)
                                            }
                                            placeholder="correo@ejemplo.com"
                                            className="h-12 rounded-lg border border-gray-300 bg-[#F4F4F4] pl-12 text-gray-900 placeholder:text-gray-400 focus:border-[#00AEEF] focus:ring-[#00AEEF]"
                                        />
                                    </div>

                                    <InputError message={errors.email} />
                                </div>

                                {/* Password */}
                                <div>
                                    <Label
                                        htmlFor="password"
                                        className="font-semibold text-[#0B2A3A]"
                                    >
                                        Contraseña
                                    </Label>

                                    <div className="relative mt-2">
                                        <Lock className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500" />

                                        <Input
                                            id="password"
                                            type="password"
                                            required
                                            autoComplete="current-password"
                                            value={data.password}
                                            onChange={(e) =>
                                                setData('password', e.target.value)
                                            }
                                            placeholder="Tu contraseña"
                                            className="h-12 rounded-lg border border-gray-300 bg-[#F4F4F4] pl-12 text-gray-900 placeholder:text-gray-400 focus:border-[#00AEEF] focus:ring-[#00AEEF]"
                                        />
                                    </div>

                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center justify-between pt-2">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="remember"
                                            checked={data.remember}
                                            onClick={() =>
                                                setData('remember', !data.remember)
                                            }
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="text-sm text-gray-700"
                                        >
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

                                <div className="flex justify-center">
                                    <ReCAPTCHA
                                        sitekey={
                                            import.meta.env.VITE_RECAPTCHA_SITE_KEY
                                        }
                                        onChange={(token) =>
                                            setData('captcha', token ?? '')
                                        }
                                    />
                                </div>

                                {/*
                                    El captcha se valida en el servidor, así que su
                                    error llega por `errors` como cualquier otro
                                    campo. Antes se mostraba con un alert(), que no
                                    cubría el caso de token expirado.
                                */}
                                <InputError message={errors.captcha} />

                                <Button
                                    type="submit"
                                    disabled={processing || !data.captcha}
                                    className="mt-4 h-12 w-full rounded-lg bg-[#0B2A3A] py-3 font-semibold text-white shadow-md transition-all hover:bg-[#091F2A] disabled:opacity-60 flex items-center justify-center gap-2"
                                >
                                    {processing ? (
                                        <LoaderCircle className="h-5 w-5 animate-spin" />
                                    ) : (
                                        <LogIn className="h-5 w-5" />
                                    )}
                                    Ingresar
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setLoginType('select')}
                                    className="w-full text-gray-600 hover:text-gray-900"
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

                    {/* ================= SOPORTE ================= */}
                    <p
                        className="mt-8 text-center text-xs text-gray-200"
                        style={{ textShadow: '0 1px 6px rgba(11,42,58,0.7)' }}
                    >
                        ¿Problemas para acceder? Escribe a{' '}
                        <a
                            href="mailto:soporte.encuestas@isil.pe"
                            className="underline decoration-white/40 hover:decoration-white"
                        >
                            soporte.encuestas@isil.pe
                        </a>
                    </p>
                </div>
            </div>
        </>
    )
}

Login.layout = (page: ReactNode) => page

export default Login
