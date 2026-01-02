import CertificationCard from "./CertificationCard";
import { CertificationRanking } from "../../types/ranking";

type Props = {
  items: CertificationRanking[];
  onSelectCertification: (cert: CertificationRanking) => void;
};

export default function RankingList({
  items,
  onSelectCertification,
  startRank = 0,
}: Props) {
  return (
    <div
      className="
        grid
        grid-cols-1
        md:grid-cols-2
        gap-6
        w-full
      "
    >
      {items.map((item, index) => (
        <CertificationCard
          key={item.id}
          rank={startRank + index + 1}
          data={item}
          onClick={() => onSelectCertification(item)}
        />
      ))}
    </div>
  );
}

