import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
];

export default function Dashboard() {
  const { stats } = usePage().props;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />
      <div className="p-0 w-full h-[calc(100vh-64px)]">
        <iframe
          title="SEGUIMIENTO DE ATC"
          src="https://app.powerbi.com/view?r=eyJrIjoiYTMyNTIzMDMtNzY5Ni00NDBhLWE2Y2EtOWQwYzA5NzcxY2ZmIiwidCI6IjcxODY3Y2NlLTFiY2YtNDg1Yi1iZDUwLTY4ZDk1ZWUzZjdiZiJ9"
          className="w-full h-full border-0"
          allowFullScreen
        ></iframe>
      </div>
    </AppLayout>
  );
}
