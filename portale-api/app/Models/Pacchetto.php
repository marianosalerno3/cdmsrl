<?php

namespace App\Models;

use App\Enums\Linea;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pacchetto del catalogo, creato da CDM nel pannello per una linea: raggruppa prodotti di quella linea e ne decide l'ordine di presentazione. */
class Pacchetto extends Model
{
    use HasVersion7Uuids;

    protected $table = 'pacchetti';

    protected $fillable = ['nome', 'linea', 'ordine'];

    protected function casts(): array
    {
        return ['linea' => Linea::class];
    }

    public function prodotti(): HasMany
    {
        return $this->hasMany(Prodotto::class);
    }
}
