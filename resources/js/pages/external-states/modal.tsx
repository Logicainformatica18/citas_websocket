// ✅ ExternalStateModal.tsx traducido al español
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

export default function ExternalStateModal({
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
    description: '',
    detail: '',
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (itemToEdit) {
      setFormData({
        description: itemToEdit.description || '',
        detail: itemToEdit.detail || '',
      });
    } else {
      setFormData({ description: '', detail: '' });
    }
  }, [itemToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async () => {
    try {
      setSaving(true);
      const url = itemToEdit ? `/external-states/${itemToEdit.id}` : '/external-states';
      const method = itemToEdit ? 'put' : 'post';
      const response = await axios[method](url, formData);

      toast.success(itemToEdit ? 'Estado externo actualizado ✅' : 'Estado externo creado ✅');
      onSaved(response.data.externalState);
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
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{itemToEdit ? 'Editar Estado Externo' : 'Nuevo Estado Externo'}</DialogTitle>
        </DialogHeader>

        <div className="grid gap-4 py-4">
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="description" className="text-right">Descripción</Label>
            <Input id="description" name="description" value={formData.description} onChange={handleChange} className="col-span-3" />
          </div>
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="detail" className="text-right">Detalle</Label>
            <textarea id="detail" name="detail" value={formData.detail} onChange={handleChange} className="col-span-3 border rounded px-3 py-2 text-sm" />
          </div>
        </div>

        <DialogFooter className="flex justify-between">
          <Button variant="outline" onClick={() => setFormData({ description: '', detail: '' })} disabled={saving}>Limpiar</Button>
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
