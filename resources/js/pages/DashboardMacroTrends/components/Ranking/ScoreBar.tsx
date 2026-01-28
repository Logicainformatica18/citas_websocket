export default function ScoreBar({ value }: { value: number }) {
  return (
    <div className="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
      <div
        className="h-2 rounded-full bg-[#00B6E8]"
        style={{ width: `${Math.min(value, 100)}%` }}
      />
    </div>
  );
}
