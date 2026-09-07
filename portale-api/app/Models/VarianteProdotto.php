<?php

namespace App\Models;

use App\Services\PriceService;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VarianteProdotto extends Model
{
    use HasVersion7Uuids;

    protected $table = 'variante_prodotti';

    protected $fillable = [
        'prodotto_id', 'taglia_id', 'colore_id', 'sku', 'prezzo', 'quantita',
        'barcode', 'shopify_variant_id', 'woocommerce_variation_id',
    ];

    protected function casts(): array
    {
        return [
            'prezzo' => 'decimal:2',
            'quantita' => 'integer',
        ];
    }

    public function prodotto(): BelongsTo
    {
        return $this->belongsTo(Prodotto::class);
    }

    public function taglia(): BelongsTo
    {
        return $this->belongsTo(Taglia::class);
    }

    public function colore(): BelongsTo
    {
        return $this->belongsTo(Colore::class);
    }

    public function immagini(): HasMany
    {
        return $this->hasMany(VarianteImmagine::class)->orderBy('ordine');
    }

    /** Prezzo per uno specifico listino (App\Enums\ListinoTipo). */
    public function prezzoPerListino(\App\Enums\ListinoTipo $listino): float
    {
        return app(PriceService::class)->forVariant($this, $listino);
    }
}
