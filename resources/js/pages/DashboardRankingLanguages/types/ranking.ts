export interface LanguageRanking {
  id: number;
  name: string;

  total_jobs: number;

  labor_score: number;
  trend_score: number;
  final_score: number;

  /* ===== CONTEXTO ===== */
  is_isil?: number; // 1 = ISIL, 0 = externo
  is_emergent_with_market?: number;

  entity_type?: "language" | "trend";
}
