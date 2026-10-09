<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipo di listino applicato a un Agente o a un Cliente.
 * Nell'originale: "standard" e "plus5" (= prezzo base +5%).
 * Il moltiplicatore è centralizzato qui e usato da App\Services\PriceService.
 */
enum ListinoTipo: string implements HasLabel
{
    case Standard = 'standard';
    case Plus5 = 'plus5';
    case Plus10 = 'plus10';
    case Outlet = 'outlet';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Plus5 => 'Standard +5%',
            self::Plus10 => 'Standard +10%',
            self::Outlet => 'Outlet',
        };
    }

    /** Moltiplicatore sul prezzo base della variante. */
    public function multiplier(): float
    {
        return match ($this) {
            self::Standard => 1.0,
            self::Plus5 => 1.05,
            self::Plus10 => 1.10,
            self::Outlet => 0.60,
        };
    }
}
