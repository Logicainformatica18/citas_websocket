import { rankingCertificationsMock } from "../mocks/rankingCertifications.mock";

export function useRankingData() {
  return {
    data: rankingCertificationsMock,
    kpis: [
      { title: "Certificación Top", value: "AWS Solutions", score: 90.6 },
      { title: "Alta Demanda", value: 7, trend: 12 },
      { title: "Alta Proyección", value: 12, trend: 8 },
      { title: "Área Destacada", value: "Cloud Computing" },
    ],
  };
}
