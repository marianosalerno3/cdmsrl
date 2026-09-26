<?php

namespace App\Mail;

use App\Models\OrdineB2B;
use App\Services\OrderDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al backoffice CDM di un nuovo ordine raccolto dal portale.
 * Il commerciale prende in carico l'ordine e lo carica manualmente su WinMino.
 */
class NuovoOrdineBackofficeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public OrdineB2B $ordine) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nuovo ordine {$this->ordine->numero} — {$this->ordine->cliente_nome}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.nuovo-ordine-backoffice', with: [
            'ordine' => $this->ordine->loadMissing('agente', 'righe'),
            'panelUrl' => rtrim((string) config('app.url'), '/').'/'.config('portale.admin_path', 'access')
                .'/ordine-b2-bs/'.$this->ordine->getKey(),
        ]);
    }

    public function attachments(): array
    {
        $doc = app(OrderDocumentService::class);

        return [
            Attachment::fromData(fn () => $doc->pdf($this->ordine)->output(), $doc->filename($this->ordine))
                ->withMime('application/pdf'),
        ];
    }
}
