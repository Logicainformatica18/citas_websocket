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
  Layers3,
  Database,
} from 'lucide-react';

// 🔷 Color institucional ISIL
const isilBlue = 'text-sky-500 dark:text-sky-400';

type PageProps = {
  permissions: string[];
  auth: {
    user: any;
    role: string;
  };
};

export function AppSidebar() {
  const { permissions } = usePage<PageProps>().props;
  const { state } = useSidebar(); // 🔹 Para saber si está colapsado o no
  const isCollapsed = state === 'collapsed';

  return (
    <Sidebar collapsible="icon" variant="inset">
      {/* Logo superior */}
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

      <SidebarContent className="pt-3 pb-2 space-y-4">
        {/* 🧭 Bloque principal */}
        <div className="flex flex-col gap-1 pl-3">
          <div className="flex items-center mb-1 gap-2">
            <Layers3 className={`ml-1 w-5 h-5 ${isilBlue}`} />
            {!isCollapsed && (
              <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                Principal
              </span>
            )}
          </div>

          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link
                  href="/dashboard"
                  className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                  <BarChart3 className={`w-5 h-5 ${isilBlue}`} />
                  <span className="text-gray-800 dark:text-gray-100 font-medium">Dashboard</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link
                  href="/syllabus"
                  className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                  <FileText className={`w-5 h-5 ${isilBlue}`} />
                  <span className="text-gray-800 dark:text-gray-100 font-medium">Syllabus</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link
                  href="/careers"
                  className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                  <BookOpen className={`w-5 h-5 ${isilBlue}`} />
                  <span className="text-gray-800 dark:text-gray-100 font-medium">Carreras</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link
                  href="/courses"
                  className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                  <GraduationCap className={`w-5 h-5 ${isilBlue}`} />
                  <span className="text-gray-800 dark:text-gray-100 font-medium">Cursos ISIL</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link
                  href="/job-offers"
                  className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                  <BriefcaseBusiness className={`w-5 h-5 ${isilBlue}`} />
                  <span className="text-gray-800 dark:text-gray-100 font-medium">
                    Bolsa de Empleo
                  </span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </div>

        {/* 🧠 Bloque de IA */}
        <div className="flex flex-col gap-1 pl-3 border-t border-gray-200 dark:border-gray-700 pt-2">
          <div className="flex items-center mb-1 gap-2">
            <Brain className={`ml-1 w-5 h-5 ${isilBlue}`} />
            {!isCollapsed && (
              <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                Inteligencia Artificial
              </span>
            )}
          </div>

          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link
                  href="/admin/ai-trainings"
                  className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                  <Database className={`w-5 h-5 ${isilBlue}`} />
                  <span className="text-gray-800 dark:text-gray-100 font-medium">
                    Entrenamiento IA
                  </span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </div>

        {/* ⚙️ Bloque administrativo */}
        <div className="flex flex-col gap-1 pl-3 border-t border-gray-200 dark:border-gray-700 pt-2">
          <div className="flex items-center mb-1 gap-2">
            <Settings className={`ml-1 w-5 h-5 ${isilBlue}`} />
            {!isCollapsed && (
              <span className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                Administración
              </span>
            )}
          </div>

          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link
                  href="/users"
                  className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                  <UserCircle2 className={`w-5 h-5 ${isilBlue}`} />
                  <span className="text-gray-800 dark:text-gray-100 font-medium">Usuarios</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
              <SidebarMenuButton asChild>
                <Link
                  href="/roles"
                  className="flex items-center gap-3 px-2 py-1.5 rounded-md hover:bg-sky-100 dark:hover:bg-sky-900/30 transition"
                >
                  <Shield className={`w-5 h-5 ${isilBlue}`} />
                  <span className="text-gray-800 dark:text-gray-100 font-medium">Roles</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </div>
      </SidebarContent>

      {/* 👤 Usuario logueado */}
      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
