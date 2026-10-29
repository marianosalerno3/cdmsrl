<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sostituzione extends Model
{
    use HasVersion7Uuids;
    use SoftDeletes;

    protected $table = 'sostituzioni';

    protected $fillable = [
        'numero', 'ordine_originale_id', 'agente_id', 'cliente_id',
        'stato', 'motivazione', 'note',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->numero ??= sprintf('SOST-%s-%04d', now()->format('Ymd'), random_int(1000, 9999));
        });
    }

    public function ordineOriginale(): BelongsTo
    {
        return $this->belongsTo(OrdineB2B::class, 'ordine_originale_id');
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function righe(): HasMany
    {
        return $this->hasMany(SostituzioneRiga::class);
    }
}
