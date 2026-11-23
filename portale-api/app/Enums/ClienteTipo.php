<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClienteTipo: string implements HasLabel
{
    case B2B = 'b2b';
    case B2C = 'b2c';

    public function getLabel(): string
    {
        return match ($this) {
            self::B2B => 'B2B',
            self::B2C => 'B2C',
        };
    }
}
