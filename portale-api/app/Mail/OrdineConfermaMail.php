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

class OrdineConfermaMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public OrdineB2B $ordine) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Conferma ordine {$this->ordine->numero}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.ordine-conferma', with: [
            'ordine' => $this->ordine,
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
