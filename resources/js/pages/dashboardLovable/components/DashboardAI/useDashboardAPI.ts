import axios from "axios";

/* ======================================================
   📊 WIDGETS – API (OBLIGATORIO dashboardId)
====================================================== */

export async function fetchWidgets(setActiveDashboard?: (d: any) => void) {
  const res = await axios.get('/api/ai/dashboards/1/widgets');

  if (setActiveDashboard && res.data.dashboard) {
    setActiveDashboard(res.data.dashboard); // 🔥 AQUÍ
  }

  return res.data.widgets;
}


export async function reorderWidgets(
  dashboardId: number,
  widgets: any[]
) {
  return axios.post(
    `/api/ai/dashboards/${dashboardId}/widgets/reorder`,
    { widgets }
  );
}

export async function updateWidget(
  dashboardId: number,
  widgetId: number,
  data: any
) {
  return axios.put(
    `/api/ai/dashboards/${dashboardId}/widgets/${widgetId}`,
    data
  );
}

export async function deleteWidget(
  dashboardId: number,
  widgetId: number
) {
  return axios.delete(
    `/api/ai/dashboards/${dashboardId}/widgets/${widgetId}`
  );
}

export async function updateWidgetColor(
  dashboardId: number,
  widgetId: number,
  color: string,
  field: "primary" | "bg" | "text" | "border" = "primary"
) {
  return axios.patch(
    `/api/ai/dashboards/${dashboardId}/widgets/${widgetId}/color`,
    { color, field }
  );
}

export async function saveWidgetFilters(
  dashboardId: number,
  widgetId: number,
  activeLabels: string[]
) {
  return axios.post(
    `/api/ai/dashboards/${dashboardId}/widgets/${widgetId}/filters`,
    { filters: { activeLabels } }
  );
}

export async function createWidgetFromTraining(
  dashboardId: number,
  training_id: number,
  chart_type: string
) {
  return axios.post(
    `/api/ai/dashboards/${dashboardId}/widgets/from-training`,
    { training_id, chart_type }
  );
}

export async function segmentWidget(
  widgetId: number,
  filters: Record<string, any>
) {
  const res = await axios.post(
    `/api/ai/dashboard-widgets/${widgetId}/segment`,
    { filters }
  );
  return res.data;
}
