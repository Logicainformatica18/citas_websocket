export interface CertificationRanking {
  id: number;
  name: string;
  vendor: string;
  level: string;
  category?: string;
  roles?: string[];

  total_jobs: number;

  labor_score: number;
  trend_score: number;
  final_score: number;
}
