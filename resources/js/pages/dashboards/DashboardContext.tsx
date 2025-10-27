import { createContext, useContext, useState, ReactNode } from "react";

type DashboardData = {
  results: any;
  aggregations?: Record<string, any>;
  instruction?: any;
  component?: string | null;
  topic?: string | null;
};

type DashboardContextType = {
  data: DashboardData;
  updateDashboard: (
    results: any,
    topic?: string | null,
    component?: string | null,
    aggregations?: Record<string, any>,
    instruction?: any
  ) => void;
};

const DashboardContext = createContext<DashboardContextType | undefined>(
  undefined
);

export function DashboardProvider({ children }: { children: ReactNode }) {
  const [data, setData] = useState<DashboardData>({
    results: null,
    aggregations: {},
    instruction: undefined,
    component: null,
    topic: null,
  });

  const updateDashboard = (
    results: any,
    topic?: string | null,
    component?: string | null,
    aggregations: Record<string, any> = {},
    instruction?: any
  ) => {
    setData({ results, topic, component, aggregations, instruction });
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
