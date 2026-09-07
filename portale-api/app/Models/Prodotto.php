<?php

namespace App\Models;

use App\Enums\ProdottoTipo;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prodotto extends Model
{
    use HasVersion7Uuids;
    use SoftDeletes;

    protected $table = 'prodotti';

    protected $fillable = [
        'codice', 'nome', 'descrizione', 'composizione', 'tessuto', 'pacchetto',
        'tipo', 'categoria_id', 'stagione_id', 'genere_id',
        'prezzo_base', 'attivo', 'shopify_product_id', 'woocommerce_product_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => ProdottoTipo::class,
            'prezzo_base' => 'decimal:2',
            'attivo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function stagione(): BelongsTo
    {
        return $this->belongsTo(Stagione::class);
    }

    public function genere(): BelongsTo
    {
        return $this->belongsTo(Genere::class);
    }

    public function varianti(): HasMany
    {
        return $this->hasMany(VarianteProdotto::class);
    }

    public function immagini(): HasMany
    {
        return $this->hasMany(ProdottoImmagine::class)->orderBy('ordine');
    }

    public function getGiacenzaTotaleAttribute(): int
    {
        return (int) $this->varianti->sum('quantita');
    }

    public function getPrezzoMinAttribute(): ?float
    {
        return $this->varianti->min('prezzo');
    }

    public function getPrezzoMaxAttribute(): ?float
    {
        return $this->varianti->max('prezzo');
    }
}
