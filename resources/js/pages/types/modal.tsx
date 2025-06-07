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
import { Loader2 } from 'lucide-react'; // 👈 para el spinner

interface Props {
  open: boolean;
  onClose: () => void;
  onSaved: (type: any) => void;
  itemToEdit?: any;
}

export default function TypeModal({ open, onClose, onSaved, itemToEdit }: Props) {
  const [form, setForm] = useState({
    id: undefined,
    description: '',
    detail: '',
  });

  const [isSubmitting, setIsSubmitting] = useState(false); // 👈 spinner de guardado

  useEffect(() => {
    if (itemToEdit) {
      setForm({
        id: itemToEdit.id,
        description: itemToEdit.description || '',
        detail: itemToEdit.detail || '',
      });
    } else {
      setForm({ id: undefined, description: '', detail: '' });
    }
  }, [itemToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async () => {
    try {
      setIsSubmitting(true);
      const res = form.id
        ? await axios.put(`/types/${form.id}`, form)
        : await axios.post('/types', form);

      onSaved(res.data.type);
      onClose();
    } catch (error) {
      console.error('Error al guardar tipo', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{form.id ? 'Editar Tipo' : 'Nuevo Tipo'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <Input
            name="description"
            placeholder="Descripción"
            value={form.description}
            onChange={handleChange}
          />
          <Input
            name="detail"
            placeholder="Detalle"
            value={form.detail}
            onChange={handleChange}
          />
        </div>

        <DialogFooter className="mt-6">
          <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
            Cancelar
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
