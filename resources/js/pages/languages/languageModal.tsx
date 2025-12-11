import { useState, useEffect, useRef } from "react";
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
  contexts: Context[];
};

export default function LanguageModal({
  open,
  onClose,
  onCreated,
  editing,
  contexts,
}: Props) {
  const modalRef = useRef<HTMLDivElement | null>(null);

  const [form, setForm] = useState<Language>({
    name: "",
    context_id: null,
  });

  const [loading, setLoading] = useState(false);

  // Preload en edición
  useEffect(() => {
    if (editing) {
      setForm({
        name: editing.name,
        context_id: editing.context_id ?? null,
      });
    } else {
      setForm({ name: "", context_id: null });
    }
  }, [editing]);

  // Detectar clic fuera del modal ⭐⭐⭐⭐⭐
  useEffect(() => {
    const handleOutside = (e: MouseEvent) => {
      if (modalRef.current && !modalRef.current.contains(e.target as Node)) {
        onClose();
      }
    };

    if (open) document.addEventListener("mousedown", handleOutside);

    return () => document.removeEventListener("mousedown", handleOutside);
  }, [open, onClose]);

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
      if (editing?.id) {
        await axios.put(`/languages/${editing.id}`, form);
        Swal.fire("Actualizado", "El lenguaje fue modificado.", "success");
      } else {
        await axios.post("/languages", form);
        Swal.fire("Creado", "El nuevo lenguaje fue registrado.", "success");
      }

      onCreated();
      onClose();
    } catch (err) {
      console.error("Error al guardar lenguaje", err);
      Swal.fire("Error", "No se pudo guardar el lenguaje.", "error");
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 animate-fadeIn">
      <div
        ref={modalRef}
        className="bg-white dark:bg-slate-800 dark:text-white p-6 rounded-xl shadow-xl w-full max-w-md relative animate-slideUp"
      >
        {/* Botón cerrar */}
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white transition"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-xl font-semibold mb-4">
          {editing ? "Editar Lenguaje" : "Nuevo Lenguaje"}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Nombre */}
          <div>
            <label className="block text-sm mb-1">Nombre *</label>
            <input
              type="text"
              name="name"
              value={form.name}
              onChange={handleChange}
              placeholder="Ej: Python, JavaScript..."
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
              required
            />
          </div>

          {/* Contexto */}
          <div>
            <label className="block text-sm mb-1">Contexto semántico</label>
            <select
              name="context_id"
              value={form.context_id ?? ""}
              onChange={handleChange}
              className="w-full p-2.5 rounded-lg bg-slate-100 dark:bg-slate-700 focus:ring-2 focus:ring-blue-500 outline-none"
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
          <div className="flex justify-end gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 bg-slate-300 dark:bg-slate-600 rounded-lg hover:bg-slate-400 dark:hover:bg-slate-500 transition"
            >
              Cancelar
            </button>

            <button
              type="submit"
              disabled={loading}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
            >
              {loading ? "Guardando..." : editing ? "Actualizar" : "Guardar"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
