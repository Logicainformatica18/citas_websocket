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

/* ==============================================
   Componente
============================================== */
export default function TopicModal({ open, onClose, onCreated, editing }: Props) {
  const modalRef = useRef<HTMLDivElement | null>(null);
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);

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

  /* ------------------------------------------
     Auto resize textarea
  ------------------------------------------ */
  const autoResize = () => {
    if (textareaRef.current) {
      textareaRef.current.style.height = "auto";
      textareaRef.current.style.height =
        textareaRef.current.scrollHeight + "px";
    }
  };

  /* ------------------------------------------
     Preload edición
  ------------------------------------------ */
  useEffect(() => {
    if (editing) {
      setForm({
        topic_name: editing.topic_name,
        search_query: editing.search_query,
        intent: editing.intent ?? "mixed",
        category: editing.category ?? "",
        subcategory: editing.subcategory ?? "",
        importance_weight: editing.importance_weight ?? 1,
        min_required_results: editing.min_required_results ?? 3,
      });

      setTimeout(autoResize, 50);
    } else {
      setForm({
        topic_name: "",
        search_query: "",
        intent: "mixed",
        category: "",
        subcategory: "",
        importance_weight: 1,
        min_required_results: 3,
      });
    }
  }, [editing]);

  /* ------------------------------------------
     Cerrar al click externo
  ------------------------------------------ */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (modalRef.current && !modalRef.current.contains(e.target as Node)) {
        onClose();
      }
    };

    if (open) document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, [open, onClose]);

  /* ------------------------------------------
     Handle change
  ------------------------------------------ */
  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    const { name, value } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]:
        ["importance_weight", "min_required_results"].includes(name)
          ? Number(value)
          : value,
    }));

    if (name === "search_query") autoResize();
  };

  /* ------------------------------------------
     Guardar / Actualizar
  ------------------------------------------ */
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!form.topic_name.trim()) {
      return Swal.fire(
        "Campo requerido",
        "El nombre del Topic es obligatorio.",
        "warning"
      );
    }

    if (!form.search_query.trim()) {
      return Swal.fire(
        "Campo requerido",
        "El Search Query es obligatorio.",
        "warning"
      );
    }

    if (!form.intent) {
      return Swal.fire(
        "Campo requerido",
        "Debe seleccionar el tipo de intención.",
        "warning"
      );
    }

    setLoading(true);

    try {
      if (editing?.id) {
        await axios.put(`/topics-ia/${editing.id}`, form);
        Swal.fire("Actualizado", "El topic fue actualizado con éxito", "success");
      } else {
        await axios.post("/topics-ia", form);
        Swal.fire("Creado", "El topic fue creado con éxito", "success");
      }

      onCreated();
      onClose();
    } catch (err) {
      console.error(err);
      Swal.fire("Error", "No se pudo guardar el Topic.", "error");
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  /* ------------------------------------------
     Render
  ------------------------------------------ */
  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
      <div
        ref={modalRef}
        className="
          bg-white dark:bg-slate-800 dark:text-white
          p-6 rounded-xl shadow-xl w-full max-w-lg relative
          max-h-[90vh] overflow-y-auto
          animate-slideUp
        "
      >
        {/* Cerrar */}
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-2xl font-semibold mb-5">
          {editing ? "Editar Topic IA" : "Nuevo Topic IA"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-5">

          {/* Nombre */}
          <div className="space-y-1">
            <label className="block text-sm font-medium">
              Nombre del Topic *
            </label>
            <input
              type="text"
              name="topic_name"
              value={form.topic_name}
              onChange={handleChange}
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-[#1CBCE8] outline-none"
              placeholder="Ej: Machine Learning Engineer 2025"
            />
          </div>

          {/* Intent */}
          <div className="space-y-1">
            <label className="block text-sm font-medium">
              Tipo de análisis *
            </label>
            <select
              name="intent"
              value={form.intent}
              onChange={handleChange}
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-[#1CBCE8] outline-none"
            >
              <option value="mixed">Mixto (exploratorio)</option>
              <option value="certification">Certificaciones</option>
              <option value="technology_trend">Tendencias tecnológicas</option>
              <option value="skill">Skills / Habilidades</option>
              <option value="workforce">Workforce / Mercado laboral</option>
            </select>
          </div>

          {/* Search Query */}
          <div className="space-y-1">
            <label className="block text-sm font-medium">
              Search Query *
            </label>
            <textarea
              ref={textareaRef}
              name="search_query"
              value={form.search_query}
              onChange={handleChange}
              className="
                w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700
                resize-none overflow-hidden
                focus:ring-2 focus:ring-[#1CBCE8] outline-none
              "
              placeholder='Ej: "machine learning engineer certifications demand 2025 skills workforce"'
              rows={1}
            />
          </div>

          {/* Categoría */}
          <div className="space-y-1">
            <label className="block text-sm font-medium">Categoría</label>
            <input
              type="text"
              name="category"
              value={form.category ?? ""}
              onChange={handleChange}
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-[#1CBCE8] outline-none"
            />
          </div>

          {/* Subcategoría */}
          <div className="space-y-1">
            <label className="block text-sm font-medium">Subcategoría</label>
            <input
              type="text"
              name="subcategory"
              value={form.subcategory ?? ""}
              onChange={handleChange}
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-[#1CBCE8] outline-none"
            />
          </div>

          {/* Peso y mínimos */}
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="block text-sm font-medium">Peso (1–10)</label>
              <input
                type="number"
                name="importance_weight"
                value={form.importance_weight}
                onChange={handleChange}
                min={1}
                max={10}
                className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-[#1CBCE8] outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="block text-sm font-medium">
                Resultados mínimos
              </label>
              <input
                type="number"
                name="min_required_results"
                value={form.min_required_results}
                onChange={handleChange}
                min={1}
                className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-[#1CBCE8] outline-none"
              />
            </div>
          </div>

          {/* Botones */}
          <div className="flex justify-end gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg bg-slate-300 dark:bg-slate-600 hover:bg-slate-400 dark:hover:bg-slate-500"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={loading}
              className="px-5 py-2 rounded-lg bg-[#1CBCE8] hover:bg-[#17A8D0] text-white disabled:opacity-50"
            >
              {loading ? "Guardando…" : editing ? "Actualizar" : "Crear"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
