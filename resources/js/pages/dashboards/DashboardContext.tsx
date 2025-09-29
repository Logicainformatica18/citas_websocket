import { createContext, useContext, useState, ReactNode } from "react";

type DashboardData = {
  results: any[];
  aggregations: Record<string, any>;
  instruction?: any; // 🔹 Nuevo: guardar la instrucción que viene del backend
};

type DashboardContextType = {
  data: DashboardData;
  updateDashboard: (
    results: any[],
    aggregations: Record<string, any>,
    instruction?: any
  ) => void;
};

const DashboardContext = createContext<DashboardContextType | undefined>(
  undefined
);

export function DashboardProvider({ children }: { children: ReactNode }) {
  const [data, setData] = useState<DashboardData>({
    results: [],
    aggregations: {},
    instruction: undefined,
  });

  const updateDashboard = (
    results: any[],
    aggregations: Record<string, any>,
    instruction?: any
  ) => {
    setData({ results, aggregations, instruction });
  };

  return (
    <DashboardContext.Provider value={{ data, updateDashboard }}>
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
