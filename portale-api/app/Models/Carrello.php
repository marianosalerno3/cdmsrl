<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Carrello salvato sul server: serve a riprenderlo da un altro dispositivo e al promemoria per i carrelli fermi. */
class Carrello extends Model
{
    use HasVersion7Uuids;

    protected $table = 'carrelli';

    protected $fillable = ['cliente_id', 'agente_id', 'righe', 'totale', 'aggiornato_at', 'promemoria_inviato_at'];

    protected function casts(): array
    {
        return [
            'righe' => 'array',
            'totale' => 'decimal:2',
            'aggiornato_at' => 'datetime',
            'promemoria_inviato_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }
}
