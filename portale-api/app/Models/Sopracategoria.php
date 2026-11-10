<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sopracategoria extends Model
{
    use HasVersion7Uuids;

    protected $table = 'sopracategorie';

    protected $fillable = ['nome', 'ordine'];

    public function categorie(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }
}
