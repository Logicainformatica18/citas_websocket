import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";

import { Link, usePage } from "@inertiajs/react";
import { useState, useEffect } from "react";

import AppLogo from "./app-logo";
import { NavUser } from "@/components/nav-user";

import {
    BarChart3,
    Award,
    Users,
    Building2,
    Laptop,
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




const colorMarket =
    " text-teal-500 dark:bg-teal-400/15 dark:text-teal-400";
const colorTrends = "text-purple-500 dark:text-purple-400";   // morado
const colorAlign = "text-sky-500 dark:text-sky-400";          // celeste ISIL
/* ======================================================
   SIDEBAR
====================================================== */
export function AppSidebar() {
    type PageProps = {
        permissions: string[];
    };
    const { permissions } = usePage<PageProps>().props;
    const has = (perm: string) => permissions.includes(perm);

    const isAdmin = has("administrar");
    useEffect(() => {
        if (isAdmin) {
            console.log("Usuario ADMIN detectado");
        }
    }, []);
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

                {/* ================= DASHBOARD ================= */}
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link
                                href="/dashboard"
                                className="flex items-center gap-3 px-2 py-2 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30"
                            >
                                <BarChart3 className={`w-5 h-5 ${colorAlign}`} />
                                <span className="font-semibold">Dashboard Vera</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                {/* ================= INDICADORES ================= */}
                {/* ================= INDICADORES ================= */}
                <CollapsibleSection
                    title="Indicadores"
                    icon={<BarChart3 className={colorAlign} />}
                >

                    {/* ===== MERCADO Y DEMANDA ===== */}
                    <div className="pt-2 pb-1 text-[10px] uppercase tracking-wide text-gray-400">
                        Mercado y Demanda
                    </div>

                    <MenuItem
                        href="/dashboard/ranking/technologies"
                        icon={<Cpu className={colorMarket} />}
                        label="Tecnologías"
                    />

                    <MenuItem
                        href="/dashboard/ranking/languages"
                        icon={<Languages className={colorMarket} />}
                        label="Lenguajes"
                    />

                    <MenuItem
                        href="/dashboard/ranking-certificaciones"
                        icon={<Award className={colorMarket} />}
                        label="Certificaciones"
                    />

                    <MenuItem
                        href="/dashboard/indicators/seniority"
                        icon={<Users className={colorMarket} />}
                        label="Nivel Profesional"
                    />

                    <MenuItem
                        href="/dashboard/indicators/companies"
                        icon={<Building2 className={colorMarket} />}
                        label="Empresas"
                    />

                    <MenuItem
                        href="/dashboard/indicators/job-demand-geo"
                        icon={<Building2 className={colorMarket} />}
                        label="Demanda Ciudad"
                    />

                    <MenuItem
                        href="/dashboard/indicadores/modalidad-laboral"
                        icon={<Laptop className={colorMarket} />}
                        label="Modalidad Laboral"
                    />

                    {/* ===== TENDENCIAS ===== */}
                    <div className="pt-3 pb-1 text-[10px] uppercase tracking-wide text-gray-400">
                        Tendencias
                    </div>

                    <MenuItem
                        href="/dashboard/indicators/macro-trends"
                        icon={<Brain className={colorTrends} />}
                        label="Tendencias Macro"
                    />
 <div className="pt-3 pb-1 text-[10px] uppercase tracking-wide text-gray-400">
                        Origen de Datos
                    </div>
                        <MenuItem
                        href="/sources"
                        icon={<Cpu className=" -ml-px" />}
                        label="Comandos Fuentes"
                    />
                    {/* ===== ALINEACIÓN ACADÉMICA ===== */}
                    <div className="pt-3 pb-1 text-[10px] uppercase tracking-wide text-gray-400">
                        Alineación Académica
                    </div>

                    {/* <MenuItem
        href="/dashboard/indicators/pe-alignment"
        icon={<ListChecks className={colorAlign} />}
        label="Alineación Competencias"
    /> */}

                    <MenuItem
                        href="/dashboard/indicators/course-alignment"
                        icon={<GraduationCap className={colorAlign} />}
                        label="Alineación Cursos"
                    />

                </CollapsibleSection>
{isAdmin && (
<>
                {/* ================= ACADÉMICO ================= */}
                <CollapsibleSection
                    title="Académico"
                    icon={<GraduationCap className={colorAlign} />}
                >
                    <MenuItem
                        href="/silabos"
                        icon={<FileText className={colorAlign} />}
                        label="Silabos"
                    />
                    <MenuItem
                        href="/careers"
                        icon={<BookOpen className={colorAlign} />}
                        label="Carreras"
                    />
                    <MenuItem
                        href="/courses"
                        icon={<GraduationCap className={colorAlign} />}
                        label="Cursos ISIL"
                    />
                </CollapsibleSection>

                {/* ================= HABILIDADES ================= */}
                <CollapsibleSection
                    title="Habilidades"
                    icon={<Cpu className={colorAlign} />}
                >
                    <MenuItem href="/languages" icon={<Languages className={colorAlign} />} label="Lenguajes" />
                    <MenuItem href="/technologies" icon={<Cpu className={colorAlign} />} label="Tecnologías" />
                    <MenuItem href="/methodologies" icon={<Workflow className={colorAlign} />} label="Metodologías" />
                    <MenuItem href="/competencies" icon={<ListChecks className={colorAlign} />} label="Competencias" />
                    <MenuItem href="/tech-positions" icon={<ListChecks className={colorAlign} />} label="Roles Tech" />
                    <MenuItem href="/market-entities" icon={<Languages className={colorAlign} />} label="Entidades" />
                </CollapsibleSection>

                {/* ================= EXTRACCIÓN ================= */}
                <CollapsibleSection
                    title="Datos"
                    icon={<FolderSearch className={colorAlign} />}
                >
                    <MenuItem href="/job-offers" icon={<BriefcaseBusiness className={colorAlign} />} label="Empleos" />
                    <MenuItem href="/topics-ia" icon={<FileSearch className={colorAlign} />} label="Topics IA" />
                </CollapsibleSection>

                {/* ================= IA ================= */}
                <CollapsibleSection
                    title="IA VERA"
                    icon={<Brain className={colorAlign} />}
                >
                    <MenuItem
                        href="/admin/ai-trainings"
                        icon={<Database className={colorAlign} />}
                        label="Entrenamiento IA"
                    />
                </CollapsibleSection>

                {/* ================= ADMIN ================= */}
                <CollapsibleSection
                    title="Administración"
                    icon={<Settings className={colorAlign} />}
                >
                    <MenuItem href="/users" icon={<UserCircle2 className={colorAlign} />} label="Usuarios" />
                    <MenuItem href="/roles" icon={<Shield className={colorAlign} />} label="Roles" />
                </CollapsibleSection>
</>
)}
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
    const [open, setOpen] = useState(true); // 👈 CONTRAÍDO POR DEFECTO

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
                    className={`w-4 h-4 transition-transform ${open ? "rotate-180" : ""
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
