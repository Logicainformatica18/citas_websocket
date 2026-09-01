import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

import AppLogo from './app-logo';
import { NavUser } from '@/components/nav-user';

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
} from 'lucide-react';

/* Paleta del sidebar en un solo lugar */
const SIDEBAR_BG = 'bg-[#0d2434]';
const SIDEBAR_BORDER = 'border-[#1d3243]';
const HOVER_BG = 'hover:bg-[#17374d]';
const ACTIVE_BG = 'bg-[#17374d]';
const ICON_COLOR = 'text-[#8dd8f2]';

export function AppSidebar() {
    type PageProps = {
        permissions: string[];
    };
    const { permissions } = usePage<PageProps>().props;
    const has = (perm: string) => permissions.includes(perm);
    const isAdmin = has('administrar');

    return (
        <Sidebar
            variant="inset"
            className={`border-r border-[#1a3446] ${SIDEBAR_BG} text-white shadow-[inset_-1px_0_0_rgba(255,255,255,0.06)]`}
        >
            <SidebarHeader className={`border-b ${SIDEBAR_BORDER} ${SIDEBAR_BG} px-4 py-4`}>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <Link
                            href="/"
                            className="flex items-center gap-3 rounded-md p-0 transition hover:opacity-90"
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className={`space-y-3 ${SIDEBAR_BG} px-2 pb-2 pt-4 text-white`}>
                <SidebarMenu className="px-2">
                    <MenuItem href="/dashboard" icon={<BarChart3 />} label="Resumen" />
                </SidebarMenu>

                {isAdmin && (
                    <CollapsibleSection title="Encuestas" icon={<ClipboardList />} defaultOpen>
                        <MenuItem href="/surveys" icon={<ClipboardList />} label="Encuestas" />
                        <MenuItem href="/selections" icon={<Network />} label="Selecciones" />
                    </CollapsibleSection>
                )}

                {isAdmin && (
                    <CollapsibleSection title="Catálogos" icon={<Library />} defaultOpen={false}>
                        <MenuItem href="/types" icon={<Tag />} label="Tipos" />
                        <MenuItem href="/categories" icon={<Folder />} label="Categorías" />
                    </CollapsibleSection>
                )}

                {isAdmin && (
                    <CollapsibleSection title="Administración" icon={<Settings />} defaultOpen={false}>
                        <MenuItem href="/users" icon={<UserCircle2 />} label="Usuarios" />
                        <MenuItem href="/roles" icon={<Shield />} label="Roles" />
                    </CollapsibleSection>
                )}
            </SidebarContent>

            <SidebarFooter className={`border-t ${SIDEBAR_BORDER} ${SIDEBAR_BG} p-2`}>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

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
                type="button"
                onClick={() => setOpen(!open)}
                aria-expanded={open}
                className={`flex w-full items-center justify-between rounded-md px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-[#dfeaf1] transition ${HOVER_BG} [&_svg]:h-4 [&_svg]:w-4`}
            >
                <span className="flex items-center gap-2">
                    <span className={ICON_COLOR}>{icon}</span>
                    <span>{title}</span>
                </span>
                <ChevronDown className={`shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && <SidebarMenu className="mt-1 space-y-1 pl-2">{children}</SidebarMenu>}
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
    const { url } = usePage();
    const active = url === href || url.startsWith(`${href}/`);

    return (
        <SidebarMenuItem>
            <Link
                href={href}
                aria-current={active ? 'page' : undefined}
                className={`flex w-full items-center gap-3 rounded-md px-2 py-2 text-[15px] font-medium transition [&_svg]:h-5 [&_svg]:w-5 [&_svg]:shrink-0 ${
                    active
                        ? `${ACTIVE_BG} text-white shadow-[inset_0_0_0_1px_rgba(141,216,242,0.15)]`
                        : `text-white/80 ${HOVER_BG} hover:text-white`
                }`}
            >
                <span className={ICON_COLOR}>{icon}</span>
                <span className="truncate">{label}</span>
            </Link>
        </SidebarMenuItem>
    );
}