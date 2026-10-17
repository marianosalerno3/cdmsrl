<?php

namespace App\Services\Erp;

class ErpException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $erpCode = null,
        public readonly mixed $raw = null,
    ) {
        parent::__construct($message);
    }
}
