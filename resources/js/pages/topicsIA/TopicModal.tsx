import { useEffect, useRef, useState } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { X } from "lucide-react";

type Topic = {
  id?: number;
  topic_name: string;
  search_query: string;
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

export default function TopicModal({ open, onClose, onCreated, editing }: Props) {
  const modalRef = useRef<HTMLDivElement | null>(null);
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);

  const [form, setForm] = useState<Topic>({
    topic_name: "",
    search_query: "",
    category: "",
    subcategory: "",
    importance_weight: 1,
    min_required_results: 3,
  });

  const [loading, setLoading] = useState(false);

  /* ------------------------------------------
     Auto Resize Textarea
  ------------------------------------------ */
  const autoResize = () => {
    if (textareaRef.current) {
      textareaRef.current.style.height = "auto";
      textareaRef.current.style.height = textareaRef.current.scrollHeight + "px";
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
        category: editing.category ?? "",
        subcategory: editing.subcategory ?? "",
        importance_weight: editing.importance_weight ?? 1,
        min_required_results: editing.min_required_results ?? 3,
      });

      setTimeout(() => autoResize(), 50);
    } else {
      setForm({
        topic_name: "",
        search_query: "",
        category: "",
        subcategory: "",
        importance_weight: 1,
        min_required_results: 3,
      });
    }
  }, [editing]);

  /* ------------------------------------------
     Cerrar modal al hacer clic afuera
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
     Manejar cambios del formulario
  ------------------------------------------ */
  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
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
      return Swal.fire("Campo requerido", "El nombre del Topic es obligatorio.", "warning");
    }

    if (!form.search_query.trim()) {
      return Swal.fire("Campo requerido", "El Search Query es obligatorio.", "warning");
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
     Render modal
  ------------------------------------------ */
  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
      {/* Contenedor que limita altura y agrega scroll interno */}
      <div
        ref={modalRef}
        className="
          bg-white dark:bg-slate-800 dark:text-white
          p-6 rounded-xl shadow-xl w-full max-w-lg relative
          max-h-[90vh] overflow-y-auto
          animate-slideUp
        "
      >
        {/* Botón cerrar */}
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white transition"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-2xl font-semibold mb-5">
          {editing ? "Editar Topic IA" : "Nuevo Topic IA"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Nombre */}
          <div className="space-y-1">
            <label className="block text-sm font-medium">Nombre del Topic *</label>
            <input
              type="text"
              name="topic_name"
              value={form.topic_name}
              onChange={handleChange}
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="Ej: IA Generativa en Salud"
              required
            />
          </div>

          {/* Search Query */}
          <div className="space-y-1">
            <label className="block text-sm font-medium">Search Query *</label>
            <textarea
              ref={textareaRef}
              name="search_query"
              value={form.search_query}
              onChange={handleChange}
              className="
                w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700
                resize-none overflow-hidden
                focus:ring-2 focus:ring-blue-500 outline-none
              "
              placeholder='Ej: "AI generative trends 2025" McKinsey; Gartner; OpenAI'
              rows={1}
              required
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
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="Ej: Tecnologías Emergentes"
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
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="Ej: Modelos Generativos"
            />
          </div>

          {/* Peso y resultados mínimos */}
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="block text-sm font-medium">Peso (1-10)</label>
              <input
                type="number"
                name="importance_weight"
                value={form.importance_weight}
                onChange={handleChange}
                min={1}
                max={10}
                className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="block text-sm font-medium">Resultados mínimos</label>
              <input
                type="number"
                name="min_required_results"
                value={form.min_required_results}
                onChange={handleChange}
                min={1}
                className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
              />
            </div>
          </div>

          {/* Botones */}
          <div className="flex justify-end gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg bg-slate-300 dark:bg-slate-600 hover:bg-slate-400 dark:hover:bg-slate-500 transition"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={loading}
              className="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition disabled:opacity-50"
            >
              {loading ? "Guardando..." : editing ? "Actualizar" : "Crear"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
