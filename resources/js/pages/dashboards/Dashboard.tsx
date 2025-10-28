import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
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
import LanguageMetricsIndex from "./components/Metrics/CareerLanguageIndex";
import CareerTechnologyAlignmentCard from "./components/Metrics/CareerTechnologyAlignmentCard";
import CareerMethodologyAlignmentCard from "./components/Metrics/CareerMethodologyAlignmentCard";
import DynamicAIWidget from "./components/DashboardAI/DynamicAIWidget";


import AiChat from "./components/AiChat";
import MetricsChart from "./components/MetricsChart";
import { DashboardProvider } from "./DashboardContext";
import { MessageCircle } from "lucide-react";
import ProfileBlock from "./components/StackOverFlow/Profileblock";
import BackgroundParticles from "../../components/BackgroundParticles"; // 👈 Fondo animado
import MetricCard from "./components/Metrics/MetricCard";


const breadcrumbs: BreadcrumbItem[] = [
    { title: "Dashboard", href: "/dashboard" },
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
    const [expandedCard, setExpandedCard] = useState<number | null>(null);
    const [showChat, setShowChat] = useState(false);

    const handleExpand = (id: number) =>
        setExpandedCard(expandedCard === id ? null : id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            {/* 🌌 Contenedor principal con fondo animado */}
            <div className="relative min-h-[calc(100vh-64px)] bg-gray-900 text-white overflow-hidden">
                {/* 🔹 Fondo dinámico de partículas */}
                <BackgroundParticles />






                {/* 🔹 Contenido principal sobre el fondo */}
                <div className="relative p-4 overflow-y-auto space-y-4 z-10">
                    <DashboardProvider>
  <DynamicAIWidget />
{/* <div className="grid grid-cols-3 gap-3">
  <MetricCard metric="global-alignment" />
  <MetricCard metric="ai-integration" />
  <MetricCard metric="curricular-updates" />
  <MetricCard metric="tech-growth" />
  <MetricCard metric="obsolescence-index" />
  <MetricCard metric="career-improvement" />
</div> */}







                        <ExpandableCard
                            isExpanded={expandedCard === 0}
                            onToggleExpand={() => handleExpand(0)}
                        >
                            <LanguageMetricsIndex />
                        </ExpandableCard>
                        <ExpandableCard
                            isExpanded={expandedCard === 0}
                            onToggleExpand={() => handleExpand(0)}
                        >
                            <CareerTechnologyAlignmentCard />
                        </ExpandableCard>
                           <ExpandableCard
                            isExpanded={expandedCard === 0}
                            onToggleExpand={() => handleExpand(0)}
                        >
                            <CareerMethodologyAlignmentCard />
                        </ExpandableCard>
                        <ExpandableCard
                            isExpanded={expandedCard === 0}
                            onToggleExpand={() => handleExpand(0)}
                        >
                            <WordlBankChart />
                        </ExpandableCard>

                        {/* 🔹 Stack Overflow Insights */}
                        <ExpandableCard
                            isExpanded={expandedCard === 1}
                            onToggleExpand={() => handleExpand(1)}
                        >
                            <ProfileBlock />
                        </ExpandableCard>

                        {/* 🔹 City Demand & Technologies */}
                        <div className="grid grid-cols-2 gap-3">
                            <ExpandableCard
                                isExpanded={expandedCard === 2}
                                onToggleExpand={() => handleExpand(2)}
                            >
                                <CityDemandMap />
                            </ExpandableCard>
                            <ExpandableCard
                                isExpanded={expandedCard === 3}
                                onToggleExpand={() => handleExpand(3)}
                            >
                                <TechnologiesChart />
                            </ExpandableCard>
                        </div>

                        {/* 🔹 AI, Alignment, WorkMode */}
                        <div className="grid grid-cols-3 gap-3">
                            <ExpandableCard
                                isExpanded={expandedCard === 4}
                                onToggleExpand={() => handleExpand(4)}
                            >
                                <ObsolescenceIA />
                            </ExpandableCard>
                            <ExpandableCard
                                isExpanded={expandedCard === 5}
                                onToggleExpand={() => handleExpand(5)}
                            >
                                <AlignmentChart />
                            </ExpandableCard>
                            <ExpandableCard
                                isExpanded={expandedCard === 6}
                                onToggleExpand={() => handleExpand(6)}
                            >
                                <WorkModeChart initialData={initialData?.workmode} />
                            </ExpandableCard>
                        </div>

                        {/* 🔹 Último bloque */}
                        <div className="grid grid-cols-3 gap-3">
                            <ExpandableCard
                                isExpanded={expandedCard === 7}
                                onToggleExpand={() => handleExpand(7)}
                            >
                                <ObsolescenceChart />
                            </ExpandableCard>
                            <ExpandableCard
                                isExpanded={expandedCard === 8}
                                onToggleExpand={() => handleExpand(8)}
                            >
                                <RolesChart />
                            </ExpandableCard>
                            <ExpandableCard
                                isExpanded={expandedCard === 9}
                                onToggleExpand={() => handleExpand(9)}
                            >
                                <DemandByCareerChart />
                            </ExpandableCard>
                            <ExpandableCard
                                isExpanded={expandedCard === 10}
                                onToggleExpand={() => handleExpand(10)}
                            >
                                <CareerAlignmentChart />
                            </ExpandableCard>
                            <ExpandableCard
                                isExpanded={expandedCard === 11}
                                onToggleExpand={() => handleExpand(11)}
                            >
                                <EmploymentRequestChart />
                            </ExpandableCard>
                        </div>

                        {/* 🧠 Chat flotante */}
                        <button
                            onClick={() => setShowChat(!showChat)}
                            className="fixed bottom-6 right-6 bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-full shadow-lg transition z-50"
                            title="Abrir chat IA"
                        >
                            <MessageCircle size={24} />
                        </button>

                        {showChat && (
                            <div className="fixed bottom-20 right-6 bg-[#1f1f1f] border border-gray-700 rounded-lg shadow-xl w-[400px] h-[500px] z-50 flex flex-col">
                                <div className="flex justify-between items-center p-2 border-b border-gray-700 bg-gray-800 rounded-t-lg">
                                    <h4 className="text-sm font-semibold text-blue-400">
                                        💬 Vera IA
                                    </h4>
                                    <button
                                        onClick={() => setShowChat(false)}
                                        className="text-gray-400 hover:text-white"
                                    >
                                        ✕
                                    </button>
                                </div>
                                <div className="flex-1 overflow-y-auto p-2">
                                    <AiChat />
                                </div>
                            </div>
                        )}
                    </DashboardProvider>
                </div>
            </div>
        </AppLayout>
    );
}
