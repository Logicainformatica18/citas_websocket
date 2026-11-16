import { RankingEvolutionModal } from "@/components/ranking/RankingEvolutionModal";

interface WeeklyEvolutionCertificationsModalProps {
  open: boolean;
  onClose: () => void;
}

/**
 * Evolución del Ranking de Certificaciones (modal compartido con Lenguajes y Tecnologías).
 * Cada ventana es la foto del ranking semestral en su fecha de corte; los datos salen de
 * certification_ranking_weekly(_meta), que llena ranking:refresh-certifications.
 */
export function WeeklyEvolutionCertificationsModal({ open, onClose }: WeeklyEvolutionCertificationsModalProps) {
  return (
    <RankingEvolutionModal
      open={open}
      onClose={onClose}
      basePath="/dashboard/ranking-certificaciones"
      description="Evolución de certificaciones"
      subtitle="Top certificaciones por demanda laboral en el sector IT"
    />
  );
}
