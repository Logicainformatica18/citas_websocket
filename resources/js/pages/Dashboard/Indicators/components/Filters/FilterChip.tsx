export default function FilterChip({
  label,
  onRemove,
}: {
  label: string;
  onRemove: () => void;
}) {
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-[#E6F7FD] px-3 py-1 text-xs font-semibold text-[#005F7A]">
      {label}
      <button
        onClick={onRemove}
        className="ml-1 rounded-full px-1 hover:bg-[#D0EEF8]"
      >
        ✕
      </button>
    </span>
  );
}
