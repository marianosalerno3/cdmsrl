<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Ciclo di vita di un OrdineB2B.
 * Valori osservati nell'originale: "Ricevuto", "confermato_cliente",
 * "In Lavorazione", "Spedito", "Consegnato", "Annullato".
 */
enum OrderStatus: string implements HasLabel, HasColor
{
    case Ricevuto = 'ricevuto';
    case ConfermatoCliente = 'confermato_cliente';
    case InLavorazione = 'in_lavorazione';
    case Spedito = 'spedito';
    case Consegnato = 'consegnato';
    case Annullato = 'annullato';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ricevuto => 'Ricevuto',
            self::ConfermatoCliente => 'Confermato dal cliente',
            self::InLavorazione => 'In lavorazione',
            self::Spedito => 'Spedito',
            self::Consegnato => 'Consegnato',
            self::Annullato => 'Annullato',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Ricevuto => 'info',
            self::ConfermatoCliente => 'primary',
            self::InLavorazione => 'warning',
            self::Spedito => 'purple',
            self::Consegnato => 'success',
            self::Annullato => 'danger',
        };
    }

    /** Stati che si considerano "confermati" e possono essere spinti a ERP / e-commerce. */
    public function isConfirmed(): bool
    {
        return in_array($this, [self::ConfermatoCliente, self::InLavorazione, self::Spedito, self::Consegnato], true);
    }
}
