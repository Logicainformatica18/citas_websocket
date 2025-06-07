<?php

namespace App\Mail;

use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransferNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Transfer $transfer;

    public function __construct(Transfer $transfer)
    {
        $this->transfer = $transfer;
    }

    public function build()
    {
        return $this->subject('📢 Nueva Transferencia Registrada')
                    ->markdown('emails.transfer');
    }
}
