<?php

namespace App\Services;

use App\Models\OrdineB2B;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;

class OrderDocumentService
{
    public function pdf(OrdineB2B $ordine): DomPDF
    {
        $ordine->loadMissing('righe', 'cliente', 'agente');

        return Pdf::loadView('pdf.order', ['ordine' => $ordine])->setPaper('a4');
    }

    public function filename(OrdineB2B $ordine): string
    {
        return "{$ordine->numero}.pdf";
    }
}
