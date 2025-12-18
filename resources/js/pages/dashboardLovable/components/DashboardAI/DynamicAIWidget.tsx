import { useDashboard } from "../../DashboardContext";
import AlignmentGauge from "./AlignmentGauge";
import AICoursesPieChart from "./AICoursesPieChart";
import CurricularUpdatesBar from "./CurricularUpdatesBar";
import TopTechnologiesChart from "./TopTechnologiesChart";
import ObsolescenceGauge from "./ObsolescenceGauge";
import ImprovedCareersTable from "./ImprovedCareersTable";

export default function DynamicAIWidget() {
  const { data } = useDashboard();

  if (!data.component) return null;

  const componentsMap: Record<string, JSX.Element> = {
    AlignmentGauge: <AlignmentGauge data={data.results} />,
    AICoursesPieChart: <AICoursesPieChart data={data.results} />,
    CurricularUpdatesBar: <CurricularUpdatesBar data={data.results} />,
    TopTechnologiesChart: <TopTechnologiesChart data={data.results} />,
    ObsolescenceGauge: <ObsolescenceGauge data={data.results} />,
    ImprovedCareersTable: <ImprovedCareersTable data={data.results} />,
  };

  return (
    <div className="col-span-full bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-lg">
      <h3 className="text-blue-400 text-sm mb-2 font-semibold">
        {data.topic ?? "Indicador IA"}
      </h3>
      {componentsMap[data.component] ?? (
        <p className="text-gray-400">⚠️ Componente no encontrado.</p>
      )}
    </div>
  );
}
