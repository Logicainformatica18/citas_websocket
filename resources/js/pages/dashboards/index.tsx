import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface PageProps {
  stats: {
    totalSupports: number;
    attendedSupports: number;
    pendingSupports: number;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
];

export default function Dashboard() {
  const { stats } = usePage<PageProps>().props;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />
      <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div className="grid auto-rows-min gap-4 md:grid-cols-3">
          <div className="border bg-white dark:bg-gray-900 relative aspect-video overflow-hidden rounded-xl p-4">
            <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400">Total Soportes</h2>
            <p className="text-3xl font-bold text-gray-900 dark:text-white">{stats.totalSupports}</p>
          </div>
          <div className="border bg-white dark:bg-gray-900 relative aspect-video overflow-hidden rounded-xl p-4">
            <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400">Atendidos</h2>
            <p className="text-3xl font-bold text-green-600">{stats.attendedSupports}</p>
          </div>
          <div className="border bg-white dark:bg-gray-900 relative aspect-video overflow-hidden rounded-xl p-4">
            <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400">Pendientes</h2>
            <p className="text-3xl font-bold text-red-600">{stats.pendingSupports}</p>
          </div>
        </div>

        <div className="border bg-white dark:bg-gray-900 relative min-h-[300px] flex-1 overflow-hidden rounded-xl p-6">
          <h2 className="text-lg font-semibold text-gray-700 dark:text-white mb-4">Resumen General</h2>
          <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
        </div>
      </div>
    </AppLayout>
  );
}
