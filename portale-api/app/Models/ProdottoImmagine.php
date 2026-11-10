<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProdottoImmagine extends Model
{
    use HasVersion7Uuids;

    protected $table = 'prodotto_immagini';

    protected $fillable = ['prodotto_id', 'percorso', 'ordine'];

    public function prodotto(): BelongsTo
    {
        return $this->belongsTo(Prodotto::class);
    }

    public function getUrlAttribute(): string
    {
        // i nomi file possono contenere spazi ("OC405-02 DAV_2.jpg"): senza codifica Shopify risponde "Image URL is invalid"
        return Storage::disk('public')->url(implode('/', array_map('rawurlencode', explode('/', $this->percorso))));
    }
}
