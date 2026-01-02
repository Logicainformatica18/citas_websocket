type Props = {
  page: number;
  totalPages: number;
  onChange: (page: number) => void;
};

export default function Pagination({ page, totalPages, onChange }: Props) {
  if (totalPages <= 1) return null;

  return (
    <div className="flex justify-center items-center gap-2 pt-6">
      <button
        disabled={page === 1}
        onClick={() => onChange(page - 1)}
        className="px-3 py-2 rounded border disabled:opacity-40"
      >
        ←
      </button>

      {Array.from({ length: totalPages }).map((_, i) => (
        <button
          key={i}
          onClick={() => onChange(i + 1)}
          className={`
            px-3 py-2 rounded border
            ${
              page === i + 1
                ? "bg-[#1CBCE8] text-white"
                : "bg-white"
            }
          `}
        >
          {i + 1}
        </button>
      ))}

      <button
        disabled={page === totalPages}
        onClick={() => onChange(page + 1)}
        className="px-3 py-2 rounded border disabled:opacity-40"
      >
        →
      </button>
    </div>
  );
}
