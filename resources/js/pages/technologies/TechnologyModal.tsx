import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { X } from "lucide-react";

type Technology = {
  id?: number;
  name: string;
  category_id?: number | null;
  context_id?: number | null;
};

type Category = {
  id: number;
  name: string;
};

type Context = {
  id: number;
  search_context: string; // 🔥 role_name eliminado
};

type Props = {
  open: boolean;
  onClose: () => void;
  onCreated: () => void;
  editing?: Technology | null;
  categories: Category[];
  contexts: Context[];
};

export default function TechnologyModal({
  open,
  onClose,
  onCreated,
  editing,
  categories,
  contexts,
}: Props) {
  const [form, setForm] = useState<Technology>({
    name: "",
    category_id: null,
    context_id: null,
  });

  const [loading, setLoading] = useState(false);

  // Prellenar si editamos
  useEffect(() => {
    if (editing) {
      setForm({
        id: editing.id,
        name: editing.name,
        category_id: editing.category_id ?? null,
        context_id: editing.context_id ?? null,
      });
    } else {
      setForm({
        name: "",
        category_id: null,
        context_id: null,
      });
    }
  }, [editing]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
  ) => {
    const { name, value } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]:
        name === "category_id" || name === "context_id"
          ? value
            ? Number(value)
            : null
          : value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!form.name.trim()) {
      Swal.fire("Campo obligatorio", "El nombre es requerido.", "warning");
      return;
    }

    setLoading(true);

    try {
      if (editing) {
        // Actualizar
        await axios.put(`/technologies/${editing.id}`, form);

        Swal.fire({
          title: "Actualizado",
          text: "La tecnología se modificó correctamente.",
          icon: "success",
          timer: 1800,
          showConfirmButton: false,
          background: document.documentElement.classList.contains("dark")
            ? "#1e293b"
            : "#fff",
          color: document.documentElement.classList.contains("dark")
            ? "#fff"
            : "#000",
        });
      } else {
        // Crear
        await axios.post(`/technologies`, form);

        Swal.fire({
          title: "Creado",
          text: "La nueva tecnología se registró correctamente.",
          icon: "success",
          timer: 1800,
          showConfirmButton: false,
          background: document.documentElement.classList.contains("dark")
            ? "#1e293b"
            : "#fff",
          color: document.documentElement.classList.contains("dark")
            ? "#fff"
            : "#000",
        });
      }

      onCreated();
      onClose();
    } catch (error) {
      console.error(error);
      Swal.fire("Error", "No se pudo guardar la tecnología.", "error");
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-slate-800 dark:text-white p-6 rounded-lg shadow-lg w-full max-w-md relative">
        {/* ❌ Botón cerrar */}
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-xl font-bold mb-4">
          {editing ? "Editar Tecnología" : "Nueva Tecnología"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Nombre */}
          <div>
            <label className="block text-sm mb-1">Nombre *</label>
            <input
              type="text"
              name="name"
              value={form.name}
              onChange={handleChange}
              placeholder="Ej: React, Laravel, Docker..."
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* Categoría */}
          <div>
            <label className="block text-sm mb-1">Categoría</label>
            <select
              name="category_id"
              value={form.category_id ?? ""}
              onChange={handleChange}
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sin categoría</option>

              {categories.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
          </div>

          {/* Contexto */}
          <div>
            <label className="block text-sm mb-1">Contexto semántico</label>
            <select
              name="context_id"
              value={form.context_id ?? ""}
              onChange={handleChange}
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sin contexto</option>

              {contexts.map((ctx) => (
                <option key={ctx.id} value={ctx.id}>
                  {ctx.search_context}
                </option>
              ))}
            </select>
          </div>

          {/* BOTONES */}
          <div className="flex justify-end gap-2 mt-6">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded bg-slate-300 dark:bg-slate-600 hover:bg-slate-400 dark:hover:bg-slate-500 transition"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={loading}
              className="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-60 transition"
            >
              {loading ? "Guardando..." : editing ? "Actualizar" : "Guardar"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
