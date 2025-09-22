import { useState, useEffect } from "react";
import axios from "axios";

type Field = {
  id?: number;
  field_name: string;
  selector_type: string;
  selector_value: string;
  attr?: string | null;       // 👈 nuevo
  path?: string;
  parent_id?: number | null;
};

interface Props {
  scrapingId: number;
  field: Field | null;
  onClose: () => void;
  onSaved: (field: Field) => void;
}

export default function ScrapingFieldModal({ scrapingId, field, onClose, onSaved }: Props) {
  const [form, setForm] = useState<Field>(
    field ?? { field_name: "", selector_type: "css", selector_value: "", attr: "", path: "", parent_id: null }
  );
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState<{
    field_name?: string;
    selector_type?: string;
    selector_value?: string;
    attr?: string;
    path?: string;
    parent_id?: string;
  }>({});
  const [allFields, setAllFields] = useState<Field[]>([]);

  // 📌 Cargar lista de posibles padres
  useEffect(() => {
    axios
      .get(`/scrapings/${scrapingId}/fields/fetch?page=1`)
      .then((res) => {
        setAllFields(res.data.fields.data || []);
      })
      .catch((err) => console.error("Error cargando campos para parent_id", err));
  }, [scrapingId]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);
    setErrors({});
    try {
      if (field?.id) {
        // Update
        const res = await axios.put(
          `/scrapings/${scrapingId}/fields/${field.id}`,
          form
        );
        onSaved(res.data.field);
        onClose();
      } else {
        // Create
        const res = await axios.post(`/scrapings/${scrapingId}/fields`, form);
        onSaved(res.data.field);
        onClose();
        setForm({ field_name: "", selector_type: "css", selector_value: "", attr: "", path: "", parent_id: null });
      }
    } catch (error: any) {
      if (error.response?.status === 422) {
        setErrors(error.response.data.errors);
      }
      console.error("❌ Error en handleSubmit", error);
    } finally {
      setProcessing(false);
    }
  };

  return (
    <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
      <div className="bg-white dark:bg-gray-900 rounded shadow-lg p-6 w-full max-w-lg">
        <h2 className="text-xl font-bold mb-4">
          {field ? "Editar Campo" : "Nuevo Campo"}
        </h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Nombre */}
          <div>
            <label className="block text-sm font-medium mb-1">Nombre</label>
            <input
              type="text"
              value={form.field_name}
              onChange={(e) => setForm({ ...form, field_name: e.target.value })}
              className="w-full border rounded px-3 py-2"
              placeholder="Ej: Curso"
            />
            {errors.field_name && <p className="text-red-600 text-sm">{errors.field_name}</p>}
          </div>

          {/* Tipo de selector */}
          <div>
            <label className="block text-sm font-medium mb-1">Tipo de Selector</label>
            <select
              value={form.selector_type}
              onChange={(e) => setForm({ ...form, selector_type: e.target.value })}
              className="w-full border rounded px-3 py-2"
            >
              <option value="id">ID</option>
              <option value="class">Clase</option>
              <option value="tag">Etiqueta (tag)</option>
              <option value="attribute">Atributo</option>
              <option value="text">Texto</option>
              <option value="css">CSS Selector</option>
            </select>
            {errors.selector_type && (
              <p className="text-red-600 text-sm">{errors.selector_type}</p>
            )}
          </div>

          {/* Valor del selector */}
          <div>
            <label className="block text-sm font-medium mb-1">Valor del Selector</label>
            <input
              type="text"
              value={form.selector_value}
              onChange={(e) => setForm({ ...form, selector_value: e.target.value })}
              className="w-full border rounded px-3 py-2"
              placeholder="Ej: menu-cursos, nav-link, a[href*='carreras']"
            />
            {errors.selector_value && (
              <p className="text-red-600 text-sm">{errors.selector_value}</p>
            )}
          </div>

          {/* Atributo opcional */}
          <div>
            <label className="block text-sm font-medium mb-1">Atributo (opcional)</label>
            <input
              type="text"
              value={form.attr ?? ""}
              onChange={(e) => setForm({ ...form, attr: e.target.value })}
              className="w-full border rounded px-3 py-2"
              placeholder="Ej: href, src, title"
            />
            {errors.attr && <p className="text-red-600 text-sm">{errors.attr}</p>}
          </div>

          {/* Ruta */}
          <div>
            <label className="block text-sm font-medium mb-1">Ruta (Path)</label>
            <input
              type="text"
              value={form.path}
              onChange={(e) => setForm({ ...form, path: e.target.value })}
              className="w-full border rounded px-3 py-2"
              placeholder="/subpagina/opcional"
            />
            {errors.path && <p className="text-red-600 text-sm">{errors.path}</p>}
          </div>

          {/* Selector del padre */}
          <div>
            <label className="block text-sm font-medium mb-1">Campo Padre</label>
            <select
              value={form.parent_id ?? ""}
              onChange={(e) =>
                setForm({ ...form, parent_id: e.target.value ? Number(e.target.value) : null })
              }
              className="w-full border rounded px-3 py-2"
            >
              <option value="">-- Sin padre --</option>
              {allFields.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.field_name} ({f.selector_value})
                </option>
              ))}
            </select>
            {errors.parent_id && <p className="text-red-600 text-sm">{errors.parent_id}</p>}
          </div>

          {/* Botones */}
          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={processing}
              className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition disabled:opacity-50"
            >
              {processing ? "Guardando..." : "Guardar"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
