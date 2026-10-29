<?php

namespace App\Http\Resources;

use App\Enums\ListinoTipo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Prodotto */
class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listino = ListinoTipo::tryFrom((string) $request->input('__listino'))
            ?? $request->user()?->listino_default
            ?? ListinoTipo::Standard;

        $collezione = $this->collezione(); // stagione + linea: decide programmato e data di partenza

        return [
            'id' => $this->id,
            'codice' => $this->codice,
            'nome' => $this->nome,
            'descrizione' => $this->descrizione,
            'composizione' => $this->composizione,
            'tessuto' => $this->tessuto,
            'pacchetto' => $this->pacchetto,
            'tipo' => $this->tipo->value,
            'categoria' => $this->categoria?->nome,
            'stagione' => $this->stagione?->codice,
            'programmata' => (bool) $collezione?->programmata,
            'data_partenza' => $collezione?->programmata ? $collezione->data_partenza?->toDateString() : null,
            'genere' => $this->genere?->nome,
            'immagini' => $this->immagini->map(fn ($i) => $i->url)->values(),
            // taglie in ordine (42 → 60, poi XS…XXL, poi taglia unica), a prescindere da come arrivano da WinMino
            'varianti' => $this->varianti
                ->sort(fn ($a, $b) => [$a->taglia?->ordine ?? 2000, (string) $a->colore?->nome]
                    <=> [$b->taglia?->ordine ?? 2000, (string) $b->colore?->nome])
                ->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'taglia' => $v->taglia?->nome,
                'taglia_ordine' => (int) ($v->taglia?->ordine ?? 2000),
                'colore' => $v->colore?->nome,
                'quantita' => (int) $v->quantita,
                'prezzo' => round((float) $v->prezzo * $listino->multiplier(), 2),
                'immagini' => $v->immagini->map(fn ($i) => $i->url)->values(),
            ])->values(),
        ];
    }
}
