<?php

namespace App\Mail;

use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Avviso al backoffice: un nuovo cliente B2B si è registrato e attende l'approvazione. */
class NuovaRegistrazioneB2BMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Cliente $cliente) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nuova registrazione B2B da approvare — {$this->cliente->ragione_sociale}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.nuova-registrazione-b2b', with: [
            'cliente' => $this->cliente,
            'panelUrl' => rtrim((string) config('app.url'), '/').'/'.config('portale.admin_path', 'access').'/clientes',
        ]);
    }
}
