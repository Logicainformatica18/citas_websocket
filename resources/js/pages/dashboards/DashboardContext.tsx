import { createContext, useContext, useState, ReactNode } from "react";

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
    instruction?: any
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
    instruction?: any
  ) => {
    setData({ results, topic, component, aggregations, instruction });
  };

  /** ===== 🔄 REFRESH GLOBAL ===== */
  const refreshDashboard = () => {
    setIsRefreshing(true);
    setRefreshKey((k) => k + 1);
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
