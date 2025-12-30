import CertificationCard from "./CertificationCard";
import { CertificationRanking } from "../../types/ranking";

export default function RankingList({ items }: { items: CertificationRanking[] }) {
  return (
    <div className="space-y-4">
      {items.map((item, index) => (
        <CertificationCard
          key={item.id}
          rank={index + 1}
          {...item}
        />
      ))}
    </div>
  );
}
