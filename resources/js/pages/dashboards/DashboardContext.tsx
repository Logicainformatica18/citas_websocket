import { createContext, useContext, useState, ReactNode } from "react";
import axios from "axios";

type DashboardData = {
  results: any;
  aggregations?: Record<string, any>;
  instruction?: any;
  component?: string | null;
  topic?: string | null;
};

type DashboardContextType = {
  /** Estado IA / VERA */
  data: DashboardData;
updateDashboard: (
  results: any,
  topic?: string | null,
  component?: string | null,
  aggregations?: Record<string, any>,
  instruction?: any,
  options?: { silent?: boolean }
) => void;




  /** 🧠 DASHBOARD ACTIVO (🔥 FALTABA) */
  activeDashboard: any | null;
  setActiveDashboard: (d: any) => void;

  /** 🔄 REFRESH DASHBOARD */
  refreshKey: number;
  isRefreshing: boolean;
  refreshDashboard: () => void;
  stopRefreshing: () => void;
};

 

const DashboardContext = createContext<DashboardContextType | undefined>(
  undefined
);

export function DashboardProvider({ children }: { children: ReactNode }) {

  /** ===== DATA IA ===== */
  const [data, setData] = useState<DashboardData>({
    results: null,
    aggregations: {},
    instruction: undefined,
    component: null,
    topic: null,
  });
/** 🧠 DASHBOARD ACTIVO */
const [activeDashboard, setActiveDashboard] = useState<any | null>(null);

  /** ===== REFRESH STATE ===== */
  const [refreshKey, setRefreshKey] = useState(0);
  const [isRefreshing, setIsRefreshing] = useState(false);

  /** ===== ACTUALIZAR DASHBOARD IA ===== */
 const updateDashboard = (
  results: any,
  topic?: string | null,
  component?: string | null,
  aggregations: Record<string, any> = {},
  instruction?: any,
  options?: { silent?: boolean }
) => {
  // 🔕 actualización silenciosa
  if (options?.silent) {
    return;
  }

  setData({ results, topic, component, aggregations, instruction });
};




  /** ===== 🔄 REFRESH GLOBAL ===== */
const refreshDashboard = async () => {
  if (!activeDashboard?.id) return;

  setIsRefreshing(true);

  try {
    // 1️⃣ Traer widgets
    const res = await axios.get(
      `/api/ai/dashboards/${activeDashboard.id}/widgets`
    );

    const widgets = res.data.widgets || [];

    // 2️⃣ Recalcular uno por uno (MISMO refresh)
    for (const w of widgets) {
      await axios.post(
        `/api/ai/dashboards/${activeDashboard.id}/widgets/${w.id}/refresh`
      );
    }

    // 3️⃣ Forzar reload visual
    setRefreshKey((k) => k + 1);

  } finally {
    setIsRefreshing(false);
  }
};


  const stopRefreshing = () => {
    setIsRefreshing(false);
  };

  return (
    <DashboardContext.Provider
      value={{
        data,
        updateDashboard,
  activeDashboard,        // 🔥
    setActiveDashboard,     // 🔥
        refreshKey,
        isRefreshing,
        refreshDashboard,
        stopRefreshing,
      }}
    >
      {children}
    </DashboardContext.Provider>
  );
}

export function useDashboard() {
  const ctx = useContext(DashboardContext);
  if (!ctx)
    throw new Error("useDashboard debe usarse dentro de DashboardProvider");
  return ctx;
}
