import { useState, useEffect } from "react";
import axios from "axios";
import { X } from "lucide-react";

type Props = {
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
  editItem?: AITraining | null;
};

type AITraining = {
  id?: number;
  topic: string;
  prompt: string;
  interpreter: string;
  component?: string | null;
  description?: string | null;
  tags?: string[];
  is_active?: boolean;
  has_ai_response?: boolean;
  explanation_prompt?: string | null;
};

export default function AITrainingModal({ open, onClose, onSaved, editItem }: Props) {
  const [form, setForm] = useState<AITraining>({
    topic: "",
    prompt: "",
    interpreter: "",
    component: "",
    description: "",
    tags: [],
    is_active: true,
    has_ai_response: true,
    explanation_prompt: "",
  });

  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (editItem) {
      setForm({
        ...editItem,
        tags: editItem.tags ?? [],
        explanation_prompt: editItem.explanation_prompt ?? "",
      });
    } else {
      setForm({
        topic: "",
        prompt: "",
        interpreter: "",
        component: "",
        description: "",
        tags: [],
        is_active: true,
        has_ai_response: true,
        explanation_prompt: "",
      });
    }
  }, [editItem]);

  if (!open) return null;

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleCheckbox = (name: keyof AITraining) => {
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
        await axios.put(`/admin/ai-trainings/${editItem.id}`, form);
      } else {
        await axios.post("/admin/ai-trainings", form);
      }
      onSaved();
      onClose();
    } catch (err) {
      console.error("Error al guardar entrenamiento IA", err);
      alert("❌ No se pudo guardar el entrenamiento IA.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-gray-900 rounded-lg w-[650px] p-6 shadow-lg border border-gray-300 dark:border-gray-700 relative animate-fade-in">
        {/* Botón cerrar */}
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-gray-500 dark:text-gray-300 hover:text-red-500"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Título */}
        <h2 className="text-xl font-bold mb-4 text-gray-800 dark:text-gray-100">
          {editItem ? "✏️ Editar Entrenamiento IA" : "🧠 Nuevo Entrenamiento IA"}
        </h2>

        {/* Formulario */}
        <div className="space-y-3">
          {/* Tema */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Tema
            </label>
            <input
              name="topic"
              value={form.topic}
              onChange={handleChange}
              placeholder="Ejemplo: Tendencias tecnológicas"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            />
          </div>

          {/* Prompt principal */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Prompt principal
            </label>
            <textarea
              name="prompt"
              value={form.prompt}
              onChange={handleChange}
              rows={2}
              placeholder="Instrucción o pregunta que entrenará la IA..."
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            ></textarea>
          </div>

          {/* Intérprete */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Intérprete (controlador/método)
            </label>
            <input
              name="interpreter"
              value={form.interpreter}
              onChange={handleChange}
              placeholder="Ejemplo: aiTrainerController@analyzeTrends"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            />
          </div>

          {/* Componente */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Componente React asociado
            </label>
            <input
              name="component"
              value={form.component ?? ""}
              onChange={handleChange}
              placeholder="Ejemplo: TrendChart, SkillRadar"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100"
            />
          </div>

          {/* Descripción */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Descripción
            </label>
            <textarea
              name="description"
              value={form.description ?? ""}
              onChange={handleChange}
              rows={2}
              placeholder="Breve descripción del entrenamiento..."
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            ></textarea>
          </div>

          {/* Prompt de explicación */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Prompt de explicación (opcional)
            </label>
            <textarea
              name="explanation_prompt"
              value={form.explanation_prompt ?? ""}
              onChange={handleChange}
              rows={2}
              placeholder="Prompt para que la IA explique los resultados o tendencias..."
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 outline-none"
            ></textarea>
          </div>

          {/* Tags */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Tags (separados por coma)
            </label>
            <input
              type="text"
              value={form.tags?.join(", ") ?? ""}
              onChange={handleTagsChange}
              placeholder="ia, entrenamiento, tendencias"
              className="w-full px-3 py-2 mt-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100"
            />
          </div>

          {/* Checkboxes */}
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
              <span className="text-gray-800 dark:text-gray-200">Usa IA</span>
            </label>
          </div>
        </div>

        {/* Botones */}
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
