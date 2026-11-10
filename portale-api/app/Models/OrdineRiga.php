<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdineRiga extends Model
{
    use HasVersion7Uuids;

    protected $table = 'ordine_righe';

    protected $fillable = [
        'ordine_b2b_id', 'variante_prodotto_id',
        'prodotto_codice', 'prodotto_nome', 'taglia', 'colore', 'sku',
        'quantita', 'prezzo_unitario', 'totale_riga',
    ];

    protected function casts(): array
    {
        return [
            'quantita' => 'integer',
            'prezzo_unitario' => 'decimal:2',
            'totale_riga' => 'decimal:2',
        ];
    }

    public function ordine(): BelongsTo
    {
        return $this->belongsTo(OrdineB2B::class, 'ordine_b2b_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteProdotto::class, 'variante_prodotto_id');
    }
}
