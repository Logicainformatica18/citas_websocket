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

/**
 * 📋 Obtener todas las secciones del dashboard
 */
export async function fetchSections(dashboardId = 1) {
  const res = await axios.get(`/api/ai/dashboard-sections/${dashboardId}`);
  return res.data.sections;
}

/**
 * ➕ Crear nueva sección
 */
export async function createSection(data: any) {
  return axios.post("/api/ai/dashboard-sections", data);
}

/**
 * ✏️ Actualizar sección (título, descripción, posición, etc.)
 */
export async function updateSection(id: number, data: any) {
  return axios.post(`/api/ai/dashboard-sections/${id}/update`, data);
}

/**
 * 🗑️ Eliminar sección
 */
export async function deleteSection(id: number) {
  return axios.delete(`/api/ai/dashboard-sections/${id}`);
}
