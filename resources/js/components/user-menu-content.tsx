import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { type User } from '@/types';
import { Link } from '@inertiajs/react';
import { LogOut, Settings, Moon, Sun, Monitor } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
// -------------------------------
// 🎨 Hook unificado de apariencia
// -------------------------------
type Appearance = 'light' | 'dark' | 'system';

function prefersDark(): boolean {
  if (typeof window === 'undefined') return false;
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function applyTheme(mode: Appearance) {
  const isDark = mode === 'dark' || (mode === 'system' && prefersDark());
  document.documentElement.classList.toggle('dark', isDark);
}

export function useAppearance() {
  const [appearance, setAppearance] = useState<Appearance>('system');
  const [initialized, setInitialized] = useState(false);

  // 🧩 Inicializa solo una vez
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const stored = localStorage.getItem('appearance') as Appearance | null;
    const phpTheme = document.documentElement.dataset.theme as Appearance | undefined;
    const prefers = prefersDark() ? 'dark' : 'light';

    const initial = stored || phpTheme || prefers;
    setAppearance(initial);
    applyTheme(initial);
    setInitialized(true);
  }, []);

  // 🔁 Cambiar tema
const updateAppearance = useCallback((next: Appearance) => {
  setAppearance(next);
  applyTheme(next);

  localStorage.setItem('appearance', next);
  document.cookie = `appearance=${next};path=/;max-age=${365 * 24 * 60 * 60}`;

  // 🔄 Recarga la página actual (Inertia)
  router.reload({ preserveScroll: true });
}, []);


  return { appearance, updateAppearance, initialized } as const;
}

// -------------------------------
// 👤 Componente de menú del usuario
// -------------------------------
interface UserMenuContentProps {
  user: User;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
  const cleanup = useMobileNavigation();
  const { appearance, updateAppearance, initialized } = useAppearance();

  if (!initialized) return null;

  // 🌗 Lógica de cambio cíclico (dark → light → system → dark)
  const handleToggle = () => {
    const next =
      appearance === 'dark'
        ? 'light'
        : appearance === 'light'
        ? 'system'
        : 'dark';
    updateAppearance(next);
  };

  // 🎨 Ícono dinámico
  const icon =
    appearance === 'dark' ? (
      <Sun className="mr-2 text-yellow-500" />
    ) : appearance === 'light' ? (
      <Monitor className="mr-2 text-slate-500" />
    ) : (
      <Moon className="mr-2 text-blue-500" />
    );

  // 🏷️ Etiqueta dinámica
  const label =
    appearance === 'dark'
      ? 'Cambiar a tema claro'
      : appearance === 'light'
      ? 'Usar tema del sistema'
      : 'Cambiar a tema oscuro';

  return (
    <>
      <DropdownMenuLabel className="p-0 font-normal">
        <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
          <UserInfo user={user} showEmail={true} variant="dropdown" />
        </div>
      </DropdownMenuLabel>

      <DropdownMenuSeparator />

      {/* 🌙 Alternar tema (3 modos) */}
      <DropdownMenuItem
        onClick={handleToggle}
        className="cursor-pointer text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
      >
        {icon}
        <span>{label}</span>
      </DropdownMenuItem>

      <DropdownMenuSeparator />

      <DropdownMenuGroup>
        <DropdownMenuItem asChild>
          <Link
            className="block w-full text-slate-800 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800"
            href={route('profile.edit')}
            as="button"
            prefetch
            onClick={cleanup}
          >
            <Settings className="mr-2" />
            Configuración
          </Link>
        </DropdownMenuItem>
      </DropdownMenuGroup>

      <DropdownMenuSeparator />
<DropdownMenuItem
  className="cursor-pointer text-red-600 dark:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30"
  onClick={() => {
    cleanup();
    window.location.href = route('logout');
  }}
>
  <LogOut className="mr-2 text-red-500" />
  Cerrar sesión
</DropdownMenuItem>

      {/* <DropdownMenuItem asChild>
        <Link
          className="block w-full"
          method="post"
          href={route('logout')}
          as="button"
          onClick={cleanup}
        >
          <LogOut className="mr-2 text-red-500" />
          Cerrar Sesióneeeeeeeeee
        </Link>
      </DropdownMenuItem> */}
    </>
  );
}
