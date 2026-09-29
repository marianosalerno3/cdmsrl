<?php

namespace App\Models;

use App\Enums\ListinoTipo;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Agente / rappresentante. Si autentica sull'API (Sanctum personal access token),
 * NON sul pannello Filament.
 */
class Agente extends Authenticatable
{
    use HasApiTokens;
    use HasVersion7Uuids;
    use SoftDeletes;

    protected $table = 'agenti';

    protected $fillable = [
        'codice_agente', 'nome', 'email', 'password', 'telefono',
        'listino_default', 'commissione_perc', 'attivo',
        'codice_erp', 'sincronizzato_erp_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'listino_default' => ListinoTipo::class,
            'commissione_perc' => 'decimal:2',
            'attivo' => 'boolean',
            'sincronizzato_erp_at' => 'datetime',
        ];
    }

    public function clienti(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function ordini(): HasMany
    {
        return $this->hasMany(OrdineB2B::class);
    }

    public function getVenditeTotaliAttribute(): float
    {
        return (float) $this->ordini()->whereNull('deleted_at')->sum('totale');
    }
}
