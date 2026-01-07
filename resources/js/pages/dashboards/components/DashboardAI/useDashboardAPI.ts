// import axios from "axios";

// export async function fetchWidgets() {
//   const res = await axios.get("/api/ai/dashboard-widgets?dashboard_id=1");
//   return res.data.widgets;
// }

// export async function reorderWidgets(widgets: any[]) {
//   return axios.post("/api/ai/dashboard-widgets/reorder", { widgets });
// }

// export async function updateWidget(id: number, data: any) {
//   return axios.put(`/api/ai/dashboard-widgets/${id}`, data);
// }

// export async function deleteWidget(id: number) {
//   return axios.delete(`/api/ai/dashboard-widgets/${id}`);
// }

 
// export async function fetchSections(dashboardId = 1) {
//   const res = await axios.get(`/api/ai/dashboard-sections/${dashboardId}`);
//   return res.data.sections;
// }

 
// export async function createSection(data: any) {
//   return axios.post("/api/ai/dashboard-sections", data);
// }

 
// export async function updateSection(id: number, data: any) {
//   return axios.post(`/api/ai/dashboard-sections/${id}/update`, data);
// }

 
// export async function deleteSection(id: number) {
//   return axios.delete(`/api/ai/dashboard-sections/${id}`);
// }
// export async function segmentWidget(widgetId: number, filters: Record<string, any>) {
//   const res = await axios.post(`/api/ai/dashboard-widgets/${widgetId}/segment`, { filters });
//   return res.data;
// }
