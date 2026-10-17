<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;

class Genere extends Model
{
    use HasVersion7Uuids;

    protected $table = 'generi';

    protected $fillable = ['nome'];
}
