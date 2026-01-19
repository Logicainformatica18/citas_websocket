import { useEffect, useState } from "react";
import axios from "axios";

type Job = {
  id: number;
  title: string;
  company: string;
  location: string;
  published_at: string;
  url: string;
};

export default function TrendJobsTab({ trendId }: { trendId: number }) {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!trendId) return;

    setLoading(true);

    axios
      .get(`/trends/${trendId}/jobs`)
      .then((res) => {
        setJobs(res.data?.data ?? []);
      })
      .finally(() => setLoading(false));
  }, [trendId]);

  if (loading) {
    return <p className="text-sm text-slate-500">Cargando ofertas…</p>;
  }

  if (!jobs.length) {
    return (
      <p className="text-sm text-slate-500">
        No se encontraron ofertas relacionadas.
      </p>
    );
  }

  return (
    <div className="space-y-4">
      {jobs.map((job) => (
        <div
          key={job.id}
          className="rounded-xl border p-4 bg-slate-50 dark:bg-[#14384F]"
        >
          <h4 className="font-semibold">{job.title}</h4>
          <p className="text-sm text-slate-500">
            {job.company} · {job.location}
          </p>

          <a
            href={job.url}
            target="_blank"
            className="text-xs text-sky-600 underline mt-1 inline-block"
          >
            Ver oferta
          </a>
        </div>
      ))}
    </div>
  );
}
