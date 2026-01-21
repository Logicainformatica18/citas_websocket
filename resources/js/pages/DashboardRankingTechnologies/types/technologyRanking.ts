export type TechnologyRanking = {
  id: number;
  name: string;
  entity_type: "technology" | "trend";
  is_isil: number;
  is_real_trend?: number;

  total_jobs: number;
  labor_score: number;
  trend_score: number;
  trend_reports?: number; // 👈 AÑADIR ESTO
  final_score: number;

  category?: string;
  year?: number;
  quarter?: number;
  source_title?: string;
  source_url?: string;
  source_type?: string;
};
