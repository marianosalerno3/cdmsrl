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
            'shopify_shop_domain' => $s->shopify_shop_domain,
            'shopify_access_token' => $s->shopify_access_token,
            'stripe_key' => $s->stripe_key,
            'stripe_secret' => $s->stripe_secret,
            'stripe_webhook_secret' => $s->stripe_webhook_secret,
            'erp_driver' => $s->erp_driver,
            'erp_base_url' => $s->erp_base_url,
            'erp_username' => $s->erp_username,
            'erp_password' => $s->erp_password,
            'sync_giacenze_automatica' => $s->sync_giacenze_automatica,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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

                Section::make('ERP (WinMino)')
                    ->description('Webservice DataSnap. Vedi docs/winmino-integration.md.')
                    ->columns(2)
                    ->schema([
                        \Filament\Forms\Components\Select::make('erp_driver')->label('Driver')
                            ->options(['null' => 'Nessuno', 'winmino' => 'WinMino'])
                            ->default('null'),
                        TextInput::make('erp_base_url')->label('Base URL')
                            ->helperText('es. http://1.2.3.4:8080 — senza slash finale'),
                        TextInput::make('erp_username')->label('Username'),
                        TextInput::make('erp_password')->label('Password')->password()->revealable(),
                    ]),

                Section::make('Impostazioni Generali')
                    ->schema([
                        Toggle::make('sync_giacenze_automatica')
                            ->label('Sincronizzazione giacenze automatica')
                            ->helperText('Se attivo, ogni modifica giacenza viene propagata a Shopify.'),
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

    public function testErp(): void
    {
        // salva prima, così ErpManager legge i valori correnti
        $this->salva();

        try {
            $ok = app(\App\Services\Erp\ErpManager::class)->driver()->test();
            $ok
                ? Notification::make()->success()->title('ERP: connessione OK')->send()
                : Notification::make()->danger()->title('ERP: connessione fallita')->body('Verifica base URL, credenziali e raggiungibilità del server.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('ERP: '.$e->getMessage())->send();
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
