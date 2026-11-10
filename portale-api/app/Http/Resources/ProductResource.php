<?php

namespace App\Http\Resources;

use App\Enums\ListinoTipo;
use App\Models\Collezione;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Prodotto */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listino = ListinoTipo::tryFrom((string) $request->input('__listino'))
            ?? $request->user()?->listino_default
            ?? ListinoTipo::Standard;

        $prezzi = $this->varianti->map(fn ($v) => round((float) $v->prezzo * $listino->multiplier(), 2));

        // collezioni programmate lette una volta per richiesta (l'elenco ha fino a 24 prodotti)
        $programmate = $request->attributes->get('collezioni_programmate');
        if ($programmate === null) {
            $programmate = Collezione::where('programmata', true)->get()
                ->mapWithKeys(fn ($c) => [$c->stagione_id.'|'.$c->linea => true])->all();
            $request->attributes->set('collezioni_programmate', $programmate);
        }

        return [
            'id' => $this->id,
            'codice' => $this->codice,
            'nome' => $this->nome,
            'linea' => $this->linea?->value,
            'categoria' => $this->categoria?->nome,
            'stagione' => $this->stagione?->codice,
            'tipo' => $this->tipo->value,
            'programmata' => isset($programmate[$this->stagione_id.'|'.$this->linea?->value]),
            'giacenza_totale' => (int) $this->varianti->sum('quantita'),
            'prezzo_min' => $prezzi->min(),
            'prezzo_max' => $prezzi->max(),
            'immagine' => $this->immagini->first()?->url,
        ];
    }
}
