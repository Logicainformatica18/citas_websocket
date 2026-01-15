import CertificationCard from "./CertificationCard";
import Paginator from "../Pagination/Paginator";
import { CertificationRanking } from "../../types/ranking";

/* =========================
   TIPOS
========================= */
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
  onSelectCertification?: (item: CertificationRanking) => void;
};

export default function RankingList({
  items,
  pagination,
  onSelectCertification,
}: Props) {
  const perPage = pagination.per_page ?? items.length;
 

  return (
    <>
      {/* ================= GRID ÚNICO ================= */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {items.map((item, index) => {
          const isTrend = item.entity_type === "trend";

          return (
            <CertificationCard
              key={`${item.entity_type}-${item.id}`}
              rank={(pagination.current_page - 1) * perPage + index + 1}
              data={item}

              /* 👇 solo permite acción si es certificación */
              onClick={
                !isTrend
                  ? () => onSelectCertification?.(item)
                  : undefined
              }

              /* 👇 hint visual para el card */
              variant={isTrend ? "trend" : "certification"}
            />
          );
        })}
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
