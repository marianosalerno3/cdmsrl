<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProdottoTipo: string implements HasLabel
{
    case Semplice = 'semplice';
    case Variabile = 'variabile';

    public function getLabel(): string
    {
        return match ($this) {
            self::Semplice => 'Semplice',
            self::Variabile => 'Variabile',
        };
    }
}
