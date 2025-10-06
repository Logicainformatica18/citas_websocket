import { useState } from 'react';
import axios from 'axios';
import { X } from 'lucide-react';

type Props = {
  open: boolean;
  onClose: () => void;
  onCreated: () => void;
};

export default function CareerModal({ open, onClose, onCreated }: Props) {
  const [form, setForm] = useState({
    name: '',
    faculty: '',
    degree_title: '',
    duration_years: '',
    description: '',
    detail: '',
    active: true,
  });

  const [loading, setLoading] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await axios.post('/careers', form);
      onCreated();
      onClose();
    } catch (err) {
      console.error('Error al crear carrera', err);
      alert('No se pudo crear la carrera');
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
      <div className="bg-slate-800 text-white p-6 rounded-lg shadow-lg w-full max-w-lg relative">
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-slate-400 hover:text-slate-100"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-xl font-bold mb-4">Nueva Carrera</h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm mb-1">Nombre *</label>
            <input
              type="text"
              name="name"
              value={form.name}
              onChange={handleChange}
              required
              className="w-full p-2 rounded bg-slate-700 text-white focus:outline-none"
            />
          </div>

          <div>
            <label className="block text-sm mb-1">Facultad</label>
            <input
              type="text"
              name="faculty"
              value={form.faculty}
              onChange={handleChange}
              className="w-full p-2 rounded bg-slate-700 text-white"
            />
          </div>

          <div>
            <label className="block text-sm mb-1">Título</label>
            <input
              type="text"
              name="degree_title"
              value={form.degree_title}
              onChange={handleChange}
              className="w-full p-2 rounded bg-slate-700 text-white"
            />
          </div>

          <div>
            <label className="block text-sm mb-1">Duración (años)</label>
            <input
              type="number"
              name="duration_years"
              value={form.duration_years}
              onChange={handleChange}
              min="1"
              max="10"
              className="w-full p-2 rounded bg-slate-700 text-white"
            />
          </div>

          <div>
            <label className="block text-sm mb-1">Descripción</label>
            <textarea
              name="description"
              value={form.description}
              onChange={handleChange}
              className="w-full p-2 rounded bg-slate-700 text-white h-20 resize-none"
            />
          </div>

          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              name="active"
              checked={form.active}
              onChange={handleChange}
              className="w-4 h-4"
            />
            <label>Activa</label>
          </div>

          <div className="flex justify-end gap-2 mt-6">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded bg-slate-600 hover:bg-slate-500"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 disabled:opacity-60"
            >
              {loading ? 'Guardando...' : 'Guardar'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
