<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Stripe = 'stripe';
    case Bonifico = 'bonifico';
    case Contrassegno = 'contrassegno';
    case Rimessa = 'rimessa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe (Carta di Credito)',
            self::Bonifico => 'Bonifico bancario',
            self::Contrassegno => 'Contrassegno',
            self::Rimessa => 'Rimessa diretta',
        };
    }

    public function requiresOnlinePayment(): bool
    {
        return $this === self::Stripe;
    }
}
