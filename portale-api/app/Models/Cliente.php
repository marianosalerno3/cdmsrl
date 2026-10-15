<?php

namespace App\Models;

use App\Enums\ClienteTipo;
use App\Enums\ListinoTipo;
use App\Enums\RichiestaStato;
use App\Mail\ClienteApprovatoMail;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cliente B2B. Dopo registrazione + approvazione admin si autentica sull'API
 * (Sanctum personal access token) e ordina da solo; gli agenti continuano a ordinare per i propri clienti.
 */
class Cliente extends Authenticatable
{
    use HasApiTokens;
    use HasVersion7Uuids;
    use Notifiable; // serve per inviare l'email di reset password
    use SoftDeletes;

    protected $table = 'clienti';

    protected $fillable = [
        'agente_id', 'codice_cliente_erp', 'tipo',
        'ragione_sociale', 'nome', 'cognome', 'email', 'password', 'telefono',
        'stato_richiesta', 'approvato_at', 'partita_iva', 'codice_fiscale', 'codice_sdi', 'pec',
        'tipo_listino', 'attivo', 'contrassegno_abilitato',
        'indirizzo', 'cap', 'citta', 'provincia', 'nazione',
        'criterio_sconto', 'zona', 'nazione_erp', 'tipologia_fe', 'tipologia_codice_fe',
        'documenti', 'sincronizzato_erp_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'tipo' => ClienteTipo::class,
            'stato_richiesta' => RichiestaStato::class,
            'approvato_at' => 'datetime',
            'tipo_listino' => ListinoTipo::class,
            'attivo' => 'boolean',
            'contrassegno_abilitato' => 'boolean',
            'documenti' => 'array',
            'sincronizzato_erp_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // La prima volta che un cliente può accedere (password + approvato + attivo), da qualunque punto del pannello
        // (pulsante Approva, scheda di modifica, Imposta accesso), registriamo la data e gli mandiamo l'email di conferma.
        static::saved(function (self $cliente) {
            if ($cliente->approvato_at === null && $cliente->puoAccedere()) {
                $cliente->forceFill(['approvato_at' => now()])->saveQuietly();

                if (filled($cliente->email)) {
                    Mail::to($cliente->email)->queue(new ClienteApprovatoMail($cliente));
                }
            }
        });
    }

    /** Può accedere al portale: ha una password, è approvato e attivo. */
    public function puoAccedere(): bool
    {
        return filled($this->password)
            && $this->attivo
            && $this->stato_richiesta === RichiestaStato::Approvato;
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
