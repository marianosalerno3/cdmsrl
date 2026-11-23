<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasVersion7Uuids;

    protected $table = 'categorie';

    protected $fillable = ['nome', 'sopracategoria_id', 'ordine'];

    public function sopracategoria(): BelongsTo
    {
        return $this->belongsTo(Sopracategoria::class);
    }

    public function prodotti(): HasMany
    {
        return $this->hasMany(Prodotto::class);
    }
}
