<?php

namespace App\Filament\Pages;

use App\Models\Prodotto;
use App\Models\ProdottoImmagine;
use App\Models\VarianteImmagine;
use App\Models\VarianteProdotto;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload massivo di immagini con abbinamento automatico per nome file.
 *
 * Convenzioni riconosciute (estensione esclusa, case-insensitive):
 *   <SKU>                -> immagine di variante          es. B00302-40-NERO.jpg
 *   <SKU>_2 / <SKU>-2    -> ulteriore immagine variante
 *   <CODICE_PRODOTTO>    -> immagine di prodotto          es. B003-02.jpg  (=> B003/02)
 *   <CODICE>_3           -> ulteriore immagine prodotto
 * I separatori '/', '-', '_', ' ' nel codice sono equivalenti nel confronto.
 */
class UploadImmaginiProdotti extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Catalogo';

    protected static ?string $navigationLabel = 'Upload Massivo Immagini';

    protected static ?string $title = 'Upload Massivo Immagini';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.upload-immagini-prodotti';

    public ?array $data = [];

    /** @var array<int,string> */
    public array $report = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('istruzioni')
                    ->label('Come funziona')
                    ->content('Il nome del file (senza estensione) viene confrontato con lo SKU della variante o con il codice prodotto. '
                        .'Suffissi come _2, -3 aggiungono immagini ulteriori. I separatori / - _ spazio sono equivalenti.'),
                FileUpload::make('files')
                    ->label('Immagini')
                    ->image()
                    ->multiple()
                    ->maxFiles(200)
                    ->directory('import-immagini')
                    ->disk('public')
                    ->reorderable(false)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function associa(): void
    {
        $paths = collect($this->form->getState()['files'] ?? []);

        if ($paths->isEmpty()) {
            Notification::make()->warning()->title('Nessun file caricato')->send();

            return;
        }

        $prodotti = Prodotto::pluck('id', 'codice')
            ->mapWithKeys(fn ($id, $codice) => [self::norm($codice) => $id]);
        $varianti = VarianteProdotto::pluck('id', 'sku')
            ->mapWithKeys(fn ($id, $sku) => [self::norm($sku) => $id]);

        $ok = 0;
        $ko = 0;
        $this->report = [];

        foreach ($paths as $path) {
            $base = pathinfo($path, PATHINFO_FILENAME);
            [$key, $suffix] = self::splitSuffix($base);
            $norm = self::norm($key);

            if ($vid = $varianti[$norm] ?? null) {
                VarianteImmagine::create([
                    'variante_prodotto_id' => $vid,
                    'percorso' => $path,
                    'ordine' => VarianteImmagine::where('variante_prodotto_id', $vid)->max('ordine') + 1,
                ]);
                $ok++;
            } elseif ($pid = $prodotti[$norm] ?? null) {
                ProdottoImmagine::create([
                    'prodotto_id' => $pid,
                    'percorso' => $path,
                    'ordine' => ProdottoImmagine::where('prodotto_id', $pid)->max('ordine') + 1,
                ]);
                $ok++;
            } else {
                Storage::disk('public')->delete($path);
                $this->report[] = "✗ {$base} — nessun prodotto/variante corrispondente";
                $ko++;
            }
        }

        array_unshift($this->report, "✓ {$ok} immagini abbinate · ✗ {$ko} scartate");
        $this->form->fill();

        Notification::make()
            ->title("Abbinamento completato: {$ok} ok, {$ko} scartate")
            ->color($ko === 0 ? 'success' : 'warning')
            ->send();
    }

    private static function norm(string $v): string
    {
        return Str::of($v)->lower()->replaceMatches('/[\/\-_\s]+/', '')->toString();
    }

    /** Estrae eventuale suffisso numerico "_2" / "-3" dalla fine del nome. */
    private static function splitSuffix(string $base): array
    {
        if (preg_match('/^(.*?)[\-_](\d{1,2})$/', $base, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return [$base, 1];
    }
}
