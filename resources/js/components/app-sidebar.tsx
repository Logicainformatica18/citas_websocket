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
    const [open, setOpen] = useState(true);

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