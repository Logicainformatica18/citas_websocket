import { router, usePage } from "@inertiajs/react";

/* =====================================================
   Types
===================================================== */
type Props = {
  currentPage: number;
  lastPage: number;
};

/* =====================================================
   Component
===================================================== */
export default function Paginator({
  currentPage,
  lastPage,
}: Props) {
  const maxVisible = 5;

  // 🔑 filtros actuales desde Inertia (lenguajes)
  const { filters } = usePage().props as any;

  /* =========================================
     Construcción de páginas visibles
  ========================================= */
  const getPages = () => {
    const pages: number[] = [];

    let start = Math.max(1, currentPage - 2);
    let end = Math.min(lastPage, start + maxVisible - 1);

    if (end - start < maxVisible - 1) {
      start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
      pages.push(i);
    }

    return pages;
  };

  const pages = getPages();

  /* =========================================
     Navegación centralizada (🔥 CLAVE)
  ========================================= */
  const goToPage = (page: number) => {
    router.get(
      route("dashboard.ranking.languages"),
      {
        ...filters, // año, periodo, carrera, ranking_type, etc.
        page,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  if (lastPage <= 1) return null;

  /* =========================================
     Render
  ========================================= */
  return (
    <div className="flex items-center justify-center gap-2 mt-8">
      {/* ← ANTERIOR */}
      <button
        disabled={currentPage === 1}
        onClick={() => goToPage(currentPage - 1)}
        className="
          px-3 py-2 rounded-lg border text-sm
          disabled:opacity-40
          hover:bg-gray-50
        "
      >
        ←
      </button>

      {/* 1 … */}
      {pages[0] > 1 && (
        <>
          <PageButton
            page={1}
            active={currentPage === 1}
            onClick={goToPage}
          />
          <span className="px-1 text-gray-400">…</span>
        </>
      )}

      {/* PÁGINAS */}
      {pages.map((page) => (
        <PageButton
          key={page}
          page={page}
          active={page === currentPage}
          onClick={goToPage}
        />
      ))}

      {/* … N */}
      {pages[pages.length - 1] < lastPage && (
        <>
          <span className="px-1 text-gray-400">…</span>
          <PageButton
            page={lastPage}
            active={currentPage === lastPage}
            onClick={goToPage}
          />
        </>
      )}

      {/* SIGUIENTE → */}
      <button
        disabled={currentPage === lastPage}
        onClick={() => goToPage(currentPage + 1)}
        className="
          px-3 py-2 rounded-lg border text-sm
          disabled:opacity-40
          hover:bg-gray-50
        "
      >
        →
      </button>
    </div>
  );
}

/* =====================================================
   PageButton
===================================================== */
function PageButton({
  page,
  active,
  onClick,
}: {
  page: number;
  active: boolean;
  onClick: (page: number) => void;
}) {
  return (
    <button
      onClick={() => onClick(page)}
      className={`
        min-w-[36px]
        px-3 py-2
        rounded-lg
        text-sm
        transition
        ${
          active
            ? "bg-[#1CBCE8] text-white font-semibold shadow"
            : "border hover:bg-gray-50 text-gray-700"
        }
      `}
    >
      {page}
    </button>
  );
}
