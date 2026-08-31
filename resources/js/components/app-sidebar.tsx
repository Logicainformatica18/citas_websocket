import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
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

const colorAlign = 'text-[#8dd8f2]';

export function AppSidebar() {
    type PageProps = {
        permissions: string[];
    };
    const { permissions } = usePage<PageProps>().props;
    const has = (perm: string) => permissions.includes(perm);
    const isAdmin = has('administrar');

    return (
        <Sidebar variant="inset" className="border-r border-[#1a3446] bg-[#0d2434] text-white shadow-[inset_-1px_0_0_rgba(255,255,255,0.06)]">
            <SidebarHeader className="border-b border-[#1d3243] bg-[#0d2434] px-4 py-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="h-auto p-0 hover:bg-transparent data-[state=open]:bg-transparent">
                            <Link href="/" className="flex items-center gap-3">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="space-y-3 bg-[#0d2434] px-2 pb-2 pt-4 text-white">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link
                                href="/dashboard"
                                className="flex items-center gap-3 rounded-md px-3 py-2 text-[15px] font-medium text-white/90 transition hover:bg-[#17374d] hover:text-white"
                            >
                                <BarChart3 className={`h-5 w-5 ${colorAlign}`} />
                                <span>Resumen</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                {isAdmin && (
                    <CollapsibleSection title="Encuestas" icon={<ClipboardList className={colorAlign} />} defaultOpen>
                        <MenuItem href="/surveys" icon={<ClipboardList className={colorAlign} />} label="Encuestas" />
                        <MenuItem href="/selections" icon={<Network className={colorAlign} />} label="Selecciones" />
                    </CollapsibleSection>
                )}

                {isAdmin && (
                    <CollapsibleSection title="Catálogos" icon={<Library className={colorAlign} />} defaultOpen={false}>
                        <MenuItem href="/types" icon={<Tag className={colorAlign} />} label="Tipos" />
                        <MenuItem href="/categories" icon={<Folder className={colorAlign} />} label="Categorías" />
                    </CollapsibleSection>
                )}

                {isAdmin && (
                    <CollapsibleSection title="Administración" icon={<Settings className={colorAlign} />} defaultOpen={false}>
                        <MenuItem href="/users" icon={<UserCircle2 className={colorAlign} />} label="Usuarios" />
                        <MenuItem href="/roles" icon={<Shield className={colorAlign} />} label="Roles" />
                    </CollapsibleSection>
                )}
            </SidebarContent>

            <SidebarFooter className="border-t border-[#1d3243] bg-[#0d2434] p-2">
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
                onClick={() => setOpen(!open)}
                className="flex w-full items-center justify-between rounded-md px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-[#dfeaf1] transition hover:bg-[#17374d]"
            >
                <div className="flex items-center gap-2">
                    {icon}
                    <span>{title}</span>
                </div>
                <ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && <div className="mt-1 space-y-1 pl-2">{children}</div>}
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
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild>
                    <Link
                        href={href}
                        className={`
                            flex items-center gap-3 rounded-md px-2 py-2 text-[15px] font-medium transition
                            ${active ? 'bg-[#17374d] text-white shadow-[inset_0_0_0_1px_rgba(141,216,242,0.15)]' : 'text-white/80 hover:bg-[#17374d] hover:text-white'}
                        `}
                    >
                        {icon}
                        <span>{label}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}