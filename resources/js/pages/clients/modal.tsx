import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle,
} from '@/components/ui/dialog';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import axios from 'axios';
import HourglassLoader from '@/components/HourglassLoader';

interface Props {
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
  clientToEdit?: any;
}

export default function ClientModal({ open, onClose, onSaved, clientToEdit }: Props) {
  const [formData, setFormData] = useState({
    id_cliente: undefined,
    Codigo: '',
    Razon_Social: '',
    DNI: '',
    Email: '',
    Telefono: '',
    Direccion: '',
    canal: '',
    habilitado: true,
  });

  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (clientToEdit) {
      setFormData({
        id_cliente: clientToEdit.id_cliente,
        Codigo: clientToEdit.Codigo,
        Razon_Social: clientToEdit.Razon_Social,
        DNI: clientToEdit.DNI,
        Email: clientToEdit.Email,
        Telefono: clientToEdit.Telefono,
        Direccion: clientToEdit.Direccion,
        canal: clientToEdit.canal,
        habilitado: !!clientToEdit.habilitado,
      });
    } else {
      setFormData({
        id_cliente: undefined,
        Codigo: '',
        Razon_Social: '',
        DNI: '',
        Email: '',
        Telefono: '',
        Direccion: '',
        canal: '',
        habilitado: true,
      });
    }
  }, [clientToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value, type, checked } = e.target;
    setFormData({
      ...formData,
      [name]: type === 'checkbox' ? checked : value,
    });
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      if (formData.id_cliente) {
        await axios.put(`/clients/${formData.id_cliente}`, formData);
      } else {
        await axios.post('/clients', { ...formData, id_rol: 2 });
      }
      onSaved();
      onClose();
    } catch (error) {
      alert('Error al guardar');
      console.error(error);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{formData.id_cliente ? 'Editar cliente' : 'Nuevo cliente'}</DialogTitle>
        </DialogHeader>

        <div className="grid grid-cols-2 gap-4 py-4">
          <Input name="Codigo" value={formData.Codigo} onChange={handleChange} placeholder="Código" />
          <Input name="Razon_Social" value={formData.Razon_Social} onChange={handleChange} placeholder="Razón Social" />
          <Input name="DNI" value={formData.DNI} onChange={handleChange} placeholder="DNI" />
          <Input name="Email" value={formData.Email} onChange={handleChange} placeholder="Email" />
          <Input name="Telefono" value={formData.Telefono} onChange={handleChange} placeholder="Teléfono" />
          <Input name="Direccion" value={formData.Direccion} onChange={handleChange} placeholder="Dirección" />
          <Input name="canal" value={formData.canal} onChange={handleChange} placeholder="Canal" />
          <div className="flex items-center space-x-2">
            <label htmlFor="habilitado">Habilitado</label>
            <input
              type="checkbox"
              name="habilitado"
              checked={formData.habilitado}
              onChange={handleChange}
            />
          </div>
        </div>

        <DialogFooter>
          <Button onClick={handleSave} disabled={saving} className="w-full">
            {saving ? <HourglassLoader /> : 'Guardar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
