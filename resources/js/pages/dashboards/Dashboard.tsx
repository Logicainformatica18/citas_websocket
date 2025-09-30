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

export default function Dashboard({ initialData }: { initialData: any }) {
    const [sizes, setSizes] = useState([98, 2]);
    const [collapsed, setCollapsed] = useState(false);

    const togglePanel = () => {
        if (collapsed) {
            setSizes([97, 3]);
            setCollapsed(false);
        } else {
            setSizes([97, 3]);
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
                        <div className="overflow-y-auto p-4 space-y-6">

                            {/* Bloque 1 → dos cards + mapa */}
                            {/* Bloque 1 → 4 cards a la izquierda + mapa grande a la derecha */}
                            {/* Bloque 1 → 4 cards (2x2) + mapa grande */}
                            <div className="grid grid-cols-6 gap-2">
                                {/* Columna izquierda: 2x2 cards */}
                                <div className="grid grid-cols-1 gap-1 col-span-3">


                                <TechnologiesChart />
                                </div>

                                {/* Columna derecha: mapa ocupa el alto de las 2 filas */}
                                <div className="col-span-3  row-span-1 ">
                                    <CityDemandMap />
                                </div>
                            </div>



                            {/* Bloque 2 */}
                            <div className="grid grid-cols-3 gap-4">
 <ObsolescenceIA />
                                    <AlignmentChart />
                                {/* 🔹 Aquí pasamos los datos iniciales */}
                                <WorkModeChart initialData={initialData?.workmode} />

                            </div>

                            {/* Bloque 3 */}
                            <div className="grid grid-cols-3 gap-4">
                                  <ObsolescenceChart />
                                    <RolesChart />
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
