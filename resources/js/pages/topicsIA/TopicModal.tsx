import { useEffect, useRef, useState } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { X } from "lucide-react";

/* ==============================================
   Tipos
============================================== */
type TopicIntent =
  | "certification"
  | "technology_trend"
  | "skill"
  | "workforce"
  | "mixed";

type Topic = {
  id?: number;
  topic_name: string;
  search_query: string;
  intent: TopicIntent;
  category?: string | null;
  subcategory?: string | null;
  importance_weight: number;
  min_required_results: number;
};

type Props = {
  open: boolean;
  onClose: () => void;
  onCreated: () => void;
  editing?: Topic | null;
};
const CATEGORY_OPTIONS = [
  "AI & Machine Learning",
  "Cloud & DevOps",
  "Data & Analytics",
  "Cybersecurity",
  "Software Engineering",
  "Business & Management",
];
const CATEGORY_BY_INTENT: Record<TopicIntent, string[]> = {
  certification: [
    "Cloud Certifications",
    "AI & Machine Learning Certifications",
    "Cybersecurity Certifications",
    "Data & Analytics Certifications",
    "DevOps Certifications",
    "Project Management Certifications",
  ],

  technology_trend: [
    "Cloud Platforms",
    "AI Platforms",
    "Data Platforms",
    "Cybersecurity Tools",
    "DevOps Tooling",
  ],

  skill: [
    "AI & ML Skills",
    "Cloud Skills",
    "Data Skills",
    "Cybersecurity Skills",
    "Software Engineering Skills",
  ],

  workforce: [
    "AI Roles",
    "Cloud Roles",
    "Data Roles",
    "Cybersecurity Roles",
    "Engineering Roles",
  ],

  mixed: [
    "AI & Machine Learning",
    "Cloud & DevOps",
    "Data & Analytics",
    "Cybersecurity",
    "Software Engineering",
    "Business & Management",
  ],
};

/* ==============================================
   Componente
============================================== */
export default function TopicModal({
  open,
  onClose,
  onCreated,
  editing,
}: Props) {
  const modalRef = useRef<HTMLDivElement | null>(null);

  const [form, setForm] = useState<Topic>({
    topic_name: "",
    search_query: "",
    intent: "mixed",
    category: "",
    subcategory: "",
    importance_weight: 1,
    min_required_results: 3,
  });

  const [loading, setLoading] = useState(false);

  /* ==============================================
     Precargar edición
  ============================================== */
  useEffect(() => {
    if (editing) {
      setForm({
        topic_name: editing.topic_name,
        search_query: editing.search_query,
        intent: editing.intent,
        category: editing.category ?? "",
        subcategory: editing.subcategory ?? "",
        importance_weight: editing.importance_weight,
        min_required_results: editing.min_required_results,
      });
    }
  }, [editing]);

  /* ==============================================
     Cerrar click externo
  ============================================== */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (modalRef.current && !modalRef.current.contains(e.target as Node)) {
        onClose();
      }
    };

    if (open) document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, [open, onClose]);

  /* ==============================================
     Handlers
  ============================================== */
  const handleChange = (
    e: React.ChangeEvent<
      HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
    >
  ) => {
    const { name, value } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]:
        name === "importance_weight" || name === "min_required_results"
          ? Number(value)
          : value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!form.topic_name.trim()) {
      return Swal.fire("Campo requerido", "Ingrese el nombre del Topic.", "warning");
    }

    if (!form.search_query.trim()) {
      return Swal.fire("Campo requerido", "Ingrese el Search Query.", "warning");
    }

    setLoading(true);

    try {
      if (editing?.id) {
        await axios.put(`/topics-ia/${editing.id}`, form);
        Swal.fire("Actualizado", "Topic actualizado correctamente.", "success");
      } else {
        await axios.post("/topics-ia", form);
        Swal.fire("Creado", "Topic creado correctamente.", "success");
      }

      onCreated();
      onClose();
    } catch (e) {
      Swal.fire("Error", "No se pudo guardar el Topic.", "error");
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  /* ==============================================
     Render
  ============================================== */
  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
      <div
        ref={modalRef}
        className="bg-white dark:bg-slate-800 w-full max-w-xl rounded-xl shadow-xl p-6 relative"
      >
        {/* Cerrar */}
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-slate-400 hover:text-slate-700"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-2xl font-semibold mb-6">
          {editing ? "Editar Topic IA" : "Nuevo Topic IA"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-5">

          {/* Nombre */}
          <div>
            <label className="block text-sm font-medium mb-1">
              Nombre del Topic *
            </label>
            <input
              name="topic_name"
              value={form.topic_name}
              onChange={handleChange}
              placeholder="Ej: Certificaciones de Machine Learning 2025"
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700"
            />
          </div>

          {/* Intent */}
         <div>
  <label className="block text-sm font-medium mb-1">
    ¿Qué tipo de análisis hará la IA?
  </label>
  <select
    name="intent"
    value={form.intent}
    onChange={handleChange}
    className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700"
  >
    <option value="certification">Certificaciones</option>
    <option value="technology_trend">Tendencias tecnológicas</option>
    <option value="skill">Skills / Habilidades</option>
    <option value="workforce">Workforce / Mercado laboral</option>
    <option value="mixed">Mixto (exploratorio)</option>
  </select>

  <p className="text-xs text-slate-500 mt-1">
    Define qué tipo de información la IA está autorizada a devolver.
  </p>
</div>


          {/* Search Query */}
          <div>
            <label className="block text-sm font-medium mb-1">
              Search Query *
            </label>
            <textarea
              name="search_query"
              value={form.search_query}
              onChange={handleChange}
              rows={2}
              placeholder='Ej: "machine learning certifications demand 2025"'
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700"
            />
          </div>

          {/* Categoría */}
      {/* Categoría (dependiente del intent) */}
<div>
  <label className="block text-sm font-medium mb-1">
    Categoría específica
  </label>

  <select
    name="category"
    value={form.category ?? ""}
    onChange={handleChange}
    className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700"
  >
    <option value="">— Seleccionar categoría —</option>

    {(CATEGORY_BY_INTENT[form.intent] ?? []).map((cat) => (
      <option key={cat} value={cat}>
        {cat}
      </option>
    ))}
  </select>

  <p className="text-xs text-slate-500 mt-1">
    Depende del tipo de análisis seleccionado.
  </p>
</div>


          {/* Subcategoría */}
          <div>
            <label className="block text-sm font-medium mb-1">
              Subcategoría (opcional)
            </label>
            <input
              name="subcategory"
              value={form.subcategory ?? ""}
              onChange={handleChange}
              placeholder="Ej: Machine Learning Engineer"
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700"
            />
          </div>

          {/* Pesos */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">
                Peso del Topic (1–10)
              </label>
              <input
                type="number"
                name="importance_weight"
                min={1}
                max={10}
                value={form.importance_weight}
                onChange={handleChange}
                className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700"
              />
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">
                Resultados mínimos
              </label>
              <input
                type="number"
                name="min_required_results"
                min={1}
                value={form.min_required_results}
                onChange={handleChange}
                className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700"
              />
            </div>
          </div>

          {/* Botones */}
          <div className="flex justify-end gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg bg-slate-200"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={loading}
              className="px-5 py-2 rounded-lg bg-[#1CBCE8] text-white"
            >
              {loading ? "Guardando…" : editing ? "Actualizar" : "Crear"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
