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
            'creator:id,names,email',
            'client:id_cliente,Razon_Social',
            'details',
            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,motivo_cita',
            'details.tipoCita:id_tipo_cita,tipo_cita',
            'details.diaEspera:id_dias_espera,dia_espera',
            'details.internalState:id,internal_state',
            'details.externalState:id,external_state',
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
