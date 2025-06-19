<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Support;

class NewSupportAtcNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $support;
    public string $action;

    public function __construct(Support $support, string $action = 'created')
    {
        $this->action = $action;

        // 🔄 Asegura que todas las relaciones necesarias estén disponibles
        $this->support = $support->loadMissing([
             'client:id_cliente,Razon_Social,Telefono,Email,Direccion',
    'creator:id,firstname,lastname,names,email',
    'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,Lote,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id',
    'details.area:id_area,descripcion',
    'details.project:id_proyecto,descripcion',
    'details.motivoCita:id_motivos_cita,nombre_motivo',
    'details.tipoCita:id_tipo_cita,tipo',
    'details.diaEspera:id_dias_espera,dias',
    'details.internalState:id,description',
    'details.externalState:id,description',
    'details.supportType:id,description',
        ]);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(($this->action === 'updated' ? '🔄' : '📢') . ' Soporte ' . ($this->action === 'updated' ? 'actualizado' : 'registrado'))
            ->view('emails.support_created', [
                'support' => $this->support,
                'action' => $this->action,
            ]);
    }
}
