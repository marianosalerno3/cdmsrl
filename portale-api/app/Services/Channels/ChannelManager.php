<?php

namespace App\Services\Channels;

use App\Services\Channels\Contracts\EcommerceChannel;
use App\Settings\IntegrationSettings;
use Illuminate\Support\Collection;

/**
 * Registro dei canali e-commerce attivi (in base ai settings del pannello).
 *
 *   app(ChannelManager::class)->driver('shopify')->pushProduct($p);
 *   app(ChannelManager::class)->enabled()->each(fn ($c) => $c->pushInventory($p));
 */
class ChannelManager
{
    /** @var array<string,EcommerceChannel> */
    private array $resolved = [];

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function driver(string $name): EcommerceChannel
    {
        return $this->resolved[$name] ??= match ($name) {
            'shopify' => new ShopifyChannel($this->settings),
            'woocommerce' => new WooCommerceChannel($this->settings),
            default => throw new \InvalidArgumentException("Canale sconosciuto: {$name}"),
        };
    }

    public function isEnabled(string $name): bool
    {
        return match ($name) {
            'shopify' => filled($this->settings->shopify_shop_domain) && filled($this->settings->shopify_access_token),
            'woocommerce' => filled($this->settings->woocommerce_url)
                && filled($this->settings->woocommerce_consumer_key)
                && filled($this->settings->woocommerce_consumer_secret),
            default => false,
        };
    }

    /** @return Collection<int,EcommerceChannel> */
    public function enabled(): Collection
    {
        return collect(['shopify', 'woocommerce'])
            ->filter(fn ($n) => $this->isEnabled($n))
            ->map(fn ($n) => $this->driver($n))
            ->values();
    }

    public function syncGiacenzeAutomatica(): bool
    {
        return (bool) $this->settings->sync_giacenze_automatica;
    }
}
