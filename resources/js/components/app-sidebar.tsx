// layouts/app-sidebar.tsx

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

import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Folder, BookOpen } from 'lucide-react';
import AppLogo from './app-logo';
import { type NavItem } from '@/types';

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

  const mainNavItems: NavItem[] = [
    {
      title: 'Dashboard',
      href: '/dashboard',
      icon: LayoutGrid,
    },

    // Usuarios (solo admin)
    ...(isAdmin ? [{
      title: 'Usuarios',
      href: '/users',
      icon: Folder,
    }] : []),

    // Roles (solo admin)
    ...(isAdmin ? [{
      title: 'Roles',
      href: '/roles',
      icon: Folder,
    }] : []),

    // Clientes (admin o reserva)
    ...((isAdmin || isATC) ? [{
      title: 'Clientes',
      href: '/clients',
      icon: Folder,
    }] : []),

    // Atenciones (admin, reserva o atc)
    ...((isAdmin || isReserva || isATC) ? [{
      title: 'Atenciones',
      href: '/supports',
      icon: Folder,
    }] : []),

    // Otros módulos (admin o atc)
    ...((isAdmin || isATC) ? [{
      title: 'Areas',
      href: '/areas',
      icon: Folder,
    }] : []),

    // Otros módulos (admin o atc)
    ...((isAdmin || isATC) ? [{
      title: 'Proyectos',
      href: '/projects',
      icon: Folder,
    }] : []),
    ...((isAdmin || isATC) ? [{
      title: 'Estados Externos',
      href: '/external-states',
      icon: Folder,
    }] : []),

    ...((isAdmin || isATC) ? [{
      title: 'Estados Internos',
      href: '/internal-states',
      icon: Folder,
    }] : []),

    ...((isAdmin || isATC) ? [{
      title: 'Tipos de Cita',
      href: '/appointment-types',
      icon: Folder,
    }] : []),

    ...((isAdmin || isATC) ? [{
      title: 'Días de Espera',
      href: '/waiting-days',
      icon: Folder,
    }] : []),

    ...((isAdmin || isATC) ? [{
      title: 'Motivos de Cita',
      href: '/motives',
      icon: Folder,
    }] : []),

    ...((isAdmin || isATC) ? [{
      title: 'Tipos',
      href: '/types',
      icon: Folder,
    }] : []),
  ];




  const footerNavItems: NavItem[] = [
    {
      title: 'Repository',
      href: 'https://github.com/laravel/react-starter-kit',
      icon: Folder,
    },
    {
      title: 'Documentation',
      href: 'https://laravel.com/docs/starter-kits',
      icon: BookOpen,
    },
  ];

  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href="/dashboard">
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <NavMain items={mainNavItems} />
      </SidebarContent>

      <SidebarFooter>
        <NavFooter items={footerNavItems} className="mt-auto" />
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
