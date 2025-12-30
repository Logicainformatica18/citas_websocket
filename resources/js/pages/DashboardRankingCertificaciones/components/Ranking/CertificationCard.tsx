interface Props {
  name: string;
  vendor: string;
  level: string;
  category: string;
  total_jobs: number;
  tags?: string[];
}

export default function CertificationCard({
  name,
  vendor,
  level,
  category,
  total_jobs,
  tags = [],
}: Props) {
  return (
    <div className="border rounded-xl p-4 bg-white">
      <h3 className="font-bold">{name}</h3>
      <p className="text-sm text-gray-500">{vendor}</p>

      {/* 👇 AQUÍ ESTABA EL ERROR */}
      <div className="flex gap-2 mt-2">
        {tags.map((tag, i) => (
          <span key={i} className="text-xs bg-gray-100 px-2 py-1 rounded">
            {tag}
          </span>
        ))}
      </div>

      <p className="mt-2 font-semibold">
        {total_jobs} vacantes
      </p>
    </div>
  );
}
