<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Destinazione di spedizione di un cliente.
 * `codice` (VARCHAR 6) è la chiave di upsert su WinMino AddDestinazione ed è
 * gestita dal portale: base36 progressivo, stabile nel tempo.
 */
class Destinazione extends Model
{
    use HasVersion7Uuids;

    protected $table = 'destinazioni';

    protected $fillable = [
        'codice', 'cliente_id', 'nome', 'indirizzo', 'localita', 'provincia', 'cap',
        'telefono', 'cellulare', 'email', 'codice_fiscale', 'partita_iva', 'note',
        'sincronizzato_erp_at',
    ];

    protected function casts(): array
    {
        return ['sincronizzato_erp_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $d) {
            $d->codice ??= static::generaCodice();
        });
    }

    /** Codice a 6 caratteri, univoco, stabile. */
    public static function generaCodice(): string
    {
        do {
            $codice = strtoupper(Str::padLeft(base_convert((string) random_int(0, 2_176_782_335), 10, 36), 6, '0'));
        } while (static::query()->where('codice', $codice)->exists());

        return $codice;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
