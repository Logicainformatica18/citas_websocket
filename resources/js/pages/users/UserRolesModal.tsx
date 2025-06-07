import { useEffect, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

export default function UserRolesModal({
  open,
  onClose,
  userId,
  currentRoles,
  availableRoles,
  onUpdated,
}: {
  open: boolean;
  onClose: () => void;
  userId: number;
  currentRoles: string[];
  availableRoles: { name: string }[];
  onUpdated: (updatedRoles: string[]) => void;
}) {
  const [selectedRoles, setSelectedRoles] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setSelectedRoles(currentRoles);
  }, [currentRoles]);

  const handleToggleRole = (role: string) => {
    setSelectedRoles((prev) =>
      prev.includes(role) ? prev.filter((r) => r !== role) : [...prev, role]
    );
  };

  const handleSave = async () => {
    setLoading(true);
    try {
      await axios.put(`/users/${userId}/sync-roles`, {
        roles: selectedRoles,
      });

      toast.success('Roles actualizados correctamente ✅');
      onUpdated(selectedRoles);
      onClose();
    } catch (error) {
      console.error('Error al sincronizar roles:', error);
      toast.error('Error al sincronizar roles');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Asignar Roles</DialogTitle>
        </DialogHeader>

        <div className="grid gap-4 py-4">
          {availableRoles.map((role) => (
            <label key={role.name} className="flex items-center gap-3">
              <input
                type="checkbox"
                checked={selectedRoles.includes(role.name)}
                onChange={() => handleToggleRole(role.name)}
              />
              <span className="capitalize text-sm">{role.name}</span>
            </label>
          ))}
        </div>

        <DialogFooter className="flex justify-between">
          <Button variant="ghost" onClick={onClose} disabled={loading}>
            Cancelar
          </Button>
          <Button onClick={handleSave} disabled={loading}>
            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Guardar Cambios
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
