// resources/js/pages/transfers/notify.tsx
import axios from 'axios';
import { toast } from 'sonner';

export async function notifyTransfer(transferId: number) {
  try {
    const response = await axios.post(`/transfers/${transferId}/notify`);
    toast.success('📢 Notificación enviada correctamente');
    return response.data;
  } catch (error) {
    console.error('❌ Error al notificar:', error);
    toast.error('No se pudo enviar la notificación');
    throw error;
  }
}
