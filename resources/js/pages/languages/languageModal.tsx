import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { X } from "lucide-react";

type Language = {
  id?: number;
  name: string;
  context_id?: number | null;
};

type Context = {
  id: number;
  role_name: string;
  search_context: string;
};

type Props = {
  open: boolean;
  onClose: () => void;
  onCreated: () => void;
  editing?: Language | null;
  contexts: Context[]; // 👈 Lista de contextos semánticos
};

export default function LanguageModal({
  open,
  onClose,
  onCreated,
  editing,
  contexts,
}: Props) {
  const [form, setForm] = useState<Language>({ name: "", context_id: null });
  const [loading, setLoading] = useState(false);

  // Si estamos editando, precargamos los valores
  useEffect(() => {
    if (editing) setForm(editing);
    else setForm({ name: "", context_id: null });
  }, [editing]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
  ) => {
    const { name, value } = e.target;
    setForm((prev) => ({
      ...prev,
      [name]: name === "context_id" ? (value ? Number(value) : null) : value,
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
        // ✏️ Actualización
        await axios.put(`/languages/${editing.id}`, form);
        Swal.fire({
          title: "Lenguaje actualizado",
          text: "El lenguaje se modificó correctamente.",
          icon: "success",
          timer: 2000,
          showConfirmButton: false,
          background: document.documentElement.classList.contains("dark")
            ? "#1e293b"
            : "#fff",
          color: document.documentElement.classList.contains("dark")
            ? "#fff"
            : "#000",
        });
      } else {
        // 🆕 Creación
        await axios.post("/languages", form);
        Swal.fire({
          title: "Lenguaje creado",
          text: "El nuevo lenguaje se registró correctamente.",
          icon: "success",
          timer: 2000,
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
      setForm({ name: "", context_id: null });
    } catch (err) {
      console.error("Error al guardar lenguaje", err);
      Swal.fire("Error", "No se pudo guardar el lenguaje.", "error");
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-slate-800 dark:text-white p-6 rounded-lg shadow-lg w-full max-w-md relative">
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-xl font-bold mb-4">
          {editing ? "Editar Lenguaje" : "Nuevo Lenguaje"}
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
              placeholder="Ej: Python, JavaScript, Go"
              required
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* Contexto */}
          <div>
            <label className="block text-sm mb-1">Contexto semántico</label>
            <select
              name="context_id"
              value={form.context_id ?? ""}
              onChange={handleChange}
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Sin contexto</option>
              {contexts.map((ctx) => (
                <option key={ctx.id} value={ctx.id}>
                  {ctx.role_name} ({ctx.search_context})
                </option>
              ))}
            </select>
          </div>

          {/* Botones */}
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
