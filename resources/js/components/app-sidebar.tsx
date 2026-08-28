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
import { useState } from "react";

import AppLogo from "./app-logo";
import { NavUser } from "@/components/nav-user";

import {
    BarChart3,
    UserCircle2,
    Shield,
    Settings,
    ChevronDown,
    ClipboardList,
    Library,
    Tag,
    Folder,
    Network,
} from "lucide-react";

const colorAlign = "text-sky-500 dark:text-sky-400";

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
                                <span className="font-semibold">Dashboard</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                {/* ================= ENCUESTAS ================= */}
                {/*
                    Todo el módulo está detrás de permission:administrar en
                    routes/web.php, así que se reusa el mismo gate isAdmin.
                    Cuando definas permisos granulares (surveys.ver,
                    surveys.editar), este es el lugar a cambiar.
                */}
                {isAdmin && (
                    <CollapsibleSection
                        title="Encuestas"
                        icon={<ClipboardList className={colorAlign} />}
                    >
                        <MenuItem
                            href="/surveys"
                            icon={<ClipboardList className={colorAlign} />}
                            label="Encuestas"
                        />

                        {/*
                            REBANADA 5 · pendiente. Los reportes cuelgan de una
                            encuesta (/surveys/{id}/report), así que no tienen
                            entrada propia de menú: se entra desde el listado
                            de encuestas.
                        */}

                        <MenuItem
                            href="/selections"
                            icon={<Network className={colorAlign} />}
                            label="Selecciones"
                        />
                    </CollapsibleSection>
                )}

                {/* ================= CATÁLOGOS ================= */}
                {isAdmin && (
                    <CollapsibleSection
                        title="Catálogos"
                        icon={<Library className={colorAlign} />}
                    >
                        <MenuItem
                            href="/types"
                            icon={<Tag className={colorAlign} />}
                            label="Tipos"
                        />
                        <MenuItem
                            href="/categories"
                            icon={<Folder className={colorAlign} />}
                            label="Categorías"
                        />
                    </CollapsibleSection>
                )}

                {/* ================= ADMIN ================= */}
                {isAdmin && (
                    <CollapsibleSection
                        title="Administración"
                        icon={<Settings className={colorAlign} />}
                    >
                        <MenuItem
                            href="/users"
                            icon={<UserCircle2 className={colorAlign} />}
                            label="Usuarios"
                        />
                        <MenuItem
                            href="/roles"
                            icon={<Shield className={colorAlign} />}
                            label="Roles"
                        />
                    </CollapsibleSection>
                )}

            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

/* ======================================================
   COLLAPSIBLE SECTION

   `defaultOpen` es nuevo: con una sola sección daba igual que todas
   arrancaran abiertas, pero con cuatro el sidebar queda muy largo.
   Encuestas arranca abierta; Catálogos y Administración cerradas.
====================================================== */
function CollapsibleSection({
    title,
    icon,
    children,
    defaultOpen = true,
}: {
    title: string;
    icon: React.ReactNode;
    children: React.ReactNode;
    defaultOpen?: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);

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
                    className={`w-4 h-4 transition-transform ${open ? "rotate-180" : ""}`}
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

   `active` resalta la ruta actual comparándola con el url que Inertia
   ya expone en usePage(). Con siete entradas se hace necesario para
   saber dónde estás parado.
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
    const { url } = usePage();
    const active = url === href || url.startsWith(`${href}/`);

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild>
                    <Link
                        href={href}
                        className={`
                            flex items-center gap-3 px-2 py-1.5 rounded-md
                            hover:bg-sky-100 dark:hover:bg-sky-900/30
                            transition
                            ${active ? "bg-sky-100 dark:bg-sky-900/40 font-semibold" : ""}
                        `}
                    >
                        {icon}
                        <span className="font-medium text-sm">{label}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}