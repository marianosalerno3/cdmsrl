<?php

namespace App\Mail;

use App\Models\Carrello;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** All'agente: il carrello che ha caricato per un cliente non presente sul portale è rimasto fermo. */
class CarrelloFermoAgenteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Carrello $carrello) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Carrello in sospeso: '.$this->carrello->cliente->denominazione);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.carrello-fermo-agente', with: [
            'carrello' => $this->carrello,
            'cliente' => $this->carrello->cliente,
            'agente' => $this->carrello->agente,
            'carrelloUrl' => rtrim((string) config('portale.spa_url'), '/').'/cart?cliente='.$this->carrello->cliente_id,
        ]);
    }
}
