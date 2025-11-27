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

import {
    BarChart3,
    FileText,
    BookOpen,
    BriefcaseBusiness,
    UserCircle2,
    Shield,
    GraduationCap,
    Brain,
    Settings,
    Layers3,
    Database,
    Languages,
    Cpu,
    Workflow,
    FolderSearch,
    FileSearch,
} from 'lucide-react';

// 🔷 Color institucional ISIL
const isilBlue = 'text-sky-500 dark:text-sky-400';

type PageProps = {
    permissions: string[];
    auth: { user: any; role: string };
};

export function AppSidebar() {
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';

    return (
        <Sidebar collapsible="icon" variant="inset">
            {/* Logo */}
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

            <SidebarContent className="pt-3 pb-2 space-y-4">

                {/* 🧭 Principal */}
                <div className="flex flex-col gap-1 pl-3">
                    <div className="flex items-center mb-1 gap-2">
                        <Layers3 className={`ml-1 w-5 h-5 ${isilBlue}`} />
                        {!isCollapsed && (
                            <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                                Principal
                            </span>
                        )}
                    </div>

                    <SidebarMenu>

                        {/* Dashboard */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/dashboard" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <BarChart3 className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Dashboard</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {/* Syllabus */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/syllabus" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <FileText className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Syllabus</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {/* Carreras */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/careers" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <BookOpen className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Carreras</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {/* Lenguajes */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/languages" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <Languages className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Lenguajes</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {/* Tecnologías */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/technologies" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <Cpu className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Tecnologías</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {/* Metodologías */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/methodologies" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <Workflow className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Metodologías</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {/* Cursos */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/courses" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <GraduationCap className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Cursos ISIL</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>



                    </SidebarMenu>
                </div>

                {/* 📤 NUEVA SECCIÓN: EXTRACCIÓN DE DATOS */}
                <div className="flex flex-col gap-1 pl-3 border-t border-gray-200 dark:border-gray-700 pt-2">
                    <div className="flex items-center mb-1 gap-2">
                        <FolderSearch className={`ml-1 w-5 h-5 ${isilBlue}`} />
                        {!isCollapsed && (
                            <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                                Extracción de Datos
                            </span>
                        )}
                    </div>

                    <SidebarMenu>

                        {/* Extracción desde Bolsa de Trabajo */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/job-offers" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/20 transition">
                                    <BriefcaseBusiness className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Empleos – Extracción</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {/* Extracción desde PDF */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/scraping-sources" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/20 transition">
                                    <FileSearch className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium"> Tendencias Tech</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>


                    </SidebarMenu>
                </div>

                {/* 🧠 IA */}
                <div className="flex flex-col gap-1 pl-3 border-t border-gray-200 dark:border-gray-700 pt-2">
                    <div className="flex items-center mb-1 gap-2">
                        <Brain className={`ml-1 w-5 h-5 ${isilBlue}`} />
                        {!isCollapsed && (
                            <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                                Inteligencia Artificial
                            </span>
                        )}
                    </div>

                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/admin/ai-trainings" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <Database className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Entrenamiento IA</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </div>

                {/* ⚙️ Administración */}
                <div className="flex flex-col gap-1 pl-3 border-t border-gray-200 dark:border-gray-700 pt-2">
                    <div className="flex items-center mb-1 gap-2">
                        <Settings className={`ml-1 w-5 h-5 ${isilBlue}`} />
                        {!isCollapsed && (
                            <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                                Administración
                            </span>
                        )}
                    </div>

                    <SidebarMenu>

                        {/* Usuarios */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/users" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <UserCircle2 className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Usuarios</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {/* Roles */}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/roles" className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition">
                                    <Shield className={`w-5 h-5 ${isilBlue}`} />
                                    <span className="font-medium">Roles</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                    </SidebarMenu>
                </div>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
