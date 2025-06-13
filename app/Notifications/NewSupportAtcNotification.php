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
    public function __construct(Support $support,string $action = 'created')
    {
        $this->support = $support;

    //      $this->support = $support->loadMissing([
    //     'creator:id,names,email',
    //     'client:id_cliente,Razon_Social',
    //     'area:id_area,descripcion',
    //     'project:id_proyecto,descripcion'
    // ]);
     $this->action = $action;
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
