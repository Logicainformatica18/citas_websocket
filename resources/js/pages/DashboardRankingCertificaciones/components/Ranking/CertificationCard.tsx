import ScoreBadge from "../Shared/ScoreBadge";

interface Props {
  rank: number;
  name: string;
  provider: string;
  score: number;
  area: string;
  roles: string[];
  badges: string[];
}

export default function CertificationCard({
  rank,
  name,
  provider,
  score,
  area,
  roles,
  badges,
}: Props) {
  return (
    <div className="border rounded-xl p-4 flex justify-between">
      <div className="space-y-2">
        <span className="font-bold">#{rank}</span>
        <h3 className="font-semibold">{name}</h3>
        <p className="text-sm text-gray-500">{provider}</p>
        <p className="text-sm">{area}</p>

        <div className="flex gap-2 flex-wrap">
          {roles.map((r) => (
            <span key={r} className="text-xs border px-2 py-1 rounded">
              {r}
            </span>
          ))}
        </div>

        <div className="flex gap-2">
          {badges.map((b) => (
            <span key={b} className="text-xs bg-[#ECFAFD] px-2 py-1 rounded">
              {b}
            </span>
          ))}
        </div>
      </div>

      <ScoreBadge score={score} />
    </div>
  );
}
