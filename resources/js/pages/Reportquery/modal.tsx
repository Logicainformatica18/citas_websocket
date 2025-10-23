import { useState, useEffect } from "react";
import axios from "axios";
import { X } from "lucide-react";

type Props = {
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
  editItem?: ReportQuery | null;
};

type ReportQuery = {
  id?: number;
  category: string;
  question: string;
  interpreter: string;
  component?: string | null;
  description?: string | null;
  tags?: string[];
  is_active?: boolean;
  has_ai_response?: boolean;
};

export default function ReportQueryModal({ open, onClose, onSaved, editItem }: Props) {
  const [form, setForm] = useState<ReportQuery>({
    category: "",
    question: "",
    interpreter: "",
    component: "",
    description: "",
    tags: [],
    is_active: true,
    has_ai_response: true,
  });

  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (editItem) setForm(editItem);
  }, [editItem]);

  if (!open) return null;

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleCheckbox = (name: keyof ReportQuery) => {
    setForm((prev) => ({ ...prev, [name]: !prev[name] }));
  };

  const handleTagsChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const tags = e.target.value
      .split(",")
      .map((t) => t.trim())
      .filter((t) => t.length > 0);
    setForm((prev) => ({ ...prev, tags }));
  };

  const handleSubmit = async () => {
    try {
      setSaving(true);
      if (editItem?.id) {
        await axios.put(`/admin/report-queries/${editItem.id}`, form);
      } else {
        await axios.post("/admin/report-queries", form);
      }
      onSaved();
      onClose();
    } catch (err) {
      console.error("Error al guardar", err);
      alert("❌ No se pudo guardar el reporte.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-gray-900 rounded-lg w-[650px] p-6 shadow-lg border border-gray-300 dark:border-gray-700 relative">
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-gray-500 dark:text-gray-300 hover:text-red-500"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-xl font-bold mb-4 text-gray-800 dark:text-gray-100">
          {editItem ? "✏️ Editar Reporte" : "🆕 Nuevo Reporte"}
        </h2>

        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Categoría
            </label>
            <input
              name="category"
              value={form.category}
              onChange={handleChange}
              placeholder="Ejemplo: Métricas y Monitoreo"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Pregunta
            </label>
            <textarea
              name="question"
              value={form.question}
              onChange={handleChange}
              rows={2}
              placeholder="¿Qué tecnologías han aumentado más su demanda?"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            ></textarea>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Intérprete (comando / método a ejecutar)
            </label>
            <input
              name="interpreter"
              value={form.interpreter}
              onChange={handleChange}
              placeholder="metricsController@getTopTechnologies"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Componente React
            </label>
            <input
              name="component"
              value={form.component ?? ""}
              onChange={handleChange}
              placeholder="TopTechnologiesChart"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Descripción / Prompt IA
            </label>
            <textarea
              name="description"
              value={form.description ?? ""}
              onChange={handleChange}
              rows={3}
              placeholder="Ejemplo: Explica las tendencias tecnológicas del último trimestre con base en los datos de demanda laboral."
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            ></textarea>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Tags (separados por coma)
            </label>
            <input
              type="text"
              value={form.tags?.join(", ") ?? ""}
              onChange={handleTagsChange}
              placeholder="ia, tendencias, empleabilidad"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100"
            />
          </div>

          <div className="flex items-center gap-6 mt-3">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={() => handleCheckbox("is_active")}
                className="accent-blue-600 w-4 h-4"
              />
              <span className="text-gray-800 dark:text-gray-200">Activo</span>
            </label>

            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={form.has_ai_response}
                onChange={() => handleCheckbox("has_ai_response")}
                className="accent-indigo-600 w-4 h-4"
              />
              <span className="text-gray-800 dark:text-gray-200">Respuesta IA</span>
            </label>
          </div>
        </div>

        <div className="flex justify-end gap-2 mt-6">
          <button
            onClick={onClose}
            className="px-4 py-2 bg-gray-400 hover:bg-gray-500 text-white rounded transition"
          >
            Cancelar
          </button>
          <button
            onClick={handleSubmit}
            disabled={saving}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition disabled:opacity-50"
          >
            {saving ? "Guardando..." : "Guardar"}
          </button>
        </div>
      </div>
    </div>
  );
}
