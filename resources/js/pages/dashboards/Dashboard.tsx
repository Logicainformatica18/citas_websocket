import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

/* ===== Charts & Widgets ===== */
import AlignmentChart from "./components/AlignmentChart";
import ObsolescenceChart from "./components/ObsolescenceChart";
import RolesChart from "./components/RolesChart";
import ObsolescenceIA from "./components/ObsolescenceIA";
import TechnologiesChart from "./components/TechnologiesChart";
import WorkModeChart from "./components/WorkModeChart";
import CityDemandMap from "./components/CityDemandMap/CityDemandMap";
import CareerAlignmentChart from "./components/CareerAlignmentChart";
import WordlBankChart from "./components/WorldBankChart";
import DemandByCareerChart from "./components/DemandByCareerChart";
import EmploymentRequestChart from "./components/EmploymentRequestChart";

/* ===== Metrics ===== */
import LanguageMetricsIndex from "./components/Metrics/CareerLanguageIndex";
import CareerTechnologyAlignmentCard from "./components/Metrics/CareerTechnologyAlignmentCard";
import CareerMethodologyAlignmentCard from "./components/Metrics/CareerMethodologyAlignmentCard";

/* ===== AI ===== */
import DynamicAIWidget from "./components/DashboardAI/DynamicAIWidget";
import DashboardAIWidgets from "./DashboardAIWidgets";
import AiChatView from "./components/AiChat/AiChatView";

/* ===== Others ===== */
import ProfileBlock from "./components/StackOverFlow/Profileblock";
import { DashboardProvider } from "./DashboardContext";
import { MessageCircle } from "lucide-react";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
];

/* =========================================================
   Expandable Card (Lovable Style)
========================================================= */
function ExpandableCard({
  children,
  onToggleExpand,
  isExpanded,
}: {
  children: React.ReactNode;
  onToggleExpand: () => void;
  isExpanded: boolean;
}) {
  return (
    <div
      onDoubleClick={onToggleExpand}
      className={`
        transition-all duration-300 ease-out
        bg-white dark:bg-gray-900
        border border-[#A7E5F6] dark:border-gray-700
        rounded-xl
        shadow-sm hover:shadow-md
        cursor-pointer
        min-h-[160px]
        ${isExpanded ? "col-span-full scale-[1.01] z-20" : ""}
      `}
    >
      {children}
    </div>
  );
}

/* =========================================================
   Dashboard
========================================================= */
export default function Dashboard({ initialData }: { initialData: any }) {
  const [expandedCard, setExpandedCard] = useState<number | null>(null);
  const [showChat, setShowChat] = useState(false);

  const handleExpand = (id: number) =>
    setExpandedCard(expandedCard === id ? null : id);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />

      {/* ===== CONTENEDOR PRINCIPAL ===== */}
      <div className="relative min-h-[calc(100vh-64px)] bg-[#F7FBFD] dark:bg-gray-950 overflow-hidden">
        <div className="relative p-4 md:p-6 space-y-6 z-10">
          <DashboardProvider>

            {/* ===== WIDGET IA SUPERIOR ===== */}
            <DynamicAIWidget />

            {/* ===== DASHBOARD IA VERA ===== */}
            <div className="mt-8 border-t border-[#A7E5F6] pt-6 pb-10">
              <h2 className="text-xl font-bold text-[#0A4E61] mb-4 flex items-center gap-2">
                🤖 Dashboard generado por VERA
              </h2>

              <DashboardAIWidgets />
            </div>

            {/* ===== MÉTRICAS ===== */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <ExpandableCard
                isExpanded={expandedCard === 0}
                onToggleExpand={() => handleExpand(0)}
              >
                <LanguageMetricsIndex />
              </ExpandableCard>

              <ExpandableCard
                isExpanded={expandedCard === 1}
                onToggleExpand={() => handleExpand(1)}
              >
                <CareerTechnologyAlignmentCard />
              </ExpandableCard>

              <ExpandableCard
                isExpanded={expandedCard === 2}
                onToggleExpand={() => handleExpand(2)}
              >
                <CareerMethodologyAlignmentCard />
              </ExpandableCard>
            </div>

            {/* ===== WORLD BANK ===== */}
            <ExpandableCard
              isExpanded={expandedCard === 3}
              onToggleExpand={() => handleExpand(3)}
            >
              <WordlBankChart />
            </ExpandableCard>

            {/* ===== STACK OVERFLOW ===== */}
            <ExpandableCard
              isExpanded={expandedCard === 4}
              onToggleExpand={() => handleExpand(4)}
            >
              <ProfileBlock />
            </ExpandableCard>

            {/* ===== MAP + TECHNOLOGIES ===== */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <ExpandableCard
                isExpanded={expandedCard === 5}
                onToggleExpand={() => handleExpand(5)}
              >
                <CityDemandMap />
              </ExpandableCard>

              <ExpandableCard
                isExpanded={expandedCard === 6}
                onToggleExpand={() => handleExpand(6)}
              >
                <TechnologiesChart />
              </ExpandableCard>
            </div>

            {/* ===== CHAT FLOTANTE ===== */}
            <button
              onClick={() => setShowChat((v) => !v)}
              className="
                fixed bottom-6 right-6
                bg-[#1CBCE8] hover:bg-[#1399BE]
                text-white p-4 rounded-full
                shadow-lg transition z-50
              "
              title="Abrir chat VERA"
            >
              <MessageCircle size={24} />
            </button>

            {showChat && <AiChatView />}

          </DashboardProvider>
        </div>
      </div>
    </AppLayout>
  );
}
