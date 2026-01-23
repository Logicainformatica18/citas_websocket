export type CareerRankingRow = {
  id: number;
  name: string;
  total_jobs: number;
};

export type RankingFilters = {
  year: number;
  period: "s1" | "s2";
  career: string[];
};
