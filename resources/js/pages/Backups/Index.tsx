import AppLayout from '@/layouts/app-layout';
import { useState } from "react";
import { usePage, router } from "@inertiajs/react";
import axios from "axios";

type Backup = {
  id: number;
  row_id: number;
  data: Record<string, any>;
  reviewed: boolean;
};

type PageProps = {
  scraping: {
    id: number;
    name: string;
  };
  backups: {
    data: Backup[];
    current_page: number;
    last_page: number;
  };
};

export default function BackupIndex() {
  const { backups, scraping } = usePage<PageProps>().props;
  const [saving, setSaving] = useState<number | null>(null);

  const toggleReviewed = async (backupId: number) => {
    setSaving(backupId);
    try {
      await axios.patch(`/scrapings/${scraping.id}/backups/${backupId}/toggle`);
      router.reload({ only: ["backups"] });
    } catch (err) {
      console.error("❌ Error cambiando estado", err);
      alert("❌ No se pudo actualizar el estado");
    } finally {
      setSaving(null);
    }
  };

  const exportExcel = () => {
    window.location.href = `/scrapings/${scraping.id}/backups/export`;
  };

  const dynamicKeys = Array.from(
    new Set(backups.data.flatMap((b) => Object.keys(b.data || {})))
  );

  return (
    <AppLayout
      breadcrumbs={[
        { title: "Scrapings", href: "/scrapings" },
        { title: scraping.name, href: `/scrapings/${scraping.id}/backups` },
        { title: "Backups" },
      ]}
    >
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">📦 Backups de {scraping.name}</h1>

        <div className="mb-4 flex gap-2">
          <button
            onClick={exportExcel}
            className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition"
          >
            Exportar Excel
          </button>
        </div>

        {backups.data.length === 0 ? (
          <p className="text-gray-500">⚠️ No hay backups todavía</p>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full border border-gray-300 rounded bg-white dark:bg-black">
                <thead>
                  <tr>
                    <th className="px-4 py-2 border bg-gray-100 dark:bg-gray-800 text-black dark:text-white">
                      Row ID
                    </th>
                    {dynamicKeys.map((key) => (
                      <th
                        key={key}
                        className="px-4 py-2 border bg-gray-100 dark:bg-gray-800 text-black dark:text-white"
                      >
                        {key}
                      </th>
                    ))}
                    <th className="px-4 py-2 border bg-gray-100 dark:bg-gray-800 text-black dark:text-white">
                      Revisado
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {backups.data.map((b) => (
                    <tr
                      key={b.id}
                      className="border-t hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                      <td className="px-4 py-2 border">{b.row_id}</td>
                      {dynamicKeys.map((key) => (
                        <td key={key} className="px-4 py-2 border">
                          {b.data?.[key] ?? "-"}
                        </td>
                      ))}
                      <td className="px-4 py-2 border text-center">
                        <button
                          onClick={() => toggleReviewed(b.id)}
                          disabled={saving === b.id}
                          className={`px-3 py-1 rounded text-white ${
                            b.reviewed
                              ? "bg-green-600 hover:bg-green-700"
                              : "bg-gray-400 hover:bg-gray-500"
                          } disabled:opacity-50`}
                        >
                          {saving === b.id
                            ? "..."
                            : b.reviewed
                            ? "✅ Revisado"
                            : "Marcar"}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* 📌 Paginación */}
            <div className="flex justify-center mt-6 space-x-2">
              {[...Array(backups.last_page)].map((_, index) => {
                const page = index + 1;
                return (
                  <button
                    key={page}
                    onClick={() =>
                      router.visit(`/scrapings/${scraping.id}/backups?page=${page}`)
                    }
                    className={`px-3 py-1 rounded text-sm font-medium transition ${
                      backups.current_page === page
                        ? "bg-blue-600 text-white"
                        : "bg-gray-200 text-gray-800 hover:bg-gray-300"
                    }`}
                    disabled={backups.current_page === page}
                  >
                    {page}
                  </button>
                );
              })}
            </div>
          </>
        )}
      </div>
    </AppLayout>
  );
}
