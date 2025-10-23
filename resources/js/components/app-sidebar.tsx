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
                {/* Scrapings */}
                <div className="mb-4">
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
                    </SidebarMenu>

                    {/* <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/scrapings" className="flex items-center gap-2">
                                    <Database className="w-5 h-5" />
                                    <span>Scrapings</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu> */}

                    <SidebarMenu>
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
                    </SidebarMenu>

                    <SidebarMenu>
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
                    </SidebarMenu>

                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/courses"
                                    className="flex items-center gap-2 hover:text-sky-500 transition-colors"
                                >
                                    <BookOpen className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="text-gray-800 dark:text-gray-100">
                                        Cursos ISIL
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>

                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href="/admin/report-queries"
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

                    <SidebarMenu>
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

                {/* Users & Roles */}
                <div className="mb-4">
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

                {/* Backups */}
                {/* <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link href="/backups" className="flex items-center gap-2">
                                <Shield className="w-5 h-5" />
                                <span>Backups</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu> */}
            </SidebarContent>

            {/* Footer con el usuario logueado */}
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
