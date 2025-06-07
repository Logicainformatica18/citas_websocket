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

export default function AreaModal({
  open,
  onClose,
  onSaved,
  areaToEdit,
}: {
  open: boolean;
  onClose: () => void;
  onSaved: (area: any) => void;
  areaToEdit?: any;
}) {
  const [formData, setFormData] = useState({
    descripcion: '',
    habilitado: true,
  });

  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (areaToEdit) {
      setFormData({
        descripcion: areaToEdit.descripcion || '',
        habilitado: areaToEdit.habilitado ?? true,
      });
    } else {
      setFormData({ descripcion: '', habilitado: true });
    }
  }, [areaToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value, type, checked } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleSubmit = async () => {
    try {
      setSaving(true);
      const url = areaToEdit ? `/areas/${areaToEdit.id_area}` : '/areas';
      const method = areaToEdit ? 'put' : 'post';

      const response = await axios[method](url, formData);

      toast.success(areaToEdit ? 'Área actualizada ✅' : 'Área creada ✅');
      onSaved(response.data.area);
      onClose();
    } catch (error) {
      console.error('❌ Error al guardar:', error);
      toast.error('Error al guardar el área');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(val) => !val && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{areaToEdit ? 'Editar Área' : 'Nueva Área'}</DialogTitle>
        </DialogHeader>

        <div className="grid gap-4 py-4">
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="descripcion" className="text-right">
              Descripción
            </Label>
            <Input
              id="descripcion"
              name="descripcion"
              value={formData.descripcion}
              onChange={handleChange}
              className="col-span-3"
            />
          </div>

          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="habilitado" className="text-right">
              Habilitado
            </Label>
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
          <Button variant="outline" onClick={() => setFormData({ descripcion: '', habilitado: true })} disabled={saving}>
            Nuevo
          </Button>
          <div className="flex gap-2">
            <Button onClick={handleSubmit} disabled={saving}>
              {saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {areaToEdit ? 'Actualizar' : 'Guardar'}
            </Button>
            <Button variant="ghost" onClick={onClose} disabled={saving}>
              Cerrar
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
