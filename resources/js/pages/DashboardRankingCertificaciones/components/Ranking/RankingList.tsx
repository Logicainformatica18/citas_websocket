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

  /**
   * Handler único.
   * La Page decide qué hacer con:
   * - certificaciones
   * - tendencias
   */
  onSelectItem?: (
    action: "laboral" | "trend",
    item: CertificationRanking
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
        {items.map((item, index) => {
          const isTrend = item.entity_type === "trend";

          return (
            <CertificationCard
              key={`${item.entity_type}-${item.id}`}
              rank={(pagination.current_page - 1) * perPage + index + 1}
              data={item}
              variant={isTrend ? "trend" : "certification"}

              /* ✅ SOLO REENVÍA EVENTOS */
              onAction={(action, data) => {
                // 🔒 Bloqueo mínimo y lógico
               

                // 🔥 TREND SIEMPRE SE REENVÍA
                onSelectItem?.(action, data);
              }}
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
