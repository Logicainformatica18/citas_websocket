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
import { FileSpreadsheet, CreditCard, Database, Users } from 'lucide-react';

type PageProps = {
  permissions: string[];
  auth: {
    user: any;
    role: string;
  };
};

export function AppSidebar() {
  const { permissions } = usePage<PageProps>().props;
  const { toggleSidebar } = useSidebar();

  // Helper para verificar permisos (comentado mientras tanto)
  // const has = (perm: string) => permissions.includes(perm);

  // const canViewBankStatements = has('extractos.ver');
  // const canViewPayments = has('pagos.ver');
  // const canViewScrapings = has('scrapings.ver');

  return (
    <Sidebar collapsible="icon" variant="inset">
      {/* Logo superior */}
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href="/bank-statements">
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {/* Extractos Bancarios */}


        {/* Pagos */}


        {/* Scrapings */}
        <div className="mb-4">
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link href="/scrapings" className="flex items-center gap-2">
                  <Database className="w-5 h-5" />
                  <span>Scrapings</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </div>

        {/* Users & Roles */}
        <div className="mb-4">
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link href="/users" className="flex items-center gap-2">
                  <Users className="w-5 h-5" />
                  <span>Usuarios</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link href="/roles" className="flex items-center gap-2">
                  <Users className="w-5 h-5" />
                  <span>Roles</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </div>
      </SidebarContent>

      {/* Footer con el usuario logueado */}
      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
