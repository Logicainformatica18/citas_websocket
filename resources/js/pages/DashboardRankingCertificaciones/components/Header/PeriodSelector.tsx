/* =========================================================
   Types
========================================================= */

export type Period = "s1" | "s2";

export const periodVacancyCounts: Record<Period, number> = {
  s1: 2100,
  s2: 1800,
};

export function getPeriodDisplayText(period: Period) {
  return period === "s1"
    ? "Semestre 1 – Enero – Junio 2025"
    : "Semestre 2 – Julio – Diciembre 2025";
}

/* =========================================================
   Component
========================================================= */

export function PeriodSelector({
  value,
  onChange,
}: {
  value: Period;
  onChange: (p: Period) => void;
}) {
  return (
    <button
      className="
        flex items-center gap-2
        rounded-xl
        bg-white
        border border-border
        px-4 py-2
        shadow-sm
        hover:shadow
      "
      onClick={() => onChange(value === "s1" ? "s2" : "s1")}
    >
      <span className="font-semibold text-foreground">
        {value === "s1" ? "Semestre 1" : "Semestre 2"}
      </span>
      <span className="text-xs text-muted-foreground">
        {value === "s1"
          ? "Enero – Junio"
          : "Julio – Diciembre"}
      </span>
    </button>
  );
}
