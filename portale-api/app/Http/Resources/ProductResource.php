<?php

namespace App\Http\Resources;

use App\Enums\ListinoTipo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Prodotto */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listino = ListinoTipo::tryFrom($request->string('customer_listino')->toString())
            ?? $request->user()?->listino_default
            ?? ListinoTipo::Standard;

        $prezzi = $this->varianti->map(fn ($v) => round((float) $v->prezzo * $listino->multiplier(), 2));

        return [
            'id' => $this->id,
            'codice' => $this->codice,
            'nome' => $this->nome,
            'categoria' => $this->categoria?->nome,
            'stagione' => $this->stagione?->codice,
            'tipo' => $this->tipo->value,
            'giacenza_totale' => $this->giacenza_totale,
            'prezzo_min' => $prezzi->min(),
            'prezzo_max' => $prezzi->max(),
            'immagine' => $this->immagini->first()?->url,
        ];
    }
}
