<?php

namespace App\Models;

use App\Enums\ClienteTipo;
use App\Enums\ListinoTipo;
use App\Enums\RichiestaStato;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasVersion7Uuids;
    use SoftDeletes;

    protected $table = 'clienti';

    protected $fillable = [
        'agente_id', 'codice_cliente_erp', 'tipo',
        'ragione_sociale', 'nome', 'cognome', 'email', 'telefono',
        'stato_richiesta', 'partita_iva', 'codice_fiscale', 'codice_sdi', 'pec',
        'tipo_listino', 'attivo', 'contrassegno_abilitato',
        'indirizzo', 'cap', 'citta', 'provincia', 'nazione',
        'criterio_sconto', 'zona', 'nazione_erp', 'tipologia_fe', 'tipologia_codice_fe',
        'documenti', 'sincronizzato_erp_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => ClienteTipo::class,
            'stato_richiesta' => RichiestaStato::class,
            'tipo_listino' => ListinoTipo::class,
            'attivo' => 'boolean',
            'contrassegno_abilitato' => 'boolean',
            'documenti' => 'array',
            'sincronizzato_erp_at' => 'datetime',
        ];
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }

    public function ordini(): HasMany
    {
        return $this->hasMany(OrdineB2B::class);
    }

    public function destinazioni(): HasMany
    {
        return $this->hasMany(Destinazione::class);
    }

    public function getDenominazioneAttribute(): string
    {
        return $this->ragione_sociale
            ?: trim("{$this->nome} {$this->cognome}")
            ?: (string) $this->email;
    }

    /** Listino effettivo: override cliente, altrimenti quello dell'agente. */
    public function listinoEffettivo(): ListinoTipo
    {
        return $this->tipo_listino
            ?? $this->agente?->listino_default
            ?? ListinoTipo::Standard;
    }
}
