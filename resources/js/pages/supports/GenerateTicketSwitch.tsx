import { useEffect, useState } from 'react';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import axios from 'axios';

interface Props {
  supportId?: number;
  ticket?: string;
 externalStateDescription?: string; // 👈 Aquí ahora es el texto, no el ID
}

export default function GenerateTicketSwitch({ supportId, ticket, externalStateDescription }: Props) {
  const isAlreadyGenerated = !!ticket && /^TK-\d{1,}$/.test(ticket);
  const [generateTicket, setGenerateTicket] = useState(isAlreadyGenerated);
  const [disabled, setDisabled] = useState(isAlreadyGenerated);
  const [isLoading, setIsLoading] = useState(false);

  const handleToggle = async () => {
    if (disabled || isLoading) return;

    // 🚫 Bloquear si el estado externo es "Por asignar"
    if (externalStateDescription === "Por Asignar") {
      toast.warning('⚠️ No se puede generar el ticket mientras el estado esté "Por asignar".');
      return;
    }

    try {
      setIsLoading(true);
      await axios.post('generate_ticket', { support_id: supportId });
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
    <div className="flex items-center   border border-yellow-400 rounded-md px-1 py-1">
      <Switch
        id={`generate-ticket-${supportId}`}
        checked={generateTicket}
        onCheckedChange={handleToggle}
        disabled={disabled}
        className={disabled ? 'opacity-60 cursor-not-allowed' : ''}
      />
     {/* <Label
  htmlFor={`generate-ticket-${supportId}`}
  className={`text-sm font-medium ${
    generateTicket ? 'text-green-700' : 'text-red-700'
  }`}
>
  {generateTicket ? '' : ''}
</Label> */}
    </div>
  );
}
