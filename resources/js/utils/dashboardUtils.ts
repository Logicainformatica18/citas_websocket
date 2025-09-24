export function updateDashboard(payload: any) {
  // Mandamos el payload completo (puede traer results, aggregations, filtros, etc.)
  window.dispatchEvent(
    new CustomEvent("dashboard:update", { detail: payload })
  );
}
