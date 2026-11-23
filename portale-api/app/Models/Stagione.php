<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stagione extends Model
{
    use HasVersion7Uuids;

    protected $table = 'stagioni';

    protected $fillable = [
        'codice', 'nome', 'data_inizio', 'data_fine', 'attiva', 'programmata', 'ordine',
    ];

    protected function casts(): array
    {
        return [
            'data_inizio' => 'date',
            'data_fine' => 'date',
            'attiva' => 'boolean',
            'programmata' => 'boolean',
        ];
    }

    public function prodotti(): HasMany
    {
        return $this->hasMany(Prodotto::class);
    }
}
