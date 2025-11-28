import { useState, useEffect } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { X } from "lucide-react";

type Competency = {
  id?: number;
  name: string;
  category?: string | null;
  weight?: number | null;
  description_es?: string | null;
  description_en?: string | null;
  career_id?: number | null;
};

type Career = {
  id: number;
  name: string;
};

type Props = {
  open: boolean;
  onClose: () => void;
  onCreated: () => void;
  editing?: Competency | null;
  careers: Career[];
};

export default function CompetencyModal({
  open,
  onClose,
  onCreated,
  editing,
  careers,
}: Props) {
  const [form, setForm] = useState<Competency>({
    name: "",
    category: "",
    weight: 0,
    description_es: "",
    description_en: "",
    career_id: null,
  });

  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (editing) setForm(editing);
    else
      setForm({
        name: "",
        category: "",
        weight: 0,
        description_es: "",
        description_en: "",
        career_id: null,
      });
  }, [editing]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]:
        name === "weight"
          ? Number(value)
          : name === "career_id"
          ? value
            ? Number(value)
            : null
          : value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!form.name.trim()) {
      Swal.fire("Campo requerido", "El nombre es obligatorio.", "warning");
      return;
    }

    setLoading(true);
    try {
      if (editing) {
        await axios.put(`/competencies/${editing.id}`, form);

        Swal.fire({
          title: "Competencia actualizada",
          text: "Cambios guardados correctamente.",
          icon: "success",
          timer: 2000,
          showConfirmButton: false,
        });
      } else {
        await axios.post("/competencies", form);

        Swal.fire({
          title: "Competencia creada",
          text: "La nueva competencia fue registrada.",
          icon: "success",
          timer: 2000,
          showConfirmButton: false,
        });
      }

      onCreated();
      onClose();
    } catch (err) {
      Swal.fire("Error", "No se pudo guardar la competencia.", "error");
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-slate-800 dark:text-white p-6 rounded-lg shadow-lg w-full max-w-lg relative">
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-xl font-bold mb-4">
          {editing ? "Editar Competencia" : "Nueva Competencia"}
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
              placeholder="Ej: Pensamiento crítico, Comunicación efectiva"
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700"
              required
            />
          </div>

          {/* Categoría */}
          <div>
            <label className="block text-sm mb-1">Categoría</label>
            <input
              type="text"
              name="category"
              value={form.category ?? ""}
              onChange={handleChange}
              placeholder="Ej: Técnica, Blanda, Digital"
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700"
            />
          </div>

          {/* Carrera */}
          <div>
            <label className="block text-sm mb-1">Carrera asociada</label>
            <select
              name="career_id"
              value={form.career_id ?? ""}
              onChange={handleChange}
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700"
            >
              <option value="">Ninguna</option>
              {careers.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>

          {/* Peso */}
          <div>
            <label className="block text-sm mb-1">Peso (0 a 1)</label>
            <input
              type="number"
              name="weight"
              value={form.weight ?? 0}
              onChange={handleChange}
              min="0"
              max="1"
              step="0.01"
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700"
            />
          </div>

          {/* Descripción ES */}
          <div>
            <label className="block text-sm mb-1">Descripción (ES)</label>
            <textarea
              name="description_es"
              value={form.description_es ?? ""}
              onChange={handleChange}
              rows={3}
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700"
            />
          </div>

          {/* Descripción EN */}
          <div>
            <label className="block text-sm mb-1">Descripción (EN)</label>
            <textarea
              name="description_en"
              value={form.description_en ?? ""}
              onChange={handleChange}
              rows={3}
              className="w-full p-2 rounded bg-slate-100 dark:bg-slate-700"
            />
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
