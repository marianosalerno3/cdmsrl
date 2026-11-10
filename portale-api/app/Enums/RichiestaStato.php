<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stato del flusso di onboarding di un Cliente B2B.
 * Nell'originale il valore osservato è "approvato".
 */
enum RichiestaStato: string implements HasLabel, HasColor
{
    case InAttesa = 'in_attesa';
    case Approvato = 'approvato';
    case Rifiutato = 'rifiutato';

    public function getLabel(): string
    {
        return match ($this) {
            self::InAttesa => 'In attesa',
            self::Approvato => 'Approvato',
            self::Rifiutato => 'Rifiutato',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::InAttesa => 'warning',
            self::Approvato => 'success',
            self::Rifiutato => 'danger',
        };
    }
}
