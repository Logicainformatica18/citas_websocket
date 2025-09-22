import { useState } from "react";
import axios from "axios";

interface BackupButtonProps {
  scrapingId: number;
  rowId: number;
  data: Record<string, any>;
}

export default function BackupButton({ scrapingId, rowId, data }: BackupButtonProps) {
  const [saving, setSaving] = useState(false);

  const handleSaveBackup = async () => {
    setSaving(true);
    try {
      const res = await axios.post(`/scrapings/${scrapingId}/backups`, {
        row_id: rowId,
        data: data,
      });
      alert("✅ Backup guardado correctamente");
      console.log("Backup creado:", res.data.backup);
    } catch (error) {
      console.error("❌ Error guardando backup", error);
      alert("❌ Error al guardar backup");
    } finally {
      setSaving(false);
    }
  };

  return (
    <button
      onClick={handleSaveBackup}
      disabled={saving}
      className="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition disabled:opacity-50"
    >
      {saving ? "Guardando..." : "Guardar en Backup"}
    </button>
  );
}
