// resources/js/pages/roles/modal.tsx
import { useEffect, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import axios from 'axios';
import { toast } from 'sonner';
import { usePage } from '@inertiajs/react';

interface Props {
  open: boolean;
  onClose: () => void;
  onSaved: (role: any) => void;
  roleToEdit?: {
    id: number;
    name: string;
    permissions: { id: number; name: string }[];
  } | null;
}

export default function RoleModal({ open, onClose, onSaved, roleToEdit }: Props) {
  const [name, setName] = useState('');
  const [permissions, setPermissions] = useState<number[]>([]);
  const { all_permissions = [] } = usePage<{
    all_permissions: { id: number; name: string }[];
  }>().props;

  useEffect(() => {
    if (roleToEdit) {
      setName(roleToEdit.name);
      setPermissions(roleToEdit.permissions.map((p) => p.id));
    } else {
      setName('');
      setPermissions([]);
    }
  }, [roleToEdit]);

  const togglePermission = (id: number) => {
    setPermissions((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
    );
  };

  const handleSubmit = async () => {
    const payload = { name, permissions };

    try {
      const res = roleToEdit
        ? await axios.put(`/roles/${roleToEdit.id}`, payload)
        : await axios.post('/roles', payload);

      toast.success(roleToEdit ? 'Rol actualizado' : 'Rol creado');
      onSaved(res.data.role);
      onClose();
    } catch (e) {
      console.error(e);
      toast.error('Error al guardar el rol');
    }
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{roleToEdit ? 'Editar Rol' : 'Nuevo Rol'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <label className="block mb-1 font-medium">Nombre del rol</label>
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Ej: administrador"
            />
          </div>

          <div>
            <label className="block mb-1 font-medium">Permisos</label>
            <div className="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto">
              {all_permissions.map((perm) => (
                <label key={perm.id} className="flex items-center space-x-2">
                  <input
                    type="checkbox"
                    checked={permissions.includes(perm.id)}
                    onChange={() => togglePermission(perm.id)}
                  />
                  <span>{perm.name}</span>
                </label>
              ))}
            </div>
          </div>
        </div>

        <DialogFooter className="mt-6">
          <Button variant="outline" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={handleSubmit}>
            {roleToEdit ? 'Actualizar' : 'Crear'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
