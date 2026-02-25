import { useEffect, useState } from "react";
import { Code, Wrench, Layers, Briefcase } from "lucide-react";

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
      .then((res) => res.json())
      .then((data) => {
        setConnections(data.connections ?? {});
        setRecentJobs(data.recent_jobs ?? []);
      })
      .finally(() => setLoading(false));
  }, [course?.id]);

  return (
    <div className="space-y-8">

      {/* ================= CONEXIONES ================= */}
      <div
        className="
          rounded-2xl p-6
          border border-gray-200 dark:border-gray-800
          bg-white dark:bg-slate-900
          shadow-sm dark:shadow-lg
        "
      >
        <h3 className="text-sm font-semibold mb-5 text-gray-800 dark:text-gray-100">
          Conexiones del curso
        </h3>

        <ConnectionGroup
          icon={<Code size={15} />}
          title="Lenguajes"
          items={connections.languages}
        />

        <ConnectionGroup
          icon={<Wrench size={15} />}
          title="Tecnologías"
          items={connections.technologies}
        />

        <ConnectionGroup
          icon={<Layers size={15} />}
          title="Metodologías"
          items={connections.methodologies}
        />
      </div>

      {/* ================= OFERTAS ================= */}
      <div
        className="
          rounded-2xl p-6
          border border-gray-200 dark:border-gray-800
          bg-white dark:bg-slate-900
          shadow-sm dark:shadow-lg
        "
      >
        <div className="flex items-center gap-2 mb-5">
          <Briefcase size={16} className="text-sky-600 dark:text-sky-400" />
          <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
            Ofertas recientes
          </h3>
        </div>

        {loading && (
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Cargando ofertas...
          </p>
        )}

        {!loading && recentJobs.length === 0 && (
          <p className="text-sm text-gray-500 dark:text-gray-400">
            No hay ofertas recientes asociadas.
          </p>
        )}

      {!loading &&
  recentJobs.map((job) => (
    <JobCard key={job.id} job={job} />
  ))}
      </div>
    </div>
  );
}

/* ======================================================
   CONNECTION GROUP
====================================================== */

function ConnectionGroup({ icon, title, items }: any) {
  if (!items || items.length === 0) return null;

  const colorMap: any = {
    Lenguajes: {
      text: "text-pink-500 dark:text-pink-400",
      chipBg: "bg-pink-100 dark:bg-pink-900/30",
      chipText: "text-pink-700 dark:text-pink-300",
      chipBorder: "border-pink-300 dark:border-pink-700",
    },
    Tecnologías: {
      text: "text-emerald-500 dark:text-emerald-400",
      chipBg: "bg-emerald-100 dark:bg-emerald-900/30",
      chipText: "text-emerald-700 dark:text-emerald-300",
      chipBorder: "border-emerald-300 dark:border-emerald-700",
    },
    Metodologías: {
      text: "text-violet-500 dark:text-violet-400",
      chipBg: "bg-violet-100 dark:bg-violet-900/30",
      chipText: "text-violet-700 dark:text-violet-300",
      chipBorder: "border-violet-300 dark:border-violet-700",
    },
  };

  const style = colorMap[title] || colorMap["Lenguajes"];

  return (
    <div className="mb-6">
      <div className={`flex items-center gap-2 text-xs font-semibold mb-3 ${style.text}`}>
        {icon}
        {title}
      </div>

      <div className="flex flex-wrap gap-3">
        {items.map((item: string) => (
          <span
            key={item}
            className={`
              px-3 py-1.5 rounded-full text-[11px] font-medium border
              ${style.chipBg}
              ${style.chipText}
              ${style.chipBorder}
              hover:scale-105 transition-all
            `}
          >
            {item}
          </span>
        ))}
      </div>
    </div>
  );
}
 function JobCard({ job }: any) {

  const salary =
    job.salary_min && job.salary_max
      ? `${job.currency ?? "$"} ${Number(job.salary_min).toLocaleString()} - ${Number(job.salary_max).toLocaleString()}`
      : null;

  const jobUrl = job.application_url || job.url;

  return (
    <a
      href={jobUrl}
      target="_blank"
      rel="noopener noreferrer"
      className="
        block mb-4 rounded-xl border
        border-gray-200 dark:border-gray-800
        bg-gray-50 dark:bg-slate-800/60
        hover:bg-gray-100 dark:hover:bg-slate-800
        hover:shadow-md
        transition-all
        p-4
      "
    >
      {/* TITLE */}
      <div className="flex justify-between items-start">
        <div className="font-medium text-sm text-gray-800 dark:text-gray-100">
          {job.title}
        </div>

        <span className="text-[10px] text-gray-400">
          {new Date(job.published_at).toLocaleDateString()}
        </span>
      </div>

      {/* COMPANY */}
      <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
        {job.company} • {job.city}, {job.country}
      </div>

      {/* BADGES */}
      <div className="flex flex-wrap gap-2 mt-3 text-[11px]">
        <Badge label={job.modality} color="sky" />
   
        <Badge label={job.job_type} color="violet" />
      </div>

      {/* SALARY */}
      {salary && (
        <div className="mt-3 text-xs text-gray-600 dark:text-gray-300">
          💰 {salary}
        </div>
      )}
    </a>
  );
}
function Badge({ label, color }: any) {
  if (!label) return null;

  const colors: any = {
    sky: "bg-sky-100 text-sky-700 border-sky-300 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-700",
    emerald: "bg-emerald-100 text-emerald-700 border-emerald-300 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700",
    violet: "bg-violet-100 text-violet-700 border-violet-300 dark:bg-violet-900/30 dark:text-violet-300 dark:border-violet-700",
  };

  return (
    <span
      className={`px-2 py-1 rounded-full border text-[10px] font-medium ${colors[color]}`}
    >
      {label}
    </span>
  );
}