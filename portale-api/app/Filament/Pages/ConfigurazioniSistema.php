<?php

namespace App\Filament\Pages;

use App\Settings\IntegrationSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class ConfigurazioniSistema extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Configurazioni';

    protected static ?string $title = 'Configurazioni Sistema';

    protected static string $view = 'filament.pages.configurazioni-sistema';

    public ?array $data = [];

    public function mount(): void
    {
        $s = app(IntegrationSettings::class);

        $this->form->fill([
            'woocommerce_url' => $s->woocommerce_url,
            'woocommerce_consumer_key' => $s->woocommerce_consumer_key,
            'woocommerce_consumer_secret' => $s->woocommerce_consumer_secret,
            'shopify_shop_domain' => $s->shopify_shop_domain,
            'shopify_access_token' => $s->shopify_access_token,
            'stripe_key' => $s->stripe_key,
            'stripe_secret' => $s->stripe_secret,
            'stripe_webhook_secret' => $s->stripe_webhook_secret,
            'erp_driver' => $s->erp_driver,
            'erp_base_url' => $s->erp_base_url,
            'erp_api_key' => $s->erp_api_key,
            'sync_giacenze_automatica' => $s->sync_giacenze_automatica,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('WordPress / WooCommerce (B2B)')
                    ->description('Chiavi API da WooCommerce → Impostazioni → Avanzate → REST API')
                    ->columns(2)
                    ->schema([
                        TextInput::make('woocommerce_url')->label('URL WordPress')->url()
                            ->helperText('URL completo, senza slash finale')->columnSpanFull(),
                        TextInput::make('woocommerce_consumer_key')->label('Consumer Key')->password()->revealable(),
                        TextInput::make('woocommerce_consumer_secret')->label('Consumer Secret')->password()->revealable(),
                    ]),

                Section::make('Shopify (B2C)')
                    ->description('Access Token da Shopify Admin → Apps → Develop apps')
                    ->columns(2)
                    ->schema([
                        TextInput::make('shopify_shop_domain')->label('Shop Domain')
                            ->helperText('es. mio-shop.myshopify.com — senza https://'),
                        TextInput::make('shopify_access_token')->label('Access Token')->password()->revealable(),
                    ]),

                Section::make('Stripe (Pagamenti B2B)')
                    ->columns(2)
                    ->schema([
                        TextInput::make('stripe_key')->label('Publishable Key'),
                        TextInput::make('stripe_secret')->label('Secret Key')->password()->revealable(),
                        TextInput::make('stripe_webhook_secret')->label('Webhook Secret')->password()->revealable(),
                    ]),

                Section::make('ERP')
                    ->columns(3)
                    ->schema([
                        TextInput::make('erp_driver')->label('Driver')
                            ->helperText('null | winmino | ...'),
                        TextInput::make('erp_base_url')->label('Base URL')->url(),
                        TextInput::make('erp_api_key')->label('API Key')->password()->revealable(),
                    ]),

                Section::make('Impostazioni Generali')
                    ->schema([
                        Toggle::make('sync_giacenze_automatica')
                            ->label('Sincronizzazione giacenze automatica')
                            ->helperText('Se attivo, ogni modifica giacenza viene propagata a WooCommerce e Shopify.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function salva(): void
    {
        $s = app(IntegrationSettings::class);

        foreach ($this->form->getState() as $key => $value) {
            if (property_exists($s, $key)) {
                $s->{$key} = $value;
            }
        }
        $s->save();

        Notification::make()->success()->title('Configurazioni salvate')->send();
    }

    public function testWordpress(): void
    {
        $s = $this->form->getState();

        try {
            $resp = Http::withBasicAuth($s['woocommerce_consumer_key'] ?? '', $s['woocommerce_consumer_secret'] ?? '')
                ->acceptJson()
                ->timeout(10)
                ->get(rtrim($s['woocommerce_url'] ?? '', '/').'/wp-json/wc/v3/system_status');

            $resp->successful()
                ? Notification::make()->success()->title('WooCommerce: connessione OK')->send()
                : Notification::make()->danger()->title('WooCommerce: errore '.$resp->status())->body((string) $resp->body())->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('WooCommerce: '.$e->getMessage())->send();
        }
    }

    public function testShopify(): void
    {
        $s = $this->form->getState();

        try {
            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $s['shopify_access_token'] ?? ''])
                ->acceptJson()
                ->timeout(10)
                ->get('https://'.($s['shopify_shop_domain'] ?? '').'/admin/api/2025-01/shop.json');

            $resp->successful()
                ? Notification::make()->success()->title('Shopify: connessione OK')->send()
                : Notification::make()->danger()->title('Shopify: errore '.$resp->status())->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Shopify: '.$e->getMessage())->send();
        }
    }

    public function sincronizzaProdotti(): void
    {
        Artisan::queue('sync:giacenze');
        Notification::make()->success()->title('Sincronizzazione prodotti/giacenze in coda')->send();
    }

    public function sincronizzaClienti(): void
    {
        Artisan::queue('sync:clienti');
        Notification::make()->success()->title('Sincronizzazione clienti in coda')->send();
    }
}
