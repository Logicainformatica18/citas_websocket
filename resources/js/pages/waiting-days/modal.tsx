// ✅ AppointmentTypeModal.tsx adaptado como DaysWaitModal.tsx con checkbox en lugar de Switch
import { useEffect, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import HourglassLoader from '@/components/HourglassLoader';
import axios from 'axios';

interface Props {
  open: boolean;
  onClose: () => void;
  onSaved: (data: any) => void;
  itemToEdit: any;
}

export default function DaysWaitModal({ open, onClose, onSaved, itemToEdit }: Props) {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    dias: '',
    habilitado: true,
  });

  useEffect(() => {
    if (itemToEdit) {
      setFormData({
        dias: itemToEdit.dias || '',
        habilitado: Boolean(itemToEdit.habilitado),
      });
    } else {
      setFormData({ dias: '', habilitado: true });
    }
  }, [itemToEdit]);

  const handleChange = (field: string, value: any) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async () => {
    setLoading(true);
    try {
      const url = itemToEdit ? `/days-wait/${itemToEdit.id_dias_espera}` : '/days-wait';
      const method = itemToEdit ? 'put' : 'post';
      const res = await axios[method](url, formData);
      onSaved(res.data.daysWait);
      onClose();
    } catch (e) {
      console.error('Error al guardar:', e);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{itemToEdit ? 'Editar Días de Espera' : 'Nuevo Días de Espera'}</DialogTitle>
        </DialogHeader>

        {loading ? <HourglassLoader /> : (
          <div className="space-y-4">
            <div>
              <Label>Días</Label>
              <Input
                value={formData.dias}
                onChange={(e) => handleChange('dias', e.target.value)}
              />
            </div>
            <div className="flex items-center gap-2">
              <Label htmlFor="habilitado">¿Habilitado?</Label>
              <input
                id="habilitado"
                type="checkbox"
                checked={formData.habilitado}
                onChange={(e) => handleChange('habilitado', e.target.checked)}
              />
            </div>
          </div>
        )}

        <DialogFooter className="mt-4">
          <button onClick={onClose} className="px-4 py-2 bg-gray-300 rounded">Cancelar</button>
          <button onClick={handleSubmit} className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Guardar</button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}