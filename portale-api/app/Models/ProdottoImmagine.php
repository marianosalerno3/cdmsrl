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
        return Storage::disk('public')->url($this->percorso);
    }
}
