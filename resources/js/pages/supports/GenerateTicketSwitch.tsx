import { useEffect, useState } from 'react';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import axios from 'axios';

interface Props {
  supportId?: number; // Puedes pasarlo si es necesario
}

export default function GenerateTicketSwitch({ supportId }: Props) {
  const [generateTicket, setGenerateTicket] = useState(false);

  useEffect(() => {
    if (generateTicket) {
      axios
        .post('/api/generate-ticket', { support_id: supportId }) // Ajusta a tu endpoint real
        .then(() => toast.success('🎟️ Ticket generado correctamente'))
        .catch(() => toast.error('❌ Error al generar el ticket'));
    }
  }, [generateTicket]);

  return (
    <div className="flex items-center gap-3 mt-4 border border-yellow-400 rounded-md p-2">
      <Switch
        id="generate-ticket"
        checked={generateTicket}
        onCheckedChange={setGenerateTicket}
      />
      <Label htmlFor="generate-ticket" className="text-sm font-medium text-yellow-700">
        Generar Ticket de atención
      </Label>
    </div>
  );
}
