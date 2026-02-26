import { useState, useEffect, useRef } from "react";
import axios from "axios";
import Swal from "sweetalert2";

type MarketEntity = {
  id?: number;
  name: string;
  entity_type: string;
  origin?: string | null;
  category?: string | null;
  vendor?: string | null;
  level?: string | null;
  has_isil?: boolean;
  has_trend?: boolean;
};

type Props = {
  open: boolean;
  editing?: MarketEntity | null;
  onClose: () => void;
  onSaved: () => void;
};

export default function MarketEntityModal({
  open,
  editing,
  onClose,
  onSaved,
}: Props) {
  const modalRef = useRef<HTMLDivElement>(null);

  const [form, setForm] = useState<MarketEntity>({
    name: "",
    entity_type: "",
    origin: "",
    category: "",
    vendor: "",
    level: "",
    has_isil: false,
    has_trend: false,
  });

  useEffect(() => {
    if (editing) {
      setForm(editing);
    }
  }, [editing]);

  // ESC close
  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", handleEsc);
    return () => document.removeEventListener("keydown", handleEsc);
  }, [onClose]);

  const handleOutsideClick = (e: React.MouseEvent) => {
    if (modalRef.current && !modalRef.current.contains(e.target as Node)) {
      onClose();
    }
  };

  const handleChange = (field: keyof MarketEntity, value: any) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const save = async () => {
    try {
      if (editing?.id) {
        await axios.put(`/market-entities/${editing.id}`, form);
      } else {
        await axios.post(`/market-entities`, form);
      }

      Swal.fire("Éxito", "Entidad guardada correctamente.", "success");
      onSaved();
      onClose();
    } catch (e: any) {
      Swal.fire(
        "Error",
        e?.response?.data?.message ?? "No se pudo guardar.",
        "error"
      );
    }
  };

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
      onMouseDown={handleOutsideClick}
    >
      <div
        ref={modalRef}
        className="w-full max-w-2xl bg-white dark:bg-[#1e293b] rounded-2xl shadow-2xl flex flex-col max-h-[90vh] animate-fade-in"
      >
        {/* HEADER */}
        <div className="px-8 py-6 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-2xl font-semibold text-gray-800 dark:text-white">
            {editing ? "Editar Entidad" : "Nueva Entidad"}
          </h2>
        </div>

        {/* CONTENT */}
        <div className="px-8 py-6 overflow-y-auto space-y-8">

          {/* INFORMACIÓN BÁSICA */}
          <Section title="Información General">
            <Input
              label="Nombre"
              value={form.name}
              onChange={(v) => handleChange("name", v)}
            />

            <Select
              label="Tipo de Entidad"
              value={form.entity_type}
              onChange={(v) => handleChange("entity_type", v)}
              options={[
                { label: "Certification", value: "certification" },
                { label: "Technology", value: "technology" },
                { label: "Language", value: "language" },
              ]}
            />
          </Section>

          {/* METADATA */}
          <Section title="Metadatos">
            <Input
              label="Origen"
              value={form.origin ?? ""}
              onChange={(v) => handleChange("origin", v)}
            />
            <Input
              label="Categoría"
              value={form.category ?? ""}
              onChange={(v) => handleChange("category", v)}
            />
            <Input
              label="Vendor"
              value={form.vendor ?? ""}
              onChange={(v) => handleChange("vendor", v)}
            />
            <Input
              label="Nivel"
              value={form.level ?? ""}
              onChange={(v) => handleChange("level", v)}
            />
          </Section>

          {/* FLAGS */}
          <Section title="Configuración">
            <Checkbox
              label="Tiene ISIL"
              checked={!!form.has_isil}
              onChange={(v) => handleChange("has_isil", v)}
            />
            <Checkbox
              label="Tiene Trend"
              checked={!!form.has_trend}
              onChange={(v) => handleChange("has_trend", v)}
            />
          </Section>

        </div>

        {/* FOOTER */}
        <div className="px-8 py-5 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
          <button
            onClick={onClose}
            className="px-5 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:opacity-80 transition"
          >
            Cancelar
          </button>

          <button
            onClick={save}
            className="px-5 py-2 rounded-lg bg-[#1CBCE8] text-white hover:bg-[#17A8D0] transition"
          >
            Guardar
          </button>
        </div>
      </div>
    </div>
  );
}

/* ================= COMPONENTES ================= */

function Section({ title, children }: any) {
  return (
    <div>
      <h3 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-4">
        {title}
      </h3>
      <div className="space-y-4">{children}</div>
    </div>
  );
}

function Input({ label, value, onChange }: any) {
  return (
    <div>
      <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">
        {label}
      </label>
      <input
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full px-4 py-2 rounded-lg border
          bg-gray-50 dark:bg-gray-800
          border-gray-300 dark:border-gray-700
          focus:ring-2 focus:ring-[#1CBCE8] outline-none transition"
      />
    </div>
  );
}

function Select({ label, value, onChange, options }: any) {
  return (
    <div>
      <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">
        {label}
      </label>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full px-4 py-2 rounded-lg border
          bg-gray-50 dark:bg-gray-800
          border-gray-300 dark:border-gray-700
          focus:ring-2 focus:ring-[#1CBCE8] outline-none transition"
      >
        <option value="">Seleccione</option>
        {options.map((opt: any) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </div>
  );
}

function Checkbox({ label, checked, onChange }: any) {
  return (
    <label className="flex items-center gap-2 text-sm">
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        className="w-4 h-4"
      />
      {label}
    </label>
  );
}
