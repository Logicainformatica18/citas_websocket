import { router } from "@inertiajs/react";

interface Props {
  paginator: any;
}

export default function CompanyPagination({ paginator }: Props) {
  if (!paginator || paginator.last_page <= 1) return null;

  return (
    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-sm">

      {/* ================= INFO ================= */}
      <div className="text-slate-600 dark:text-slate-300">
        Mostrando{" "}
        <span className="font-semibold text-slate-800 dark:text-slate-100">
          {paginator.from}
        </span>{" "}
        –{" "}
        <span className="font-semibold text-slate-800 dark:text-slate-100">
          {paginator.to}
        </span>{" "}
        de{" "}
        <span className="font-semibold text-slate-800 dark:text-slate-100">
          {paginator.total}
        </span>{" "}
        empresas
      </div>

      {/* ================= LINKS ================= */}
      <div className="flex flex-wrap gap-2">

        {paginator.links.map((link: any, index: number) => {
          const isActive = link.active;
          const isDisabled = !link.url;

          return (
            <button
              key={index}
              disabled={isDisabled}
              onClick={() => link.url && router.get(link.url)}
              className={`
                min-w-[38px]
                rounded-lg
                border
                px-3
                py-1.5
                transition-all
                duration-200

                ${
                  isActive
                    ? `
                      bg-sky-600
                      text-white
                      border-sky-600
                      shadow-sm
                    `
                    : `
                      bg-white
                      dark:bg-slate-800
                      text-slate-700
                      dark:text-slate-300
                      border-slate-300
                      dark:border-slate-700
                      hover:bg-slate-100
                      dark:hover:bg-slate-700
                    `
                }

                ${
                  isDisabled
                    ? "opacity-40 cursor-not-allowed"
                    : "cursor-pointer"
                }
              `}
              dangerouslySetInnerHTML={{ __html: link.label }}
            />
          );
        })}

      </div>
    </div>
  );
}