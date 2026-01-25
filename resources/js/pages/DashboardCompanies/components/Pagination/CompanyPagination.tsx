import { router } from "@inertiajs/react";

interface Props {
  paginator: any;
}

export default function CompanyPagination({ paginator }: Props) {
  if (!paginator || paginator.last_page <= 1) return null;

  return (
    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-sm">

      {/* INFO */}
      <div className="text-slate-600 dark:text-slate-300">
        Mostrando{" "}
        <span className="font-semibold">
          {paginator.from}
        </span>{" "}
        –{" "}
        <span className="font-semibold">
          {paginator.to}
        </span>{" "}
        de{" "}
        <span className="font-semibold">
          {paginator.total}
        </span>{" "}
        empresas
      </div>

      {/* LINKS */}
      <div className="flex flex-wrap gap-1">
        {paginator.links.map((link: any, index: number) => (
          <button
            key={index}
            disabled={!link.url}
            onClick={() => link.url && router.get(link.url)}
            className={`
              min-w-[36px]
              rounded-lg
              border
              px-3
              py-1.5
              transition
              ${
                link.active
                  ? "bg-[#00B6E8] text-white border-[#00B6E8]"
                  : "bg-white hover:bg-[#E6F7FD]"
              }
              ${!link.url ? "opacity-40 cursor-not-allowed" : ""}
            `}
            dangerouslySetInnerHTML={{ __html: link.label }}
          />
        ))}
      </div>
    </div>
  );
}
