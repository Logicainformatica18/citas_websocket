// Utilidades y tipos compartidos del módulo "Clasificación con IA" (aparte de index.tsx para no crear una
// importación circular: Vite dejaría la página sin entrada propia en el manifiesto).
export type PromptVersion = {
    id: number; version: number; body: string; model: string; note: string | null; status: "borrador" | "activa" | "retirada";
    created_by: string | null; created_at: string; activated_by: string | null; activated_at: string | null; decisions: number;
};
export type Prompt = { id: number; kind: "offers" | "trends"; name: string; active_version_id: number; versions: PromptVersion[] };

export const fmt = (n: number | string | undefined | null) => Number(n ?? 0).toLocaleString("es-PE");
export const usd = (n: number | string | undefined | null) => `${Number(n ?? 0).toLocaleString("es-PE", { minimumFractionDigits: 2, maximumFractionDigits: 4 })} USD`;
export const KIND_LABEL = { offers: "Ofertas", trends: "Tendencias" } as const;
