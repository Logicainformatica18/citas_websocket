import CertificationCard from "./CertificationCard";
import { CertificationRanking } from "../../types/ranking";

type Props = {
  items: CertificationRanking[];
  onSelectCertification: (cert: CertificationRanking) => void;
};

export default function RankingList({ items, onSelectCertification }: Props) {
  return (
    <div className="space-y-4">
      {items.map((item, index) => (
        <CertificationCard
          key={item.id}
          rank={index + 1}
          data={item}
          onClick={() => onSelectCertification(item)}
        />
      ))}
    </div>
  );
}
