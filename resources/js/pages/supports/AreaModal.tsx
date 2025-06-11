import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useState } from 'react';
import { toast } from 'sonner';
import axios from 'axios';

interface AreaModalProps {
  open: boolean;
  onClose: () => void;
  supportId: number;
  areas: { id_area: number; descripcion: string }[];
  motives: { id: number; nombre_motivo: string }[];
}

export default function AreaModal({
  open,
  onClose,
  supportId,
  areas,
  motives
}: AreaModalProps) {
  const [selectedArea, setSelectedArea] = useState<number | ''>('');
  const [selectedMotive, setSelectedMotive] = useState<number | ''>('');
  const [loading, setLoading] = useState(false); // 🟡 mejora opcional

  const handleSave = async () => {
    if (!selectedArea || !selectedMotive) {
      toast.warning('Debe seleccionar un área y un motivo');
      return;
    }

    setLoading(true);
    try {
      await axios.put(`/supports/${supportId}/area-motivo`, {
        area_id: selectedArea,
        id_motivos_cita: selectedMotive,
      });
      toast.success('Actualizado correctamente ✅');
      onClose();
    } catch (error) {
      console.error(error);
      toast.error('Error al actualizar ❌');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Mantenimiento de Área y Motivo</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <Label className="block mb-1">Área</Label>
            <select
              value={selectedArea}
              onChange={(e) => setSelectedArea(Number(e.target.value))}
              className="w-full border rounded px-3 py-2 text-sm"
            >
              <option value="">Seleccione un área</option>
              {areas.map((a) => (
                <option key={a.id_area} value={a.id_area}>
                  {a.descripcion}
                </option>
              ))}
            </select>
          </div>

          <div>
            <Label className="block mb-1">Motivo de Cita</Label>
            <select
              value={selectedMotive}
              onChange={(e) => setSelectedMotive(Number(e.target.value))}
              className="w-full border rounded px-3 py-2 text-sm"
            >
              <option value="">Seleccione un motivo</option>
              {motives.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.nombre_motivo}
                </option>
              ))}
            </select>
          </div>
        </div>

        <DialogFooter>
          <Button variant="ghost" onClick={onClose}>Cancelar</Button>
          <Button onClick={handleSave} disabled={loading}>
            {loading ? 'Guardando...' : 'Guardar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
