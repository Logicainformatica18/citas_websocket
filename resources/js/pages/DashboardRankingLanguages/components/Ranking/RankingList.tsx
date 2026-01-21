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

  /**
   * Handler único.
   * La Page decide:
   * - laboral → jobs
   * - trend   → reportes / detalle tendencia
   */
  onSelectItem?: (
    action: "laboral" | "trend",
    item: LanguageRanking
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
          <LanguageCard
            key={`language-${item.id}`}
            rank={(pagination.current_page - 1) * perPage + index + 1}
            data={item}

            /* ✅ SOLO REENVÍA EVENTOS */
            onAction={(action, data) => {
              // 🔒 Blindaje mínimo
              if (action === "trend" && Number(data.trend_reports ?? 0) === 0) {
                return;
              }

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
