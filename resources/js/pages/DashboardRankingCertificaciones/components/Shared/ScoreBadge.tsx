export default function ScoreBadge({ score }: { score: number }) {
  return (
    <span className="text-2xl font-bold text-[#1CBCE8]">
      {score.toFixed(1)}
    </span>
  );
}
