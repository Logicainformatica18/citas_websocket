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
import { FileSpreadsheet, CreditCard } from 'lucide-react';

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

  // Helper para verificar permisos
  const has = (perm: string) => permissions.includes(perm);

  // Permisos
  const canViewBankStatements = has('extractos.ver');
  const canViewPayments = has('pagos.ver');

  return (
    <Sidebar collapsible="icon" variant="inset">
      {/* Logo superior */}
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href={canViewBankStatements ? "/bank-statements" : canViewPayments ? "/payments" : "#"}>
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {/* Extractos Bancarios */}
        {canViewBankStatements && (
          <div className="mb-4">
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton asChild>
                  <Link href="/bank-statements" className="flex items-center gap-2">
                    <FileSpreadsheet className="w-5 h-5" />
                    <span>Extractos Bancarios</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </div>
        )}

        {/* Pagos */}
        {canViewPayments && (
          <div className="mb-4">
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton asChild>
                  <Link href="/payments" className="flex items-center gap-2">
                    <CreditCard className="w-5 h-5" />
                    <span>Pagos</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </div>
        )}
      </SidebarContent>

      {/* Footer con el usuario logueado */}
      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}

