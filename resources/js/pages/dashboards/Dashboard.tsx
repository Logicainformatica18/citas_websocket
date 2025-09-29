import { useState } from "react";
import Split from "react-split";
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import AlignmentChart from './components/AlignmentChart';
import ObsolescenceChart from './components/ObsolescenceChart';
import RolesChart from './components/RolesChart';
import ObsolescenceIA from './components/ObsolescenceIA';
import TechnologiesChart from './components/TechnologiesChart';
import WorkModeChart from './components/WorkModeChart';
import CityDemandMap from './components/CityDemandMap';
import CareerAlignmentChart from './components/CareerAlignmentChart';
import DemandByCareerChart from './components/DemandByCareerChart';
import EmploymentRequestChart from './components/EmploymentRequestChart';
import AiChat from './components/AiChat';
import { DashboardProvider } from "./DashboardContext";
import { Menu } from "lucide-react";

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
];

export default function Dashboard() {
  const [sizes, setSizes] = useState([95, 5]); // contenido 95%, chat 5%
  const [collapsed, setCollapsed] = useState(false);

  const togglePanel = () => {
    if (collapsed) {
      setSizes([65, 35]); // expandir chat
      setCollapsed(false);
    } else {
      setSizes([95, 5]); // colapsar chat
      setCollapsed(true);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />

      <DashboardProvider>
        <div className="relative h-[calc(100vh-64px)]">
          {/* Botón hamburguesa */}
          <button
            onClick={togglePanel}
            className="absolute top-2 right-2 z-50 bg-gray-800 text-white p-2 rounded-md shadow hover:bg-gray-700"
          >
            <Menu size={20} />
          </button>

    <Split
  className="flex h-full bg-gray-900 text-white"
  sizes={sizes}
  minSize={300}
  gutterSize={12}
  gutterAlign="center"
  snapOffset={50}
  onDragEnd={setSizes}
  gutterStyle={() => ({ backgroundColor: "#374151", cursor: "col-resize" })}
>


            {/* Panel izquierdo → Cards en filas */}
            <div className="overflow-y-auto p-4 space-y-6">

              {/* Bloque 1 → dos cards a la izquierda + mapa grande a la derecha */}
              <div className="grid grid-cols-3 gap-4">
                {/* Columna izquierda → 2 filas */}
                <div className="flex flex-col gap-4">
                  <AlignmentChart />
                  <ObsolescenceChart />
                </div>

                {/* Columna derecha → ocupa ambas filas */}
                <div className="col-span-2 row-span-2 h-100">
                  <CityDemandMap />
                </div>
              </div>

              {/* Bloque 2 */}
              <div className="grid grid-cols-4 gap-4">
                <RolesChart />
                <TechnologiesChart />
                <WorkModeChart />
                <ObsolescenceIA />
              </div>

              {/* Bloque 3 */}
              <div className="grid grid-cols-3 gap-4">
                <DemandByCareerChart />
                <CareerAlignmentChart />
                <EmploymentRequestChart />
              </div>
            </div>

            {/* Panel derecho (AI Chat) */}
            <div className="flex flex-col h-full overflow-y-auto p-4 border-l border-gray-700">
              <AiChat />
            </div>
          </Split>
        </div>
      </DashboardProvider>
    </AppLayout>
  );
}
