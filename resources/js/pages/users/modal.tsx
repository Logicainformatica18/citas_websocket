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

export default function UserModal({
  open,
  onClose,
  onSaved,
  userToEdit,
  availableRoles,
}: {
  open: boolean;
  onClose: () => void;
  onSaved: (user: any) => void;
  userToEdit?: any;
  availableRoles: { name: string }[];
}) {
  const [formData, setFormData] = useState({
    firstname: '',
    lastname: '',
    names: '',
    email: '',
    dni: '',
    password: '',
    cellphone: '',
    sex: '',
    datebirth: '',
    role: '',
  });

  const [photo, setPhoto] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    if (userToEdit) {
      setFormData({
        firstname: userToEdit.firstname || '',
        lastname: userToEdit.lastname || '',
        names: userToEdit.names || '',
        email: userToEdit.email || '',
        dni: userToEdit.dni || '',
        password: '',
        cellphone: userToEdit.cellphone || '',
        sex: userToEdit.sex || '',
        datebirth: userToEdit.datebirth || '',
        role: userToEdit.roles?.[0]?.name || '',
      });
      if (userToEdit.photo) setPreview(`/imageusers/${userToEdit.photo}`);
    } else {
      handleReset();
    }
  }, [userToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
  };

  const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] || null;
    setPhoto(file);
    if (file && file.type.startsWith('image')) {
      setPreview(URL.createObjectURL(file));
    } else {
      setPreview(null);
    }
  };

  const handleSubmit = async () => {
    try {
      setUploading(true);

      const data = new FormData();
      Object.entries(formData).forEach(([key, value]) => {
        data.append(key, value);
      });

      if (photo) {
        data.append('photo', photo);
      }

      const url = userToEdit ? `/users/${userToEdit.id}` : '/users';
      if (userToEdit) {
        data.append('_method', 'PUT');
      }

      const res = await axios.post(url, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      toast.success(userToEdit ? 'Usuario actualizado ✅' : 'Usuario creado ✅');
      onSaved(res.data.user);
      handleReset();
      onClose();
    } catch (error) {
      console.error('Error al guardar usuario:', error);
      toast.error('Hubo un error al guardar');
    } finally {
      setUploading(false);
    }
  };

  const handleReset = () => {
    setFormData({
      firstname: '',
      lastname: '',
      names: '',
      email: '',
      dni: '',
      password: '',
      cellphone: '',
      sex: '',
      datebirth: '',
      role: '',
    });
    setPhoto(null);
    setPreview(null);
  };

  const fieldLabels: Record<string, string> = {
    firstname: 'Nombre',
    lastname: 'Apellido',
    names: 'Nombre completo',
    email: 'Correo',
    dni: 'DNI',
    password: 'Contraseña',
    cellphone: 'Celular',
    datebirth: 'Fecha de nacimiento',
  };

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{userToEdit ? 'Editar Usuario' : 'Nuevo Usuario'}</DialogTitle>
        </DialogHeader>

        <div className="grid gap-4 py-4">
          {Object.keys(fieldLabels).map((field) => (
            <div key={field} className="grid grid-cols-4 items-center gap-4">
              <Label htmlFor={field} className="text-right">{fieldLabels[field]}</Label>
              <Input
                type={field === 'password' ? 'password' : field === 'datebirth' ? 'date' : 'text'}
                id={field}
                name={field}
                value={(formData as any)[field]}
                onChange={handleChange}
                className="col-span-3"
              />
            </div>
          ))}

          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="sex" className="text-right">Sexo</Label>
            <select
              id="sex"
              name="sex"
              value={formData.sex}
              onChange={handleChange}
              className="col-span-3 border rounded px-3 py-2 text-sm"
            >
              <option value="">Selecciona</option>
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
            </select>
          </div>

          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="role" className="text-right">Rol</Label>
            <select
              id="role"
              name="role"
              value={formData.role}
              onChange={handleChange}
              className="col-span-3 border rounded px-3 py-2 text-sm"
            >
              <option value="">Selecciona</option>
              {availableRoles.map((r) => (
                <option key={r.name} value={r.name}>{r.name}</option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-4 items-start gap-4">
            <Label htmlFor="photo" className="text-right">Foto</Label>
            <div className="col-span-3">
              <Input type="file" id="photo" name="photo" accept="image/*" onChange={handlePhotoChange} />
              {preview && <img src={preview} className="mt-2 w-20 h-20 rounded-full object-cover" alt="preview" />}
            </div>
          </div>
        </div>

        <DialogFooter className="flex justify-between">
          <div className="flex gap-2">
            <Button variant="outline" onClick={handleReset} disabled={uploading}>Nuevo</Button>
            <Button onClick={handleSubmit} disabled={uploading}>
              {uploading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {userToEdit ? 'Actualizar' : 'Guardar'}
            </Button>
          </div>
          <Button variant="ghost" onClick={onClose} disabled={uploading}>Cerrar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
