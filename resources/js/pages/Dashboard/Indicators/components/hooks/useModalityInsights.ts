type ModalityItem = {
  modalidad: string;
  porcentaje: number;
};

type TrendItem = {
  hibrido: number;
};

export function useModalityInsights(
  data: ModalityItem[],
  trendData: TrendItem[]
) {
  const remote = data.find(d => d.modalidad === "remote");
  const remotePct = remote?.porcentaje ?? 0;

  const first = trendData?.[0];
  const last = trendData?.[trendData.length - 1];

  const hybridGrowth =
    first && last
      ? +(last.hibrido - first.hibrido).toFixed(1)
      : 0;

  return {
    remotePct,
    hybridGrowth,
    insights: [
      {
        key: "remote",
        title: "Trabajo remoto en auge",
        text: `El ${remotePct}% de las vacantes tech ofrece modalidad remota, reflejando la transformación digital del mercado laboral.`,
        visible: remotePct > 0,
      },
      {
        key: "hybrid",
        title: "Híbrido en crecimiento",
        text: `Con +${hybridGrowth}% de crecimiento, el modelo híbrido se consolida como la opción preferida por empresas.`,
        visible: hybridGrowth !== 0,
      },
    ],
  };
}
