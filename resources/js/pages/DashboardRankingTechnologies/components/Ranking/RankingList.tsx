import TechnologyCard from "./TechnologyCard";
import Paginator from "../Pagination/Paginator";
import { TechnologyRanking } from "../../types/technologyRanking";

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

  onSelectItem?: (
    action: "laboral" | "trend",
    item: TechnologyRanking
  ) => void;
};

export default function RankingList({
  items,
  pagination,
  onSelectItem,
}: Props) {
  const perPage = pagination.per_page ?? items.length;

  return (
    <>
      {/* ================= GRID ================= */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {items.map((item, index) => (
          <TechnologyCard
            key={`${item.entity_type}-${item.id}`}
            rank={(pagination.current_page - 1) * perPage + index + 1}
            data={item}

            /* ✅ REENVÍA TODO (SIN BLOQUEOS) */
            onAction={(action, data) => {
              onSelectItem?.(action, data);
            }}
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
