<x-filament-panels::page>
    <form wire:submit="salva" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Salva Configurazioni
            </x-filament::button>

            <x-filament::button color="gray" wire:click="testShopify" icon="heroicon-o-signal">
                Test Shopify
            </x-filament::button>

            <x-filament::button color="gray" wire:click="testErp" icon="heroicon-o-signal">
                Test ERP
            </x-filament::button>

            <x-filament::button color="info" wire:click="sincronizzaProdotti" icon="heroicon-o-arrow-path">
                Sincronizza Prodotti e Giacenze
            </x-filament::button>

            <x-filament::button color="info" wire:click="sincronizzaClienti" icon="heroicon-o-users">
                Sincronizza Clienti
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
