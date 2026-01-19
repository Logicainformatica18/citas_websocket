import LanguageCard from "./LanguageCard";
import Paginator from "../Pagination/Paginator";
import { LanguageRanking } from "../../types/ranking";

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
  items: LanguageRanking[];
  pagination: Pagination;
  onSelectLanguage?: (item: LanguageRanking) => void;
};

export default function RankingList({
  items,
  pagination,
  onSelectLanguage,
}: Props) {
  const perPage = pagination.per_page ?? items.length;

  return (
    <>
      {/* ================= GRID ================= */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {items.map((item, index) => (
          <LanguageCard
            key={item.id}
            rank={(pagination.current_page - 1) * perPage + index + 1}
            data={item}
            onClick={() => onSelectLanguage?.(item)}
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
