import { RankingEvolutionModal } from "@/components/ranking/RankingEvolutionModal";

interface WeeklyEvolutionLanguagesModalProps {
  open: boolean;
  onClose: () => void;
}

/** Evolución del Ranking de Lenguajes (modal compartido con los demás rankings). */
export function WeeklyEvolutionLanguagesModal({ open, onClose }: WeeklyEvolutionLanguagesModalProps) {
  return (
    <RankingEvolutionModal
      open={open}
      onClose={onClose}
      basePath="/dashboard/ranking/languages"
      description="Evolución de lenguajes de programación"
      subtitle="Top lenguajes por demanda laboral en el sector IT"
    />
  );
}
