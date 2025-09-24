import Split from "react-split";
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import AlignmentChart from './components/AlignmentChart';
import ObsolescenceChart from './components/ObsolescenceChart';
import RolesChart from './components/RolesChart';
import TechnologiesChart from './components/TechnologiesChart';
import WorkModeChart from './components/WorkModeChart';
import DemandByCareerChart from './components/DemandByCareerChart';
import CityDemandMap from './components/CityDemandMap';
import CareerAlignmentChart from './components/CareerAlignmentChart';
import EmploymentRequestChart from './components/EmploymentRequestChart';
import AiChat from './components/AiChat';
import { DashboardProvider } from "./DashboardContext";
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
];

export default function Dashboard() {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />

      <DashboardProvider>
        <Split
          className="flex h-[calc(100vh-64px)] bg-gray-900 text-white"
          sizes={[75, 25]}
          minSize={400}
          gutterSize={12}
          gutterAlign="center"
          snapOffset={50}
          gutterClassName="gutter"
        >
          <div className="overflow-y-auto p-4">
            <div className="grid grid-cols-3 gap-4">
              <AlignmentChart />
              <ObsolescenceChart />
              <RolesChart />
              <TechnologiesChart />
              <WorkModeChart />
              <DemandByCareerChart />
              <CityDemandMap />
              <CareerAlignmentChart />
              <EmploymentRequestChart />
            </div>
          </div>

          <div className="flex flex-col h-full overflow-y-auto p-4">
            <AiChat />
          </div>
        </Split>
      </DashboardProvider>
    </AppLayout>
  );
}
