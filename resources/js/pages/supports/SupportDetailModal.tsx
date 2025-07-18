import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

interface Props {
  open: boolean;
  onClose: () => void;
  support: any;
}

export default function SupportDetailModal({ open, onClose, support }: Props) {
  if (!support) return null;
  const detail = support.details?.[0];

  const formatDateTime = (dateStr?: string) => {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleString('es-PE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    }).replace(',', '');
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="text-lg">🧾 Detalles de la Solicitud</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 text-sm text-gray-700">
          <div className="flex flex-col gap-1">
            <span className="text-xs font-semibold text-gray-500 uppercase">Registrado por</span>
            <span>{support.creator?.names ?? '—'}</span>
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-xs font-semibold text-gray-500 uppercase">Canal</span>
            <span>{detail?.channel ?? '—'}</span>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <span className="text-xs font-semibold text-gray-500 uppercase">Estado del Área</span>
              <p>{detail?.last_comment?.internal_state?.description ?? 'Sin seguimiento'}</p>
            </div>
            <div>
              <span className="text-xs font-semibold text-gray-500 uppercase">Estado Interno</span>
              <p>{detail?.internal_state?.description ?? '—'}</p>
            </div>
            <div>
              <span className="text-xs font-semibold text-gray-500 uppercase">Estado Externo</span>
              <p>{detail?.external_state?.description ?? '—'}</p>
            </div>
            <div>
              <span className="text-xs font-semibold text-gray-500 uppercase">Prioridad</span>
              <p>{detail?.priority ?? '—'}</p>
            </div>
            <div>
              <span className="text-xs font-semibold text-gray-500 uppercase">Fecha de creación</span>
              <p>{formatDateTime(support.created_at)}</p>
            </div>
          </div>
        </div>

        <DialogFooter className="pt-4">
          <Button variant="secondary" onClick={onClose}>Cerrar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
