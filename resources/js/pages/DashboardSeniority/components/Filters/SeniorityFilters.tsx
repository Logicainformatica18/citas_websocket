import { router, usePage } from "@inertiajs/react";
import { Badge } from "@/components/ui/badge";

type Career = {
  id: number;
  name: string;
  slug: string;
};

interface Props {
  careers: Career[];
}

export function SeniorityFilters({ careers }: Props) {
  const { filters } = usePage().props as any;

  const toggleCareer = (slug: string) => {
    const current = filters?.career ?? [];

    const next = current.includes(slug)
      ? current.filter((c: string) => c !== slug)
      : [...current, slug];

    router.get(
      "/dashboard/indicators/seniority",
      {
        ...filters,
        career: next.length ? next : undefined,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  const clear = () => {
    router.get(
      "/dashboard/indicators/seniority",
      {
        ...filters,
        career: undefined,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  return (
    <div className="px-6">
      <div className="border rounded-xl p-4 bg-white dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">
        {/* Header */}
        <div className="flex items-center justify-between mb-3">
          <p className="text-sm font-semibold text-slate-800 dark:text-slate-200">
            Filtrar por carrera
          </p>

          {filters?.career?.length > 0 && (
            <button
              onClick={clear}
              className="text-xs font-medium text-[#00B6E8] hover:underline"
            >
              Limpiar
            </button>
          )}
        </div>

        {/* Badges */}
        <div className="flex flex-wrap gap-2">
          {careers.map((career) => {
            const active = filters?.career?.includes(career.slug);

            return (
              <Badge
                key={career.id}
                onClick={() => toggleCareer(career.slug)}
                className={`
                  cursor-pointer
                  transition-all
                  duration-200
                  select-none
                  ${active
                    ? `
                      bg-[#00B6E8]
                      text-white
                      opacity-100
                      shadow-sm
                    `
                    : `
                      bg-[#ECFAFD]
                      text-[#4B7C91]
                      opacity-60
                      hover:opacity-85
                      hover:bg-[#DFF3FB]
                    `
                  }
                `}
              >
                {career.name}
              </Badge>
            );
          })}
        </div>
      </div>
    </div>
  );
}
