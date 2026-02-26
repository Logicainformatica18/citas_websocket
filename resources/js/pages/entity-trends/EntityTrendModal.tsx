import { useState, useEffect, useRef } from "react";
import axios from "axios";
import Swal from "sweetalert2";

type Props = {
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
  editing?: any;
};

export default function EntityTrendModal({
  open,
  onClose,
  onSaved,
  editing,
}: Props) {
  const modalRef = useRef<HTMLDivElement>(null);

  const [form, setForm] = useState({
    trend_name: "",
    trend_score: "",
    year: "",
    quarter: "",
    match_type: "",
    confidence_score: "",
    source_title: "",
    source_url: "",
  });

  useEffect(() => {
    if (editing) {
      setForm({
        trend_name: editing.trend_name ?? "",
        trend_score: editing.trend_score ?? "",
        year: editing.year ?? "",
        quarter: editing.quarter ?? "",
        match_type: editing.match_type ?? "",
        confidence_score: editing.confidence_score ?? "",
        source_title: editing.source_title ?? "",
        source_url: editing.source_url ?? "",
      });
    }
  }, [editing]);

  // 🔥 Cerrar con ESC
  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", handleEsc);
    return () => document.removeEventListener("keydown", handleEsc);
  }, [onClose]);

  // 🔥 Cerrar haciendo click afuera
  const handleOutsideClick = (e: React.MouseEvent) => {
    if (modalRef.current && !modalRef.current.contains(e.target as Node)) {
      onClose();
    }
  };

  const handleChange = (field: string, value: any) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const save = async () => {
    try {
      if (editing) {
        await axios.put(`/entity-trends/${editing.id}`, form);
      } else {
        await axios.post(`/entity-trends`, form);
      }

      Swal.fire("Éxito", "Trend guardado correctamente.", "success");
      onSaved();
      onClose();
    } catch {
      Swal.fire("Error", "No se pudo guardar el trend.", "error");
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
            {editing ? "Editar Trend" : "Nuevo Trend"}
          </h2>
        </div>

        {/* CONTENT SCROLLABLE */}
        <div className="px-8 py-6 overflow-y-auto space-y-8">

          {/* INFORMACIÓN GENERAL */}
          <Section title="Información General">
            <Input
              label="Nombre del Trend"
              value={form.trend_name}
              onChange={(v) => handleChange("trend_name", v)}
            />
            <Input
              label="Trend Score"
              type="number"
              value={form.trend_score}
              onChange={(v) => handleChange("trend_score", v)}
            />
          </Section>

          {/* PERIODO */}
          <Section title="Periodo">
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Año"
                type="number"
                value={form.year}
                onChange={(v) => handleChange("year", v)}
              />
              <Input
                label="Quarter"
                type="number"
                value={form.quarter}
                onChange={(v) => handleChange("quarter", v)}
              />
            </div>
          </Section>

          {/* CLASIFICACIÓN */}
          <Section title="Clasificación">
            <Input
              label="Tipo de Match"
              value={form.match_type}
              onChange={(v) => handleChange("match_type", v)}
            />
            <Input
              label="Confidence Score"
              type="number"
              step="0.01"
              value={form.confidence_score}
              onChange={(v) => handleChange("confidence_score", v)}
            />
          </Section>

          {/* FUENTE */}
          <Section title="Fuente">
            <Input
              label="Título de la Fuente"
              value={form.source_title}
              onChange={(v) => handleChange("source_title", v)}
            />
            <Input
              label="URL de la Fuente"
              value={form.source_url}
              onChange={(v) => handleChange("source_url", v)}
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

/* ============================================================
   COMPONENTES AUXILIARES
============================================================ */

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

function Input({
  label,
  value,
  onChange,
  type = "text",
  step,
}: any) {
  return (
    <div>
      <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">
        {label}
      </label>
      <input
        type={type}
        step={step}
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
