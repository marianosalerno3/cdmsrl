<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SostituzioneRiga extends Model
{
    use HasVersion7Uuids;

    protected $table = 'sostituzione_righe';

    protected $fillable = [
        'sostituzione_id', 'variante_resa_id', 'variante_richiesta_id', 'quantita',
    ];

    protected function casts(): array
    {
        return ['quantita' => 'integer'];
    }

    public function sostituzione(): BelongsTo
    {
        return $this->belongsTo(Sostituzione::class);
    }

    public function varianteResa(): BelongsTo
    {
        return $this->belongsTo(VarianteProdotto::class, 'variante_resa_id');
    }

    public function varianteRichiesta(): BelongsTo
    {
        return $this->belongsTo(VarianteProdotto::class, 'variante_richiesta_id');
    }
}
