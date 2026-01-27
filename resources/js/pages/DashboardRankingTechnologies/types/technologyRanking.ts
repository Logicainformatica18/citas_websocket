export type TechnologyRanking = {
  id: number;
  name: string;

  /* =============================
     CONTEXTO DEL ITEM
  ============================= */
  entity_type: "technology" | "trend";
  is_isil: number;
  is_real_trend?: number;

  /**
   * 🔥 Fuente real del click
   * - technology  → jobs por technology
   * - trend       → jobs por technology_trend
   */
  __source?: "technology" | "trend";

  /* =============================
     MÉTRICAS
  ============================= */
  total_jobs: number;
  labor_score: number;
  trend_score: number;
  trend_reports?: number;
  final_score: number;

  /* =============================
     METADATA
  ============================= */
  category?: string;

  year?: number;
  quarter?: number;

  source_title?: string;
  source_url?: string;
  source_type?: string;
};
