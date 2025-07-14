import { useEffect, useState } from 'react';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import axios from 'axios';

interface Props {
  supportId?: number;
  ticket?: string; // <- Pasamos el ticket real aquí
}

export default function GenerateTicketSwitch({ supportId, ticket }: Props) {
  // ✅ El ticket es válido si empieza con TK- y tiene más de 3 caracteres
  const isAlreadyGenerated = !!ticket && /^TK-\d{1,}$/.test(ticket);
  const [generateTicket, setGenerateTicket] = useState(isAlreadyGenerated);
  const [disabled, setDisabled] = useState(isAlreadyGenerated);
  const [isLoading, setIsLoading] = useState(false);

  const handleToggle = async () => {
    if (disabled || isLoading) return;

    try {
      setIsLoading(true);
      await axios.post('support-details/generate-ticket', { support_id: supportId });
      setGenerateTicket(true);
      setDisabled(true);
      toast.success('🎟️ Ticket generado correctamente');
    } catch {
      toast.error('❌ Error al generar el ticket');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex items-center gap-3 border border-yellow-400 rounded-md px-3 py-2">
      <Switch
        id={`generate-ticket-${supportId}`}
        checked={generateTicket}
        onCheckedChange={handleToggle}
        disabled={disabled}
        className={disabled ? 'opacity-60 cursor-not-allowed' : ''}
      />
      <Label
        htmlFor={`generate-ticket-${supportId}`}
        className="text-sm font-medium text-yellow-700"
      >
        {generateTicket ? '' : ''}
      </Label>
    </div>
  );
}
