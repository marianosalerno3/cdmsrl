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
        $listino = ListinoTipo::tryFrom((string) $this->additional['listino'] ?? '')
            ?? $request->user()?->listino_default
            ?? ListinoTipo::Standard;

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
            'genere' => $this->genere?->nome,
            'immagini' => $this->immagini->map(fn ($i) => $i->url)->values(),
            'varianti' => $this->varianti->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'taglia' => $v->taglia?->nome,
                'colore' => $v->colore?->nome,
                'quantita' => (int) $v->quantita,
                'prezzo' => round((float) $v->prezzo * $listino->multiplier(), 2),
                'immagini' => $v->immagini->map(fn ($i) => $i->url)->values(),
            ])->values(),
        ];
    }
}
