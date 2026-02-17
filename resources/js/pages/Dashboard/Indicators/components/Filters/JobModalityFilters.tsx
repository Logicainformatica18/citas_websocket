import { router, usePage } from "@inertiajs/react";

export default function JobModalityFilters() {
  const pageProps = usePage().props as any;
const filters = pageProps?.filters ?? {};

  const updateFilter = (key: string, value: string | null) => {
    router.get(
      "/dashboard/indicadores/modalidad-laboral",
      {
        ...filters,
        [key]: value || undefined,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  return (
    <div className="
      rounded-2xl
      border
      bg-white
      p-4
      shadow-sm
      dark:bg-[#0F2A3A]
      dark:border-slate-700
    ">
      <div className="grid gap-4 sm:grid-cols-3">

        {/* ===== PAÍS ===== */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            País
          </label>
          <input
            type="text"
            defaultValue={filters?.country ?? ""}
            placeholder="Ej. Perú, México"
            onBlur={(e) =>
              updateFilter("country", e.target.value || null)
            }
            className="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900"
          />
        </div>

        {/* ===== FUENTE ===== */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Portal de empleo
          </label>
          <select
            defaultValue={filters?.source ?? ""}
            onChange={(e) =>
              updateFilter("source", e.target.value || null)
            }
            className="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900"
          >
            <option value="">Todos</option>
            <option value="Adzuna">Adzuna</option>
            <option value="GetOnBoard">GetOnBoard</option>
            <option value="Computrabajo">Computrabajo</option>
          </select>
        </div>

        {/* ===== FECHA ===== */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Publicado desde
          </label>
          <input
            type="date"
            defaultValue={filters?.date_from ?? ""}
            onChange={(e) =>
              updateFilter("date_from", e.target.value || null)
            }
            className="rounded-lg border px-3 py-2 text-sm dark:bg-slate-900"
          />
        </div>

      </div>
    </div>
  );
}
