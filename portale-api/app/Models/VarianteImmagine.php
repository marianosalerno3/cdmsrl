<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VarianteImmagine extends Model
{
    use HasVersion7Uuids;

    protected $table = 'variante_immagini';

    protected $fillable = ['variante_prodotto_id', 'percorso', 'ordine'];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteProdotto::class, 'variante_prodotto_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->percorso);
    }
}
