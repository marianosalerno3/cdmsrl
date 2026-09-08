<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;

class Colore extends Model
{
    use HasVersion7Uuids;

    protected $table = 'colori';

    protected $fillable = ['nome', 'codice', 'hex'];
}
