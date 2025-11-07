import { useState } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { X } from "lucide-react";

type Props = {
  open: boolean;
  onClose: () => void;
  onCreated: () => void;
};

export default function LanguageModal({ open, onClose, onCreated }: Props) {
  const [form, setForm] = useState({ name: "" });
  const [loading, setLoading] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) {
      Swal.fire("Campo obligatorio", "El nombre es requerido.", "warning");
      return;
    }

    setLoading(true);
    try {
      await axios.post("/languages", form);
      Swal.fire({
        title: "Lenguaje creado",
        text: "El nuevo lenguaje se registró correctamente.",
        icon: "success",
        timer: 2000,
        showConfirmButton: false,
        background: document.documentElement.classList.contains("dark") ? "#1e293b" : "#fff",
        color: document.documentElement.classList.contains("dark") ? "#fff" : "#000",
      });
      onCreated();
      onClose();
      setForm({ name: "" });
    } catch (err) {
      console.error("Error al crear lenguaje", err);
      Swal.fire("Error", "No se pudo crear el lenguaje.", "error");
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
      <div
        className="bg-white dark:bg-slate-800 dark:text-white p-6 rounded-lg shadow-lg w-full max-w-md relative"
      >
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-xl font-bold mb-4">Nuevo Lenguaje</h2>

        <form onSubmit={handleSubmit} className="space-y-4">
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
              {loading ? "Guardando..." : "Guardar"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
