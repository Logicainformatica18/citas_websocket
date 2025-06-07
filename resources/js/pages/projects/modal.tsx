// resources/js/pages/projects/modal.tsx
import { useEffect, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import axios from 'axios';
import { Loader2 } from 'lucide-react';

interface Props {
  open: boolean;
  onClose: () => void;
  onSaved: (project: any) => void;
  itemToEdit?: any;
}

export default function ProjectModal({ open, onClose, onSaved, itemToEdit }: Props) {
  const [form, setForm] = useState({
    id_proyecto: undefined,
    descripcion: '',
    habilitado: 1,
  });

  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (itemToEdit) {
      setForm({
        id_proyecto: itemToEdit.id_proyecto,
        descripcion: itemToEdit.descripcion || '',
        habilitado: itemToEdit.habilitado ?? 1,
      });
    } else {
      setForm({ id_proyecto: undefined, descripcion: '', habilitado: 1 });
    }
  }, [itemToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? (checked ? 1 : 0) : value,
    }));
  };

  const handleSubmit = async () => {
    try {
      setIsSubmitting(true);
      const res = form.id_proyecto
        ? await axios.put(`/projects/${form.id_proyecto}`, form)
        : await axios.post('/projects', form);

      onSaved(res.data.project);
      onClose();
    } catch (error) {
      console.error('Error saving project', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{form.id_proyecto ? 'Editar Proyecto' : 'Nuevo Proyecto'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <Input
            name="descripcion"
            placeholder="Description"
            value={form.descripcion}
            onChange={handleChange}
          />
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              name="habilitado"
              checked={form.habilitado === 1}
              onChange={handleChange}
            />
           Habilitado
          </label>
        </div>

        <DialogFooter className="mt-6">
          <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                Guardando...
              </>
            ) : (
              'Guardar'
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
