<?php

namespace App\Models;

use App\Enums\ListinoTipo;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasVersion7Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrdineB2B extends Model
{
    use HasVersion7Uuids;
    use SoftDeletes;

    protected $table = 'ordini_b2b';

    protected $fillable = [
        'numero', 'agente_id', 'cliente_id',
        'cliente_nome', 'cliente_email', 'cliente_telefono', 'indirizzo_spedizione',
        'stato', 'metodo_pagamento', 'listino_applicato',
        'subtotale', 'spese_spedizione', 'iva_perc', 'iva_importo', 'totale', 'totale_pezzi',
        'note_agente', 'programmato', 'data_ordine',
        'stripe_session_id', 'stripe_payment_intent', 'pagato_at',
        'email_conferma_inviata_at', 'inviato_erp_at', 'idesterno', 'erp_response',
        'shopify_order_id', 'woocommerce_order_id', 'wordpress_payload',
    ];

    protected function casts(): array
    {
        return [
            'stato' => OrderStatus::class,
            'metodo_pagamento' => PaymentMethod::class,
            'listino_applicato' => ListinoTipo::class,
            'subtotale' => 'decimal:2',
            'spese_spedizione' => 'decimal:2',
            'iva_perc' => 'decimal:2',
            'iva_importo' => 'decimal:2',
            'totale' => 'decimal:2',
            'programmato' => 'boolean',
            'data_ordine' => 'date',
            'pagato_at' => 'datetime',
            'email_conferma_inviata_at' => 'datetime',
            'inviato_erp_at' => 'datetime',
            'wordpress_payload' => 'array',
            'erp_response' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ordine) {
            $ordine->numero ??= self::generaNumero();
            $ordine->data_ordine ??= now()->toDateString();
            $ordine->idesterno ??= $ordine->numero;
        });
    }

    public static function generaNumero(): string
    {
        return sprintf('ORD-%s-%04d', now()->format('Ymd'), random_int(1000, 9999));
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function righe(): HasMany
    {
        // FK esplicita: lo snake_case di "OrdineB2B" darebbe "ordine_b2_b_id".
        return $this->hasMany(OrdineRiga::class, 'ordine_b2b_id');
    }

    public function scopeB2b($query)
    {
        return $query->whereNull('shopify_order_id')->whereNull('woocommerce_order_id');
    }

    public function isPagato(): bool
    {
        return $this->pagato_at !== null;
    }
}
