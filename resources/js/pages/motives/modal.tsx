// ✅ motives/modal.tsx
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

export default function MotiveModal({
  open,
  onClose,
  onSaved,
  itemToEdit,
  appointmentTypes,
  waitingDays,
  areas,
}: {
  open: boolean;
  onClose: () => void;
  onSaved: (item: any) => void;
  itemToEdit?: any;
  appointmentTypes: { id_tipo_cita: number; tipo: string }[];
  waitingDays: { id_dias_espera: number; dias: string }[];
  areas: { id_area: number; descripcion: string }[];
}) {
  const [formData, setFormData] = useState({
    nombre_motivo: '',
    id_tipo_cita: '',
    id_dia_espera: '',
    id_area: '',
    habilitado: true,
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (itemToEdit) {
      setFormData({
        nombre_motivo: itemToEdit.nombre_motivo || '',
        id_tipo_cita: itemToEdit.id_tipo_cita || '',
        id_dia_espera: itemToEdit.id_dia_espera || '',
        id_area: itemToEdit.id_area || '',
        habilitado: itemToEdit.habilitado || false,
      });
    } else {
      setFormData({
        nombre_motivo: '',
        id_tipo_cita: '',
        id_dia_espera: '',
        id_area: '',
        habilitado: true,
      });
    }
  }, [itemToEdit]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
  ) => {
    const { name, value, type, checked } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleSubmit = async () => {
    try {
      setSaving(true);
      const url = itemToEdit ? `/motives/${itemToEdit.id_motivos_cita}` : '/motives';
      const method = itemToEdit ? 'put' : 'post';

      const payload = {
        ...formData,
        id_tipo_cita: formData.id_tipo_cita || null,
        id_dia_espera: formData.id_dia_espera || null,
      };

      const response = await axios[method](url, payload);
      toast.success(itemToEdit ? 'Actualizado ✅' : 'Creado ✅');
      onSaved(response.data.motive);
      onClose();
    } catch (err) {
      console.error(err);
      toast.error('Error al guardar');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(val) => !val && onClose()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{itemToEdit ? 'Editar Motivo' : 'Nuevo Motivo'}</DialogTitle>
        </DialogHeader>

        <div className="grid gap-4 py-4">
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="nombre_motivo" className="text-right">Motivo</Label>
            <Input
              id="nombre_motivo"
              name="nombre_motivo"
              value={formData.nombre_motivo}
              onChange={handleChange}
              className="col-span-3"
            />
          </div>

          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="id_tipo_cita" className="text-right">Tipo de Cita</Label>
            <select
              name="id_tipo_cita"
              value={formData.id_tipo_cita}
              onChange={handleChange}
              className="col-span-3 border rounded px-2 py-1"
            >
              <option value="">-- Seleccionar --</option>
              {appointmentTypes.map((item) => (
                <option key={item.id_tipo_cita} value={item.id_tipo_cita}>{item.tipo}</option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="id_dia_espera" className="text-right">Día Espera</Label>
            <select
              name="id_dia_espera"
              value={formData.id_dia_espera}
              onChange={handleChange}
              className="col-span-3 border rounded px-2 py-1"
            >
              <option value="">-- Seleccionar --</option>
              {waitingDays.map((item) => (
                <option key={item.id_dias_espera} value={item.id_dias_espera}>{item.dias}</option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="id_area" className="text-right">Área</Label>
            <select
              name="id_area"
              value={formData.id_area}
              onChange={handleChange}
              className="col-span-3 border rounded px-2 py-1"
            >
              <option value="">-- Seleccionar --</option>
              {areas.map((item) => (
                <option key={item.id_area} value={item.id_area}>{item.descripcion}</option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="habilitado" className="text-right">¿Habilitado?</Label>
            <input
              type="checkbox"
              id="habilitado"
              name="habilitado"
              checked={formData.habilitado}
              onChange={handleChange}
              className="h-5 w-5 col-span-3"
            />
          </div>
        </div>

        <DialogFooter className="flex justify-between">
          <Button variant="outline" onClick={() => setFormData({ nombre_motivo: '', id_tipo_cita: '', id_dia_espera: '', id_area: '', habilitado: true })} disabled={saving}>Limpiar</Button>
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
