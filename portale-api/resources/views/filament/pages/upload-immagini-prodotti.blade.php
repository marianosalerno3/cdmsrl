<x-filament-panels::page>
    <form wire:submit="associa" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-link">
            Abbina immagini
        </x-filament::button>
    </form>

    @if (! empty($report))
        <x-filament::section heading="Esito" class="mt-6">
            <ul class="space-y-1 text-sm font-mono">
                @foreach ($report as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
