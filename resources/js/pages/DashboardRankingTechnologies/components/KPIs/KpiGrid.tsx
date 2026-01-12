import {
  Cpu,
  Briefcase,
  TrendingUp,
  BarChart3,
} from "lucide-react";

type KpiItem = {
  label: string;
  value: number | string;
  description?: string;
  icon?: "jobs" | "technologies" | "trend" | "score";
};

type Props = {
  items: KpiItem[];
};

/* =========================================
   Icon mapping
========================================= */
const iconMap = {
  jobs: Briefcase,
  technologies: Cpu,
  trend: TrendingUp,
  score: BarChart3,
};

export default function KpiGrid({ items }: Props) {
  if (!items || items.length === 0) {
    return null;
  }

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      {items.map((item, index) => {
        const Icon =
          item.icon && iconMap[item.icon]
            ? iconMap[item.icon]
            : BarChart3;

        return (
          <div
            key={index}
            className="
              rounded-2xl
              border
              bg-white
              dark:bg-[#0F2A3A]
              dark:border-[#1E3A4A]
              px-5
              py-4
              flex
              items-center
              gap-4
            "
          >
            {/* ICON */}
            <div
              className="
                flex
                h-10
                w-10
                items-center
                justify-center
                rounded-xl
                bg-[#ECFAFD]
                text-[#1CBCE8]
                dark:bg-[#123A52]
              "
            >
              <Icon className="h-5 w-5" />
            </div>

            {/* TEXT */}
            <div className="flex-1">
              <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                {item.value}
              </p>
              <p className="text-xs uppercase tracking-wider text-gray-500">
                {item.label}
              </p>

              {item.description && (
                <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">
                  {item.description}
                </p>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
