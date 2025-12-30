import { CertificationRanking } from "../types/ranking";

export const rankingCertificationsMock: CertificationRanking[] = [
  {
    id: 1,
    name: "AWS Solutions Architect Associate",
    provider: "Amazon Web Services",
    score: 90.6,
    area: "Cloud Computing",
    roles: ["Cloud Architect", "DevOps Engineer"],
    badges: ["Alta demanda", "Alta proyección"],
  },
  {
    id: 2,
    name: "AWS Cloud Practitioner",
    provider: "Amazon Web Services",
    score: 80.3,
    area: "Cloud Computing",
    roles: ["Cloud Engineer", "Support Engineer"],
    badges: ["Alta demanda", "Alta proyección"],
  },
];
