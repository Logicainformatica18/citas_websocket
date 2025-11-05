import axios from "axios";

export async function fetchWidgets() {
  const res = await axios.get("/api/ai/dashboard-widgets?dashboard_id=1");
  return res.data.widgets;
}

export async function reorderWidgets(widgets: any[]) {
  return axios.post("/api/ai/dashboard-widgets/reorder", { widgets });
}

export async function updateWidget(id: number, data: any) {
  return axios.put(`/api/ai/dashboard-widgets/${id}`, data);
}

export async function deleteWidget(id: number) {
  return axios.delete(`/api/ai/dashboard-widgets/${id}`);
}
