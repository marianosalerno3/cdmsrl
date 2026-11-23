<?php

namespace App\Mail;

use App\Models\Carrello;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Al cliente: ha un carrello fermo. Comunicazione commerciale: parte solo con il consenso
 * e contiene il link per non riceverne più.
 */
class CarrelloFermoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Carrello $carrello) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hai un carrello in sospeso');
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->urlAnnulla().'>']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.carrello-fermo', with: [
            'carrello' => $this->carrello,
            'cliente' => $this->carrello->cliente,
            'carrelloUrl' => rtrim((string) config('portale.spa_url'), '/').'/cart?cliente='.$this->carrello->cliente_id,
            'annullaUrl' => $this->urlAnnulla(),
        ]);
    }

    private function urlAnnulla(): string
    {
        return URL::signedRoute('comunicazioni.annulla', ['cliente' => $this->carrello->cliente_id]);
    }
}
