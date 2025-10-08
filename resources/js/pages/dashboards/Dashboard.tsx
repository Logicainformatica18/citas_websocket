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
import WordlBankChart from './components/WorldBankChart';
import DemandByCareerChart from './components/DemandByCareerChart';
import EmploymentRequestChart from './components/EmploymentRequestChart';
import AiChat from './components/AiChat';
import MetricsChart from './components/MetricsChart';
import { DashboardProvider } from "./DashboardContext";
import { Menu } from "lucide-react";

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
];

// 🔹 Card compacta y expansible
function ExpandableCard({ children, onToggleExpand, isExpanded }: any) {
  return (
    <div
      onDoubleClick={onToggleExpand}
      className={`transition-all duration-500 ease-in-out
        bg-gray-800 rounded-lg shadow overflow-hidden cursor-pointer
        min-h-[150px] hover:shadow-lg
        ${isExpanded ? "col-span-full scale-[1.01] z-20" : ""}
      `}
    >
      {children}
    </div>
  );
}

export default function Dashboard({ initialData }: { initialData: any }) {
  const [sizes, setSizes] = useState([98, 2]);
  const [collapsed, setCollapsed] = useState(false);
  const [expandedCard, setExpandedCard] = useState<number | null>(null);

  const togglePanel = () => setCollapsed(!collapsed);
  const handleExpand = (id: number) =>
    setExpandedCard(expandedCard === id ? null : id);

  const splitSizes = expandedCard !== null ? [100, 0] : sizes;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />

      <DashboardProvider>
        <div className="relative h-[calc(100vh-64px)]">
          {/* Botón menú */}
          <button
            onClick={togglePanel}
            className="absolute top-2 right-2 z-50 bg-gray-800 text-white p-2 rounded-md shadow hover:bg-gray-700"
          >
            <Menu size={20} />
          </button>

          <Split
            className="flex h-full bg-gray-900 text-white"
            sizes={splitSizes}
            minSize={280}
            gutterSize={5}
            gutterAlign="center"
            snapOffset={50}
            onDragEnd={setSizes}
            gutter={() => {
              const gutter = document.createElement("div");
              gutter.className = "gutter bg-gray-700 hover:bg-gray-600 transition-colors";
              return gutter;
            }}
          >
            {/* Panel izquierdo */}
            <div className="overflow-y-auto p-3 space-y-2">

              {/* 🔹 Métricas globales IA */}
              <ExpandableCard
                isExpanded={expandedCard === 0}
                onToggleExpand={() => handleExpand(0)}
              >
                <WordlBankChart></WordlBankChart>
                {/* <MetricsChart /> */}
              </ExpandableCard>

              {/* Bloque 1 */}
              <div className="grid grid-cols-2 gap-2">
                <ExpandableCard
                  isExpanded={expandedCard === 1}
                  onToggleExpand={() => handleExpand(1)}
                >
                  <TechnologiesChart />
                </ExpandableCard>

                <ExpandableCard
                  isExpanded={expandedCard === 2}
                  onToggleExpand={() => handleExpand(2)}
                >
                  <CityDemandMap />
                </ExpandableCard>
              </div>

              {/* Bloque 2 */}
              <div className="grid grid-cols-3 gap-3">
                <ExpandableCard
                  isExpanded={expandedCard === 3}
                  onToggleExpand={() => handleExpand(3)}
                >
                  <ObsolescenceIA />
                </ExpandableCard>
                <ExpandableCard
                  isExpanded={expandedCard === 4}
                  onToggleExpand={() => handleExpand(4)}
                >
                  <AlignmentChart />
                </ExpandableCard>
                <ExpandableCard
                  isExpanded={expandedCard === 5}
                  onToggleExpand={() => handleExpand(5)}
                >
                  <WorkModeChart initialData={initialData?.workmode} />
                </ExpandableCard>
              </div>

              {/* Bloque 3 */}
              <div className="grid grid-cols-3 gap-3">
                <ExpandableCard
                  isExpanded={expandedCard === 6}
                  onToggleExpand={() => handleExpand(6)}
                >
                  <ObsolescenceChart />
                </ExpandableCard>
                <ExpandableCard
                  isExpanded={expandedCard === 7}
                  onToggleExpand={() => handleExpand(7)}
                >
                  <RolesChart />
                </ExpandableCard>
                <ExpandableCard
                  isExpanded={expandedCard === 8}
                  onToggleExpand={() => handleExpand(8)}
                >
                  <DemandByCareerChart />
                </ExpandableCard>
                <ExpandableCard
                  isExpanded={expandedCard === 9}
                  onToggleExpand={() => handleExpand(9)}
                >
                  <CareerAlignmentChart />
                </ExpandableCard>
                <ExpandableCard
                  isExpanded={expandedCard === 10}
                  onToggleExpand={() => handleExpand(10)}
                >
                  <EmploymentRequestChart />
                </ExpandableCard>
              </div>
            </div>

            {/* Panel derecho (AI Chat) */}
            {expandedCard === null && (
              <div className="flex flex-col h-full overflow-y-auto p-3 border-l border-gray-700">
                <AiChat />
              </div>
            )}
          </Split>
        </div>
      </DashboardProvider>
    </AppLayout>
  );
}
