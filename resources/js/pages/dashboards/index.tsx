import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import StatCards from './StatCards';
import StatCharts from './StatCharts';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
];

export default function Dashboard() {
  const { stats } = usePage().props;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />
      <div className="p-4 space-y-6">
        <StatCards stats={stats} />
        <StatCharts stats={stats} />
      </div>
    </AppLayout>
  );
}
