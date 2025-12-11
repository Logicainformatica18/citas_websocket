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

import { Link } from '@inertiajs/react';
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
    ListChecks,
} from 'lucide-react';

const isilBlue = 'text-sky-500 dark:text-sky-400';

export function AppSidebar() {
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';

    return (
        <Sidebar collapsible="icon" variant="inset">
            {/* LOGO */}
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

                {/* 🧭 PRINCIPAL */}
                <SectionLabel
                    isCollapsed={isCollapsed}
                    icon={<Layers3 className={`ml-1 w-5 h-5 ${isilBlue}`} />}
                    label="Principal"
                />

                <SidebarMenu>
                    <MenuItem href="/dashboard" icon={<BarChart3 className={`w-5 h-5 ${isilBlue}`} />} label="Dashboard" />
                    <MenuItem href="/syllabus" icon={<FileText className={`w-5 h-5 ${isilBlue}`} />} label="Syllabus" />
                </SidebarMenu>

                {/* 🎓 NÚCLEO ACADÉMICO */}
                <SectionLabel
                    isCollapsed={isCollapsed}
                    icon={<GraduationCap className={`ml-1 w-5 h-5 ${isilBlue}`} />}
                    label="Académico"
                />

                <SidebarMenu>
                    <MenuItem href="/careers" icon={<BookOpen className={`w-5 h-5 ${isilBlue}`} />} label="Carreras" />
                    <MenuItem href="/courses" icon={<GraduationCap className={`w-5 h-5 ${isilBlue}`} />} label="Cursos ISIL" />
                </SidebarMenu>

                {/* 🧠 SKILLS & KNOWLEDGE */}
                <SectionLabel
                    isCollapsed={isCollapsed}
                    icon={<Cpu className={`ml-1 w-5 h-5 ${isilBlue}`} />}
                    label="Habilidades"
                />

                <SidebarMenu>
                    <MenuItem href="/languages" icon={<Languages className={`w-5 h-5 ${isilBlue}`} />} label="Lenguajes" />
                    <MenuItem href="/technologies" icon={<Cpu className={`w-5 h-5 ${isilBlue}`} />} label="Tecnologías" />
                    <MenuItem href="/methodologies" icon={<Workflow className={`w-5 h-5 ${isilBlue}`} />} label="Metodologías" />
                    <MenuItem href="/competencies" icon={<ListChecks className={`w-5 h-5 ${isilBlue}`} />} label="Competencias" />
                      <MenuItem href="/tech-positions" icon={<ListChecks className={`w-5 h-5 ${isilBlue}`} />} label="Roles Tech" />
                </SidebarMenu>

                {/* 📥 EXTRACCIÓN DE DATOS */}
                <SectionLabel
                    isCollapsed={isCollapsed}
                    icon={<FolderSearch className={`ml-1 w-5 h-5 ${isilBlue}`} />}
                    label="Extracción de Datos"
                />

                <SidebarMenu>
                    <MenuItem
                        href="/job-offers"
                        icon={<BriefcaseBusiness className={`w-5 h-5 ${isilBlue}`} />}
                        label="Empleos – Extracción"
                    />
                    <MenuItem
                        href="/scraping-sources"
                        icon={<FileSearch className={`w-5 h-5 ${isilBlue}`} />}
                        label="Tendencias Tech"
                    />
                    <MenuItem
                        href="/topics-ia"
                        icon={<FileSearch className={`w-5 h-5 ${isilBlue}`} />}
                        label="Topics IA"
                    />
                </SidebarMenu>

                {/* 🤖 INTELIGENCIA ARTIFICIAL */}
                <SectionLabel
                    isCollapsed={isCollapsed}
                    icon={<Brain className={`ml-1 w-5 h-5 ${isilBlue}`} />}
                    label="Inteligencia Artificial"
                />

                <SidebarMenu>
                    <MenuItem
                        href="/admin/ai-trainings"
                        icon={<Database className={`w-5 h-5 ${isilBlue}`} />}
                        label="Entrenamiento IA"
                    />
                </SidebarMenu>

                {/* ⚙️ ADMINISTRACIÓN */}
                <SectionLabel
                    isCollapsed={isCollapsed}
                    icon={<Settings className={`ml-1 w-5 h-5 ${isilBlue}`} />}
                    label="Administración"
                />

                <SidebarMenu>
                    <MenuItem href="/users" icon={<UserCircle2 className={`w-5 h-5 ${isilBlue}`} />} label="Usuarios" />
                    <MenuItem href="/roles" icon={<Shield className={`w-5 h-5 ${isilBlue}`} />} label="Roles" />
                </SidebarMenu>
            </SidebarContent>

            {/* Usuario */}
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

/* -------------------- COMPONENTES AUXILIARES --------------------- */

function SectionLabel({
    isCollapsed,
    icon,
    label,
}: {
    isCollapsed: boolean;
    icon: React.ReactNode;
    label: string;
}) {
    return (
        <div className="flex flex-col gap-1 pl-3 border-t border-gray-200 dark:border-gray-700 pt-2">
            <div className="flex items-center mb-1 gap-2">
                {icon}
                {!isCollapsed && (
                    <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                        {label}
                    </span>
                )}
            </div>
        </div>
    );
}

function MenuItem({
    href,
    icon,
    label,
}: {
    href: string;
    icon: React.ReactNode;
    label: string;
}) {
    return (
        <SidebarMenuItem>
            <SidebarMenuButton asChild>
                <Link
                    href={href}
                    className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                    {icon}
                    <span className="font-medium">{label}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}
