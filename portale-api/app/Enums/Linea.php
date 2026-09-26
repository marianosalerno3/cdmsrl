<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Linea / brand del prodotto. Il valore è il nome mostrato (e usato come filtro);
 * `codiceWinmino()` è il codice linea in WinMino (GetLinee: CG, OL, OC…).
 */
enum Linea: string implements HasLabel
{
    case Oltretempo = 'OLTRETEMPO';
    case ClasseDiValentina = 'CLASSE DI VALENTINA';
    case ClaraG = 'CLARA G';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function codiceWinmino(): string
    {
        return match ($this) {
            self::Oltretempo => 'OL',
            self::ClasseDiValentina => 'OC',   // in WinMino: "CLASSE DI OLTRETEMPO"
            self::ClaraG => 'CG',
        };
    }

    public static function fromWinMino(?string $codice): ?self
    {
        foreach (self::cases() as $l) {
            if ($l->codiceWinmino() === strtoupper((string) $codice)) {
                return $l;
            }
        }

        return null;
    }
}
