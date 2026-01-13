import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";

import { Link } from "@inertiajs/react";
import { useState } from "react";

import AppLogo from "./app-logo";
import { NavUser } from "@/components/nav-user";

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
    Database,
    Languages,
    Cpu,
    Workflow,
    FolderSearch,
    FileSearch,
    ListChecks,
    ChevronDown,
} from "lucide-react";

const isilBlue = "text-sky-500 dark:text-sky-400";

/* ======================================================
   SIDEBAR
====================================================== */
export function AppSidebar() {
    return (
        <Sidebar variant="inset">
            {/* ================= LOGO ================= */}
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

            <SidebarContent className="pt-4 pb-2 space-y-3">

                {/* ================= DASHBOARD (SIEMPRE ABIERTO) ================= */}
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link
                                href="/dashboard"
                                className="flex items-center gap-3 px-2 py-2 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30"
                            >
                                <BarChart3 className={`w-5 h-5 ${isilBlue}`} />
                                <span className="font-semibold">Dashboard Vera</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
   <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link
                                href="/dashboard/ranking-certificaciones"
                                className="flex items-center gap-3 px-2 py-2 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30"
                            >
                                <BarChart3 className={`w-5 h-5 ${isilBlue}`} />
                                <span className="font-semibold">Ranking Certificación</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {/* ================= ACADÉMICO ================= */}
                <CollapsibleSection
                    title="Académico"
                    icon={<GraduationCap className={isilBlue} />}
                >
                    <MenuItem href="/syllabus" icon={<FileText className={isilBlue} />} label="Syllabus" />
                    <MenuItem href="/careers" icon={<BookOpen className={isilBlue} />} label="Carreras" />
                    <MenuItem href="/courses" icon={<GraduationCap className={isilBlue} />} label="Cursos ISIL" />
                </CollapsibleSection>

                {/* ================= HABILIDADES ================= */}
                <CollapsibleSection
                    title="Habilidades"
                    icon={<Cpu className={isilBlue} />}
                >
                    <MenuItem href="/languages" icon={<Languages className={isilBlue} />} label="Lenguajes" />
                    <MenuItem href="/technologies" icon={<Cpu className={isilBlue} />} label="Tecnologías" />
                    <MenuItem href="/methodologies" icon={<Workflow className={isilBlue} />} label="Metodologías" />
                    <MenuItem href="/competencies" icon={<ListChecks className={isilBlue} />} label="Competencias" />
                    <MenuItem href="/tech-positions" icon={<ListChecks className={isilBlue} />} label="Roles Tech" />
                </CollapsibleSection>

                {/* ================= EXTRACCIÓN ================= */}
                <CollapsibleSection
                    title="Extracción de Datos"
                    icon={<FolderSearch className={isilBlue} />}
                >
                    <MenuItem href="/job-offers" icon={<BriefcaseBusiness className={isilBlue} />} label="Empleos" />
                    <MenuItem href="/scraping-sources" icon={<FileSearch className={isilBlue} />} label="Tendencias Tech" />
                    <MenuItem href="/topics-ia" icon={<FileSearch className={isilBlue} />} label="Topics IA" />
                </CollapsibleSection>

                {/* ================= IA ================= */}
                <CollapsibleSection
                    title="Inteligencia Artificial"
                    icon={<Brain className={isilBlue} />}
                >
                    <MenuItem href="/admin/ai-trainings" icon={<Database className={isilBlue} />} label="Entrenamiento IA" />
                </CollapsibleSection>

                {/* ================= ADMIN ================= */}
                <CollapsibleSection
                    title="Administración"
                    icon={<Settings className={isilBlue} />}
                >
                    <MenuItem href="/users" icon={<UserCircle2 className={isilBlue} />} label="Usuarios" />
                    <MenuItem href="/roles" icon={<Shield className={isilBlue} />} label="Roles" />
                </CollapsibleSection>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

/* ======================================================
   COLLAPSIBLE SECTION (CLAVE)
====================================================== */
function CollapsibleSection({
    title,
    icon,
    children,
}: {
    title: string;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(false); // 👈 CONTRAÍDO POR DEFECTO

    return (
        <div className="px-2">
            <button
                onClick={() => setOpen(!open)}
                className="
                    w-full flex items-center justify-between
                    px-2 py-2 rounded-md
                    text-xs uppercase tracking-wide font-semibold
                    text-gray-600 dark:text-gray-400
                    hover:bg-gray-100 dark:hover:bg-gray-800
                "
            >
                <div className="flex items-center gap-2">
                    {icon}
                    <span>{title}</span>
                </div>
                <ChevronDown
                    className={`w-4 h-4 transition-transform ${
                        open ? "rotate-180" : ""
                    }`}
                />
            </button>

            {open && (
                <div className="mt-1 pl-4 space-y-1">
                    {children}
                </div>
            )}
        </div>
    );
}

/* ======================================================
   MENU ITEM
====================================================== */
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
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild>
                    <Link
                        href={href}
                        className="
                            flex items-center gap-3 px-2 py-1.5 rounded-md
                            hover:bg-sky-100 dark:hover:bg-sky-900/30
                            transition
                        "
                    >
                        {icon}
                        <span className="font-medium text-sm">{label}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
