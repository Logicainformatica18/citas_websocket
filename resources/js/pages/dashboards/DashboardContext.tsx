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
  refreshDashboard: () => Promise<void>;
  reloadWidgets: () => void;
  stopRefreshing: () => void;
};

 

/** Cuántos widgets se recalculan a la vez; más saturaría MySQL con SQL pesados. */
const REFRESH_CONCURRENCY = 3;

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

    // 2️⃣ Recalcular en paralelo, de a REFRESH_CONCURRENCY a la vez. En serie, el
    // tiempo total era la suma de todos los widgets; así queda cerca del más lento.
    // allSettled: si un widget falla, los demás igual se recalculan.
    const queue = [...widgets];
    const worker = async () => {
      while (queue.length > 0) {
        const w = queue.shift();
        await axios
          .post(`/api/ai/dashboards/${activeDashboard.id}/widgets/${w.id}/refresh`)
          .catch((err) => console.error(`Error recalculando widget ${w.id}`, err));
      }
    };
    await Promise.allSettled(
      Array.from({ length: Math.min(REFRESH_CONCURRENCY, widgets.length) }, worker)
    );

    // 3️⃣ Forzar reload visual
    setRefreshKey((k) => k + 1);

  } finally {
    setIsRefreshing(false);
  }
};

  /** ===== 🔁 RECARGAR SIN RECALCULAR ===== */
  // Vuelve a leer los widgets con los datos ya guardados, sin ejecutar sus SQL.
  const reloadWidgets = () => setRefreshKey((k) => k + 1);


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
        reloadWidgets,
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
