// types/trending-certification.ts
export type TrendingCertification = {
  id: number;
  name: string;
  vendor?: string;
  level?: string;
  category?: string;

  final_score: number;
  labor_score: number;
  trend_score: number;
  total_jobs?: number;
};
