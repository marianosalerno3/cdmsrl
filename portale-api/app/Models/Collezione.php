<?php

namespace App\Models;

use App\Enums\Linea;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stagione + linea (es. Classe di Valentina PE27). Qui si decide, a mano, se è pronto consegna o programmata;
 * per le programmate si indica la data di partenza delle spedizioni quando è nota.
 */
class Collezione extends Model
{
    use HasVersion7Uuids;

    protected $table = 'collezioni';

    protected $fillable = ['stagione_id', 'linea', 'programmata', 'data_partenza'];

    protected function casts(): array
    {
        return [
            'programmata' => 'boolean',
            'data_partenza' => 'date',
        ];
    }

    public function stagione(): BelongsTo
    {
        return $this->belongsTo(Stagione::class);
    }

    /** La collezione di una stagione + linea (null se non è stata mai censita: vale come pronto consegna). */
    public static function per(?string $stagioneId, Linea|string|null $linea): ?self
    {
        $nome = $linea instanceof Linea ? $linea->value : strtoupper(trim((string) $linea));

        return ($stagioneId && $nome !== '') ? static::where('stagione_id', $stagioneId)->where('linea', $nome)->first() : null;
    }
}
