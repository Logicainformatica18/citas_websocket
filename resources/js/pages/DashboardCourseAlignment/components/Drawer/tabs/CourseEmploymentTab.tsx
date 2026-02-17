import { useEffect, useState } from "react";
import { Code, Wrench, Layers } from "lucide-react";

export default function CourseEmploymentTab({ course }: any) {
  const [connections, setConnections] = useState({
    languages: [],
    technologies: [],
    methodologies: [],
  });

  const [recentJobs, setRecentJobs] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!course?.id) return;

    setLoading(true);

    fetch(`/indicators/course/${course.id}/recent-jobs`)
      .then(res => res.json())
      .then(data => {
        setConnections(data.connections ?? {});
        setRecentJobs(data.recent_jobs ?? []);
      })
      .finally(() => setLoading(false));

  }, [course.id]);

  return (
    <div className="space-y-6">
      {/* Conexiones */}
      <div>
        <h3 className="font-semibold mb-3">
          Conexiones del curso
        </h3>

        <ConnectionGroup
          icon={<Code size={14} />}
          title="Lenguajes"
          items={connections.languages}
        />

        <ConnectionGroup
          icon={<Wrench size={14} />}
          title="Tecnologías"
          items={connections.technologies}
        />

        <ConnectionGroup
          icon={<Layers size={14} />}
          title="Metodologías"
          items={connections.methodologies}
        />
      </div>

      {/* Ofertas */}
      <div>
        <h3 className="font-semibold mb-3">
          Ofertas recientes
        </h3>

        {loading && <p>Cargando...</p>}

        {!loading && recentJobs.map(job => (
          <div key={job.id} className="border rounded-xl p-3 mb-3">
            <div className="font-medium text-sm">
              {job.title}
            </div>
            <div className="text-xs text-muted-foreground">
              {job.company} – {job.city}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function ConnectionGroup({ icon, title, items }: any) {
  if (!items || items.length === 0) return null;

  return (
    <div className="mb-4">
      <div className="flex items-center gap-2 text-xs font-semibold mb-2">
        {icon}
        {title}
      </div>
      <div className="flex flex-wrap gap-2">
        {items.map((item: string) => (
          <span
            key={item}
            className="inline-flex items-center rounded-full bg-[#E6F7FD] text-[#1CBCE8] px-3 py-1 text-[11px]"
          >
            {item}
          </span>
        ))}
      </div>
    </div>
  );
}
