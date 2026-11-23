<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;

class Taglia extends Model
{
    use HasVersion7Uuids;

    protected $table = 'taglie';

    protected $fillable = ['nome', 'codice', 'ordine'];
}
