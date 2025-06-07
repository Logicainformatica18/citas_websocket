import { useEffect, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

export default function AppointmentTypeModal({
  open,
  onClose,
  onSaved,
  itemToEdit,
}: {
  open: boolean;
  onClose: () => void;
  onSaved: (item: any) => void;
  itemToEdit?: any;
}) {
  const [formData, setFormData] = useState({
    tipo: '',
    habilitado: false,
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (itemToEdit) {
      setFormData({
        tipo: itemToEdit.tipo ?? '',
        habilitado: itemToEdit.habilitado ?? false,
      });
    } else {
      setFormData({ tipo: '', habilitado: false });
    }
  }, [itemToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, type, checked, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleSubmit = async () => {
    try {
      setSaving(true);
      const url = itemToEdit ? `/appointment-types/${itemToEdit.id_tipo_cita ?? itemToEdit.id}` : '/appointment-types';
      const method = itemToEdit ? 'put' : 'post';
      const response = await axios[method](url, formData);
      toast.success(itemToEdit ? 'Actualizado ✅' : 'Creado ✅');
      onSaved(response.data.appointmentType);
      onClose();
    } catch (error) {
      console.error(error);
      toast.error('Error al guardar');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(val) => !val && onClose()}>
      <DialogContent className="sm:max-w-md" aria-describedby="appointment-type-description">
        <DialogHeader>
          <DialogTitle>{itemToEdit ? 'Editar Tipo de Cita' : 'Nuevo Tipo de Cita'}</DialogTitle>
        </DialogHeader>

        <p id="appointment-type-description" className="text-sm text-gray-500 mb-4">
          Completa los datos para guardar el tipo de cita.
        </p>

        <div className="grid gap-4 py-2">
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="tipo" className="text-right">Tipo</Label>
            <Input
              id="tipo"
              name="tipo"
              value={formData.tipo}
              onChange={handleChange}
              className="col-span-3"
            />
          </div>
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="habilitado" className="text-right">¿Habilitado?</Label>
            <input
              type="checkbox"
              id="habilitado"
              name="habilitado"
              checked={formData.habilitado}
              onChange={handleChange}
              className="col-span-3 h-5 w-5"
            />
          </div>
        </div>

        <DialogFooter className="flex justify-between">
          <Button variant="outline" onClick={() => setFormData({ tipo: '', habilitado: false })} disabled={saving}>
            Limpiar
          </Button>
          <div className="flex gap-2">
            <Button onClick={handleSubmit} disabled={saving}>
              {saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {itemToEdit ? 'Actualizar' : 'Guardar'}
            </Button>
            <Button variant="ghost" onClick={onClose} disabled={saving}>Cerrar</Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}