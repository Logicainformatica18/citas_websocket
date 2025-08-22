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
      <div className="p-0 w-full h-[calc(100vh-64px)]">
        {/* ocupa todo el ancho y la altura menos el header (ajusta 64px según tu layout) */}
        <iframe
          title="SEGUIMIENTO DE ATC"
          src="https://app.powerbi.com/view?r=eyJrIjoiYmYwZjZlMzgtN2E0OC00MWMwLTg3OGEtMmJkZjAyZTI3YTI0IiwidCI6IjcxODY3Y2NlLTFiY2YtNDg1Yi1iZDUwLTY4ZDk1ZWUzZjdiZiJ9"
          className="w-full h-full border-0"
          allowFullScreen
        ></iframe>
      </div>
    </AppLayout>
  );
}
