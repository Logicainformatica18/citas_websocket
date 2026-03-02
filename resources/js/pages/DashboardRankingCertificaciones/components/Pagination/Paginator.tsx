import { router, usePage } from "@inertiajs/react";

type Props = {
  currentPage: number;
  lastPage: number;
  prevUrl?: string | null;
  nextUrl?: string | null;
};

export default function Paginator({
  currentPage,
  lastPage,
  prevUrl,
  nextUrl,
}: Props) {
  const maxVisible = 5;

  // 🔑 filtros actuales desde Inertia
  const pageProps = usePage().props as any;
const filters = pageProps?.filters ?? {};

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

  const goToPage = (page: number) => {
    router.get(
      "/dashboard/ranking-certificaciones",
      {
        ...filters, // 🔥 CLAVE: preserva áreas, carreras, año, Periodo, etc.
        page,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  return (
    <div className="flex items-center justify-center gap-2 mt-8">
      {/* ← ANTERIOR */}
      <button
        disabled={!prevUrl}
        onClick={() => prevUrl && router.get(prevUrl, {}, { preserveState: true })}
        className="px-3 py-2 rounded-lg border text-sm disabled:opacity-40 hover:bg-gray-50"
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
        disabled={!nextUrl}
        onClick={() => nextUrl && router.get(nextUrl, {}, { preserveState: true })}
        className="px-3 py-2 rounded-lg border text-sm disabled:opacity-40 hover:bg-gray-50"
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
