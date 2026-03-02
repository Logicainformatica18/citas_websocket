import { Search, X } from "lucide-react";
import { router, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";

export default function MacroTrendSearch() {
  const { filters, meta } = usePage().props as any;

  const [value, setValue] = useState(filters?.search ?? "");

  /* =========================
     DEBOUNCE
  ========================= */
  useEffect(() => {
    const timeout = setTimeout(() => {
      router.get(
        "/dashboard/indicators/macro-trends",
        {
          search: value || undefined,
          year: meta.year,
          period: meta.period,
        },
        {
          preserveState: true,
          replace: true,
        }
      );
    }, 400);

    return () => clearTimeout(timeout);
  }, [value]);

  const clearSearch = () => {
    setValue("");
  };

  return (
    <div className="w-full max-w-xl relative">
      {/* INPUT CONTAINER */}
      <div
        className="
          group flex items-center gap-3
          rounded-2xl border-2 border-[#00B6E8]/40
          bg-white px-4 py-3 shadow-sm
          transition-all duration-300
          focus-within:border-[#00B6E8]
          focus-within:shadow-md
          dark:bg-[#0F172A]
        "
      >
        <Search className="h-5 w-5 text-[#00B6E8]" />

        <input
          type="text"
          placeholder="Buscar tendencia, fuente, descripción..."
          value={value}
          onChange={(e) => setValue(e.target.value)}
          className="
            w-full bg-transparent text-sm
            text-[#0A2540] placeholder-slate-400
            focus:outline-none
            dark:text-white
          "
        />

        {value && (
          <button
            onClick={clearSearch}
            className="text-slate-400 hover:text-red-500 transition"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {/* INDICADOR ACTIVO */}
      {value && (
        <p className="mt-2 text-xs text-[#00B6E8] font-semibold">
          Buscando: "{value}"
        </p>
      )}
    </div>
  );
}
