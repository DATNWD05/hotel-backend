<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $pdfPath;

    public function __construct($invoice, $pdfPath)
    {
        $this->invoice = $invoice;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        return $this->subject('Hóa đơn thanh toán của bạn')
            ->markdown('emails.invoice')
            ->attach($this->pdfPath, [
                'as' => 'hoa-don-' . $this->invoice->invoice_code . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
