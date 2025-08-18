// ✅ motives/modal.tsx (con campos detail y detail_2 + checkboxes de áreas)
import { useEffect, useMemo, useState } from 'react';
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

type AreaMini = { id_area: number; descripcion: string };
type ItemToEdit = {
  id_motivos_cita: number;
  nombre_motivo: string;
  detail?: string | null;
  detail_2?: string | null;      // ← NUEVO
  id_tipo_cita: number | null;
  id_dia_espera: number | null;
  id_area: number | '';
  habilitado: boolean;
  areas_ids?: number[];
  areas_pivot?: AreaMini[];
};

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
  itemToEdit?: ItemToEdit | null;
  appointmentTypes: { id_tipo_cita: number; tipo: string }[];
  waitingDays: { id_dias_espera: number; dias: string }[];
  areas: AreaMini[];
}) {
  const [formData, setFormData] = useState({
    nombre_motivo: '',
    detail: '' as string | null,
    detail_2: '' as string | null,         // ← NUEVO
    id_tipo_cita: '' as number | '' | null,
    id_dia_espera: '' as number | '' | null,
    id_area: '' as number | '' | null,     // área principal
    habilitado: true,
  });

  // Áreas N:M (checkboxes)
  const [areasSelected, setAreasSelected] = useState<number[]>([]);
  const [saving, setSaving] = useState(false);

  // “Seleccionar todo”
  const allAreaIds = useMemo(() => areas.map(a => a.id_area), [areas]);
  const allChecked = areasSelected.length > 0 && areasSelected.length === allAreaIds.length;

  useEffect(() => {
    if (itemToEdit) {
      setFormData({
        nombre_motivo: itemToEdit.nombre_motivo ?? '',
        detail: itemToEdit.detail ?? '',
        detail_2: itemToEdit.detail_2 ?? '',   // ← NUEVO
        id_tipo_cita: itemToEdit.id_tipo_cita ?? '',
        id_dia_espera: itemToEdit.id_dia_espera ?? '',
        id_area: itemToEdit.id_area ?? '',
        habilitado: !!itemToEdit.habilitado,
      });

      const fromIds = Array.isArray(itemToEdit.areas_ids)
        ? itemToEdit.areas_ids
        : (itemToEdit.areas_pivot?.map(a => a.id_area) ?? []);
      setAreasSelected(fromIds);
    } else {
      handleClear();
    }
  }, [itemToEdit]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]:
        type === 'checkbox'
          ? checked
          : (name === 'detail' || name === 'detail_2')
          ? value // texto libre
          : (value === '' ? '' : Number(value)),
    }));
  };

  const toggleArea = (id: number, checked: boolean) => {
    setAreasSelected(prev =>
      checked ? Array.from(new Set([...prev, id])) : prev.filter(x => x !== id)
    );
  };

  const toggleAllAreas = () => {
    setAreasSelected(allChecked ? [] : allAreaIds);
  };

  const handleSubmit = async () => {
    if (!formData.id_area) {
      toast.error('Selecciona el área principal.');
      return;
    }

    try {
      setSaving(true);
      const url = itemToEdit ? `/motives/${itemToEdit.id_motivos_cita}` : '/motives';
      const method = itemToEdit ? 'put' : 'post';

      // Payload con detail y detail_2 (si están vacíos, van como null)
      const payload = {
        nombre_motivo: formData.nombre_motivo,
        detail: (formData.detail ?? '').toString().trim() || null,
        detail_2: (formData.detail_2 ?? '').toString().trim() || null, // ← NUEVO
        id_tipo_cita: formData.id_tipo_cita || null,
        id_dia_espera: formData.id_dia_espera || null,
        id_area: formData.id_area,
        habilitado: !!formData.habilitado,
        areas_ids: (() => {
          const principal = typeof formData.id_area === 'number' ? formData.id_area : null;
          const set = new Set<number>(areasSelected);
          if (principal && !set.has(principal)) set.add(principal);
          return Array.from(set);
        })(),
      };

      const response = await (axios as any)[method](url, payload);
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

  const handleClear = () => {
    setFormData({
      nombre_motivo: '',
      detail: '',
      detail_2: '',   // ← NUEVO
      id_tipo_cita: '',
      id_dia_espera: '',
      id_area: '',
      habilitado: true,
    });
    setAreasSelected([]);
  };

  return (
    <Dialog open={open} onOpenChange={(val) => !val && onClose()}>
      <DialogContent className="sm:max-w-2xl">
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

          {/* Detalle 1 (opcional) */}
          <div className="grid grid-cols-4 items-start gap-4">
            <Label htmlFor="detail" className="text-right pt-1">Detalle</Label>
            <textarea
              id="detail"
              name="detail"
              rows={3}
              value={formData.detail ?? ''}
              onChange={handleChange}
              className="col-span-3 border rounded px-2 py-1"
              placeholder="Descripción adicional del motivo (opcional)"
            />
          </div>

          {/* Detalle 2 (opcional) */}
          <div className="grid grid-cols-4 items-start gap-4">
            <Label htmlFor="detail_2" className="text-right pt-1">Detalle 2</Label>
            <textarea
              id="detail_2"
              name="detail_2"
              rows={3}
              value={formData.detail_2 ?? ''}
              onChange={handleChange}
              className="col-span-3 border rounded px-2 py-1"
              placeholder="Información complementaria (opcional)"
            />
          </div>

          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="id_tipo_cita" className="text-right">Tipo de Cita</Label>
            <select
              name="id_tipo_cita"
              value={String(formData.id_tipo_cita ?? '')}
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
              value={String(formData.id_dia_espera ?? '')}
              onChange={handleChange}
              className="col-span-3 border rounded px-2 py-1"
            >
              <option value="">-- Seleccionar --</option>
              {waitingDays.map((item) => (
                <option key={item.id_dias_espera} value={item.id_dias_espera}>{item.dias}</option>
              ))}
            </select>
          </div>

          {/* Área principal (1:N) */}
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="id_area" className="text-right">Área principal</Label>
            <select
              name="id_area"
              value={String(formData.id_area ?? '')}
              onChange={handleChange}
              className="col-span-3 border rounded px-2 py-1"
            >
              <option value="">-- Seleccionar --</option>
              {areas.map((item) => (
                <option key={item.id_area} value={item.id_area}>{item.descripcion}</option>
              ))}
            </select>
          </div>

          {/* Áreas N:M (checkboxes) */}
          <div className="grid grid-cols-4 items-start gap-4">
            <Label className="text-right pt-1">Áreas (múltiples)</Label>
            <div className="col-span-3 border rounded p-2 max-h-56 overflow-auto space-y-1">
              <div className="flex items-center gap-2 mb-2">
                <input
                  id="check_all_areas"
                  type="checkbox"
                  checked={allChecked}
                  onChange={toggleAllAreas}
                  className="h-4 w-4"
                />
                <Label htmlFor="check_all_areas" className="cursor-pointer">Seleccionar todo</Label>
                <span className="text-xs text-gray-500">({areasSelected.length} seleccionado(s))</span>
              </div>

              {areas.map((a) => {
                const checked = areasSelected.includes(a.id_area);
                return (
                  <label key={a.id_area} className="flex items-center gap-2 text-sm cursor-pointer">
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={(e) => toggleArea(a.id_area, e.target.checked)}
                      className="h-4 w-4"
                    />
                    <span>{a.descripcion}</span>
                  </label>
                );
              })}
            </div>
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
          <Button variant="outline" onClick={handleClear} disabled={saving}>
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
