import { createContext, useContext, useState, ReactNode } from "react";

type DashboardData = {
  results: any[];
  aggregations: Record<string, any>;
};

type DashboardContextType = {
  data: DashboardData;
  updateDashboard: (results: any[], aggregations: Record<string, any>) => void;
};

const DashboardContext = createContext<DashboardContextType | undefined>(undefined);

export function DashboardProvider({ children }: { children: ReactNode }) {
  const [data, setData] = useState<DashboardData>({
    results: [],
    aggregations: {},
  });

  const updateDashboard = (results: any[], aggregations: Record<string, any>) => {
    setData({ results, aggregations });
  };

  return (
    <DashboardContext.Provider value={{ data, updateDashboard }}>
      {children}
    </DashboardContext.Provider>
  );
}

export function useDashboard() {
  const ctx = useContext(DashboardContext);
  if (!ctx) throw new Error("useDashboard debe usarse dentro de DashboardProvider");
  return ctx;
}
