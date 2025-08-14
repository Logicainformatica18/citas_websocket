<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function build()
    {
        return $this->subject('Confirmación de pago')
            ->view('emails.payment-notification') // crea esta vista
            ->with([
                'full_name' => $this->payment->full_name,
                'amount'    => $this->payment->amount,
                'receipt'   => $this->payment->receipt_number,
                'mz_lote'   => $this->payment->mz_lote,
            ]);
    }
}
