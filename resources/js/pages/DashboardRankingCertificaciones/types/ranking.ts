export interface RankingResponse {
  id: number;
  name: string;
  vendor: string;
  level: string;
  category: string;

  total_jobs: number;

  labor_score: number;
  trend_score: number;
  final_score: number;

  is_emergent_with_market: number; // 👈 ESTE ES EL CLAVE
}
