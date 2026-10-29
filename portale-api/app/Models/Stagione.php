<?php

namespace App\Models;

use App\Enums\Linea;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stagione extends Model
{
    use HasVersion7Uuids;

    protected $table = 'stagioni';

    protected $fillable = [
        'codice', 'nome', 'data_inizio', 'data_fine', 'attiva', 'programmata', 'programmata_linee', 'ordine',
    ];

    protected function casts(): array
    {
        return [
            'data_inizio' => 'date',
            'data_fine' => 'date',
            'attiva' => 'boolean',
            'programmata' => 'boolean',
            'programmata_linee' => 'array',
        ];
    }

    /**
     * Stagione programmata per la linea data: vale l'eccezione della linea, se c'è, altrimenti il valore della stagione.
     */
    public function isProgrammataPer(Linea|string|null $linea): bool
    {
        $nome = $linea instanceof Linea ? $linea->value : strtoupper(trim((string) $linea));

        foreach ((array) $this->programmata_linee as $eccezione) {
            if ($nome !== '' && strtoupper(trim((string) ($eccezione['linea'] ?? ''))) === $nome) {
                return (bool) ($eccezione['programmata'] ?? false);
            }
        }

        return (bool) $this->programmata;
    }

    public function prodotti(): HasMany
    {
        return $this->hasMany(Prodotto::class);
    }
}
