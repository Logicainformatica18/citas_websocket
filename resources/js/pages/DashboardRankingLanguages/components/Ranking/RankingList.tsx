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
   * - laboral → jobs (language o trend)
   * - trend   → detalle de tendencia
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
            key={`${item.entity_type}-${item.id}`}
            rank={(pagination.current_page - 1) * perPage + index + 1}
            data={item}

            /* =========================
               REENVÍO DE ACCIONES
            ========================= */
            onAction={(action, data) => {

              /* ---------- DETALLE TENDENCIA ---------- */
              if (action === "trend") {
                // 🔒 Solo si es tendencia real
                if (!data.is_real_trend) return;

                // 🔒 Sin reportes no tiene sentido
                if (Number(data.trend_reports ?? 0) === 0) return;

                onSelectItem?.("trend", data);
                return;
              }

              /* ---------- LABORAL ---------- */
              if (action === "laboral") {

                // 🔵 Lenguaje ISIL → jobs por language_job
                if (data.entity_type === "language") {
                  if (Number(data.total_jobs ?? 0) === 0) return;
                  onSelectItem?.("laboral", data);
                  return;
                }

                // 🔴 Lenguaje en tendencia → jobs por technology_trend_job
                if (data.entity_type === "trend") {
                  if (!data.is_real_trend) return;
                  if (Number(data.total_jobs ?? 0) === 0) return;

                  onSelectItem?.("laboral", data);
                  return;
                }
              }
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
