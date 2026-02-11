import { useEffect, useState } from "react";
import axios from "axios";
import { X, FileText, Briefcase } from "lucide-react";

type Props = {
  macroId: number | null;
  open: boolean;
  onClose: () => void;
};

export default function MacroTrendDetailModal({
  macroId,
  open,
  onClose,
}: Props) {
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<any>(null);
  const [activeTab, setActiveTab] = useState<"reportes" | "jobs">("reportes");

  useEffect(() => {
    if (!open || !macroId) return;

    setLoading(true);

    axios
      .get(`/indicators/macro-trends/${macroId}/detail`)
      .then((res) => setData(res.data))
      .finally(() => setLoading(false));
  }, [macroId, open]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center">
      <div
        className="absolute inset-0 bg-black/50"
        onClick={onClose}
      />

      <div className="relative bg-white w-[950px] max-h-[90vh] overflow-hidden rounded-2xl shadow-2xl">

        <div className="flex justify-between items-start px-6 py-5 border-b">
          <div>
            <h2 className="text-xl font-bold text-gray-800">
              {data?.macro?.name}
            </h2>
            {data?.macro?.description && (
              <p className="text-sm text-gray-600 mt-2 max-w-2xl">
                {data.macro.description}
              </p>
            )}
          </div>

          <button
            onClick={onClose}
            className="text-gray-500 hover:text-gray-800"
          >
            <X />
          </button>
        </div>

        <div className="px-6 pt-4 border-b flex gap-6">
          <button
            onClick={() => setActiveTab("reportes")}
            className={`pb-3 text-sm font-medium ${
              activeTab === "reportes"
                ? "border-b-2 border-[#1CBCE8] text-[#1CBCE8]"
                : "text-gray-500"
            }`}
          >
            <FileText className="inline w-4 h-4 mr-1" />
            Reportes
          </button>

          <button
            onClick={() => setActiveTab("jobs")}
            className={`pb-3 text-sm font-medium ${
              activeTab === "jobs"
                ? "border-b-2 border-[#1CBCE8] text-[#1CBCE8]"
                : "text-gray-500"
            }`}
          >
            <Briefcase className="inline w-4 h-4 mr-1" />
            Ofertas
          </button>
        </div>

        <div className="p-6 overflow-y-auto max-h-[65vh]">

          {loading && (
            <div className="text-center text-gray-500">
              Cargando información...
            </div>
          )}

          {!loading && activeTab === "reportes" && (
            <div className="space-y-4">
              {data?.reportes?.length === 0 && (
                <p className="text-gray-500">
                  No hay reportes asociados.
                </p>
              )}

              {data?.reportes?.map((r: any) => (
                <div
                  key={r.id}
                  className="border rounded-xl p-4 hover:bg-gray-50 transition"
                >
                  <p className="font-medium text-gray-800">
                    {r.trend_name}
                  </p>

                  {r.source_url && (
                    <a
                      href={r.source_url}
                      target="_blank"
                      className="text-[#1CBCE8] text-sm mt-1 inline-block"
                    >
                      Ver fuente
                    </a>
                  )}
                </div>
              ))}
            </div>
          )}

          {!loading && activeTab === "jobs" && (
            <div className="space-y-4">
              {data?.jobs?.length === 0 && (
                <p className="text-gray-500">
                  No hay ofertas asociadas.
                </p>
              )}

              {data?.jobs?.map((j: any) => (
                <div
                  key={j.id}
                  className="border rounded-xl p-4 hover:bg-gray-50 transition"
                >
                  <p className="font-medium text-gray-800">
                    {j.title}
                  </p>

                  <p className="text-sm text-gray-600">
                    {j.company} – {j.region}
                  </p>

                  <p className="text-xs text-gray-400 mt-1">
                    {j.published_at}
                  </p>
                </div>
              ))}
            </div>
          )}

        </div>
      </div>
    </div>
  );
}
