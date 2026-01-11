import CertificationCard from "./CertificationCard";
import Paginator from "../Pagination/Paginator";
import { CertificationRanking } from "../../types/ranking";

type Pagination = {
  current_page: number;
  last_page: number;
  per_page?: number;
  prev_page_url?: string | null;
  next_page_url?: string | null;
};

type Props = {
  items: CertificationRanking[];
  pagination: Pagination;
  onSelectCertification?: (cert: CertificationRanking) => void;
};

export default function RankingList({
  items,
  pagination,
  onSelectCertification,
}: Props) {
  const perPage = pagination.per_page ?? items.length;

  return (
    <>
      {/* ================= GRID ================= */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {items.map((item, index) => (
          <CertificationCard
            key={item.id}
            rank={(pagination.current_page - 1) * perPage + index + 1}
            data={item}
            onClick={() => onSelectCertification?.(item)}
          />
        ))}
      </div>

      {/* ================= PAGINACIÓN ================= */}
      <Paginator
        currentPage={pagination.current_page}
        lastPage={pagination.last_page}
        prevUrl={pagination.prev_page_url}
        nextUrl={pagination.next_page_url}
      />
    </>
  );
}
