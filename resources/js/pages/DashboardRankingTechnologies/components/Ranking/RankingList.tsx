import TechnologyCard from "./TechnologyCard";
import Paginator from "../Pagination/Paginator";
import { TechnologyRanking } from "../../types/ranking";

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
  items: TechnologyRanking[];
  pagination: Pagination;
  onSelectTechnology?: (item: TechnologyRanking) => void;
};

export default function RankingList({
  items,
  pagination,
  onSelectTechnology,
}: Props) {
  const perPage = pagination.per_page ?? items.length;

  return (
    <>
      {/* ================= GRID ================= */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {items.map((item, index) => {
          const isTrend = item.entity_type === "trend";
          const isIsil  = item.is_isil === 1;

          return (
            <TechnologyCard
              key={`${item.entity_type}-${item.id}`}
              rank={(pagination.current_page - 1) * perPage + index + 1}
              data={item}

              /* 👇 Solo tecnologías ISIL son clickeables */
              onClick={
                !isTrend && isIsil
                  ? () => onSelectTechnology?.(item)
                  : undefined
              }
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
