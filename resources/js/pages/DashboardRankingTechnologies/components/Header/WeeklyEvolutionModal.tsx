import { RankingEvolutionModal } from "@/components/ranking/RankingEvolutionModal";

interface WeeklyEvolutionModalProps {
  open: boolean;
  onClose: () => void;
}

/**
 * Evolución del Ranking de Tecnologías (modal compartido con Lenguajes).
 * Cada ventana es la foto del ranking semestral en su fecha de corte.
 */
export function WeeklyEvolutionModal({ open, onClose }: WeeklyEvolutionModalProps) {
  return (
    <RankingEvolutionModal
      open={open}
      onClose={onClose}
      basePath="/dashboard/ranking/technologies"
      description="Evolución de tecnologías"
      subtitle="Top tecnologías por demanda laboral en el sector IT"
    />
  );
}
