import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';

import { Link, usePage } from '@inertiajs/react';
import AppLogo from './app-logo';
import { NavUser } from '@/components/nav-user';

// Nuevos íconos
import {
    BarChart3,
    FileText,
    BookOpen,
    BriefcaseBusiness,
    UserCircle2,
    Shield,
    Database,
    GraduationCap, // 🎓 Nuevo ícono para Cursos
} from 'lucide-react';

// Color institucional ISIL
const isilBlue = 'text-sky-500 dark:text-sky-400';

type PageProps = {
    permissions: string[];
    auth: {
        user: any;
        role: string;
    };
};

export function AppSidebar() {
    const { permissions } = usePage<PageProps>().props;
    const { toggleSidebar } = useSidebar();

    // Helper para verificar permisos (comentado mientras tanto)
    // const has = (perm: string) => permissions.includes(perm);

    // const canViewBankStatements = has('extractos.ver');
    // const canViewPayments = has('pagos.ver');
    // const canViewScrapings = has('scrapings.ver');

    return (
        <Sidebar collapsible="icon" variant="inset">
            {/* Logo superior */}
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {/* ================================== */}
                {/* 🧩 BLOQUE: MÓDULOS PRINCIPALES     */}
                {/* ================================== */}
                <div className="mb-4">
                    <h4 className="px-3 py-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                        Módulos principales
                    </h4>

                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/dashboard"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <BarChart3 className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Dashboard
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/syllabus"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <FileText className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Syllabus
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/careers"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <BookOpen className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Carreras
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/courses"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <GraduationCap className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Cursos ISIL
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/job-offers"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <BriefcaseBusiness className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Bolsa de Empleo
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </div>

                {/* ================================== */}
                {/* 🧠 BLOQUE: INTELIGENCIA ARTIFICIAL  */}
                {/* ================================== */}
                <div className="mb-4">
                    <h4 className="px-3 py-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                        Inteligencia Artificial
                    </h4>

                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/admin/ai-trainings"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <Database className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Entrenamiento IA
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </div>

                {/* ================================== */}
                {/* 👥 BLOQUE: ADMINISTRACIÓN          */}
                {/* ================================== */}
                <div className="mb-4">
                    <h4 className="px-3 py-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                        Administración
                    </h4>

                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/users"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <UserCircle2 className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Usuarios
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/roles"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <Shield className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Roles
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </div>

                {/* ================================== */}
                {/* 💾 BLOQUE OPCIONAL: BACKUPS        */}
                {/* ================================== */}
                {/* <div className="mb-4">
                    <h4 className="px-3 py-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                        Sistema
                    </h4>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/backups"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <Shield className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Backups
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </div> */}
            </SidebarContent>

            {/* Footer con el usuario logueado */}
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
