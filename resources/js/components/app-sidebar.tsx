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

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';

import { useState } from 'react';
import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import { Link, usePage } from '@inertiajs/react';
import AppLogo from './app-logo';
import { type NavItem } from '@/types';

import {
  LayoutGrid,
  Users,
  ShieldCheck,
  UserCircle,
  LayoutDashboard,
  FolderKanban,
  ArrowUpRight,
  ArrowDownLeft,
  CalendarClock,
  Hourglass,
  HelpCircle,
  List,
  Headphones,
  BookOpen,
  Github,
  ChevronDown,
  ChevronRight,
} from 'lucide-react';

type PageProps = {
  permissions: string[];
  sidebarOpen: boolean;
  auth: {
    user: any;
    role: string;
  };
};

export function AppSidebar() {
  const { permissions } = usePage<PageProps>().props;
  const { toggleSidebar } = useSidebar();

  const has = (perm: string) => permissions.includes(perm);
  const isAdmin = has('administrar');
  const isReserva = has('reserva');
  const isATC = has('atc');

const allGroupTitles = ['Admin', 'Gestión', 'Parámetros', 'Atención'];

const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(
  Object.fromEntries(allGroupTitles.map((title) => [title, true]))
);


  const toggleGroup = (groupTitle: string) => {
    setOpenGroups((prev) => ({
      ...prev,
      [groupTitle]: !prev[groupTitle],
    }));
  };

  const groupedNavItems = [
    {
      title: 'Admin',
      show: isAdmin,
      items: [
        { title: 'Usuarios', href: '/users', icon: Users },
        { title: 'Roles', href: '/roles', icon: ShieldCheck },
      ],
    },
    {
      title: 'Gestión',
      show: isAdmin || isATC,
      items: [
        { title: 'Clientes', href: '/clients', icon: UserCircle },
        { title: 'Áreas', href: '/areas', icon: LayoutDashboard },
        { title: 'Proyectos', href: '/projects', icon: FolderKanban },
      ],
    },
    {
      title: 'Parámetros',
      show: isAdmin || isATC,
      items: [
        { title: 'Estado de Atención', href: '/external-states', icon: ArrowUpRight },
        { title: 'Estados Internos', href: '/internal-states', icon: ArrowDownLeft },
        { title: 'Tipos de Cita', href: '/appointment-types', icon: CalendarClock },
        { title: 'Días de Espera', href: '/waiting-days', icon: Hourglass },
        { title: 'Motivos de Cita', href: '/motives', icon: HelpCircle },
        { title: 'Tipos', href: '/types', icon: List },
      ],
    },
    {
      title: 'Atención',
      show: isAdmin || isATC || isReserva,
      items: [
        { title: 'Atenciones', href: '/supports', icon: Headphones },
      ],
    },
  ];

  const footerNavItems: NavItem[] = [
    {
      title: 'Repository',
      href: 'https://github.com/laravel/react-starter-kit',
      icon: Github,
    },
    {
      title: 'Documentation',
      href: 'https://laravel.com/docs/starter-kits',
      icon: BookOpen,
    },
  ];

  return (
    <Sidebar collapsible="icon" variant="inset" >
      <SidebarHeader >
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild >
              <Link href="/dashboard" >
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {/* Dashboard siempre visible */}
        <div className="mb-4">
          <SidebarMenu >
            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link href="/dashboard" className="flex items-center gap-2">
                  <LayoutGrid className="w-5 h-5" />
                  <span>Dashboard</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </div>

        {/* Menús agrupados y colapsables */}
        {groupedNavItems.map(
          (group, index) =>
            group.show && (
              <div key={index} className="mb-2">
                <Collapsible open={!!openGroups[group.title]} onOpenChange={() => toggleGroup(group.title)}>
                  <CollapsibleTrigger asChild>
                    <button
                      className="w-full px-4 py-2 text-left text-sm font-semibold flex items-center justify-between hover:bg-muted rounded-md"
                    >
                      <span>{group.title}</span>
                      {openGroups[group.title] ? (
                        <ChevronDown className="w-4 h-4" />
                      ) : (
                        <ChevronRight className="w-4 h-4" />
                      )}
                    </button>
                  </CollapsibleTrigger>

                  <CollapsibleContent>
                    <SidebarMenu >
                      {group.items.map((item, idx) => (
                        <SidebarMenuItem key={idx}>
                          <SidebarMenuButton asChild>
                            <Link href={item.href} className="flex items-center gap-2 pl-6">
                              <item.icon className="w-5 h-5" />
                              <span>{item.title}</span>
                            </Link>
                          </SidebarMenuButton>
                        </SidebarMenuItem>
                      ))}
                    </SidebarMenu>
                  </CollapsibleContent>
                </Collapsible>
              </div>
            )
        )}
      </SidebarContent>

      <SidebarFooter>
        <NavFooter items={footerNavItems} className="mt-auto" />
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
