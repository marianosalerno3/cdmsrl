<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pacchetto del catalogo, creato da CDM nel pannello: raggruppa prodotti e ne decide l'ordine di presentazione. */
class Pacchetto extends Model
{
    use HasVersion7Uuids;

    protected $table = 'pacchetti';

    protected $fillable = ['nome', 'ordine'];

    public function prodotti(): HasMany
    {
        return $this->hasMany(Prodotto::class);
    }
}
