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

    public Support $support;
    public string $action;

    public function __construct(Support $support, string $action = 'created')
    {
        $this->action = $action;

        // Carga todas las relaciones necesarias
        $this->support = $support->loadMissing([
            'client:id_cliente,Razon_Social,Telefono,Email,Direccion',
            'creator:id,firstname,lastname,names,email',
            'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,comment,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id,ticket,channel',
            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
            'details.type:id,description',
            'details.lastComment.internalState:id,description',
        ]);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }
public function toMail(object $notifiable): MailMessage
{
    $firstDetail = $this->support->details->first();
    $ticketId = $firstDetail?->id ?? 0;
    $ticketFormatted = 'TR-' . str_pad($ticketId, 5, '0', STR_PAD_LEFT);

    $subjectPrefix = match ($this->action) {
        'client.created' => '📩 Se ha registrado su solicitud',
        'client.updated' => '🔄 Su solicitud ha sido actualizada',
        'updated'        => '🔄 Soporte actualizado',
        default           => '📢 Nuevo soporte registrado',
    };

    $subject = "{$subjectPrefix} [{$ticketFormatted}]";

    return (new MailMessage)
        ->subject($subject)
        ->view('emails.support_notification', [
            'support' => $this->support,
            'action' => $this->action,
            'ticketFormatted' => $ticketFormatted,
        ]);
}


}
