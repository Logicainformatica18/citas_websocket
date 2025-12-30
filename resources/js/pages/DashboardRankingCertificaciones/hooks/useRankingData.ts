import { RankingResponse } from "../types/ranking";

export function useRankingData(ranking: RankingResponse[]) {
  /**
   * Aquí luego puedes:
   * - aplicar pesos
   * - ordenar por score
   * - filtrar por vendor / level
   * - recalcular métricas
   */

  return {
    data: ranking,
  };
}
