<?php

namespace App\Mail;

use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Al cliente: la registrazione è stata approvata, può accedere al portale. */
class ClienteApprovatoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Cliente $cliente) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Il tuo accesso al portale B2B è attivo');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.cliente-approvato', with: [
            'cliente' => $this->cliente,
            'portaleUrl' => rtrim((string) config('portale.spa_url'), '/').'/login',
        ]);
    }
}
