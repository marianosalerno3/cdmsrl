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
 * Convenzioni riconosciute (estensione esclusa, case-insensitive), in ordine di priorita':
 *   1. <SKU>              -> immagine di variante          es. 4000000974383.jpg
 *   2. <CODICE_PRODOTTO>  -> immagine di prodotto          es. CG3873-0727-013.jpg
 *   3. codice contenuto nel NOME del prodotto: se il nome file CONTIENE il codice che apre il nome
 *      (es. nome "CG3873/13 PANTALONE ..." -> codice "CG3873/13") l'immagine e' del prodotto.
 *      Vale CG3873-13.jpg, CG3873_13.jpg, CG3873/13 pantalone nero.jpg, CG387313_2.jpg ...
 * Suffissi _2, -3 in coda = ulteriori immagini. I separatori '/', '-', '_', ' ' sono equivalenti.
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
                    ->content('Basta che il nome del file contenga il codice che si legge in testa al nome del prodotto '
                        .'(es. prodotto "CG3873/13 PANTALONE…" => CG3873-13.jpg, CG3873_13.jpg, CG387313.jpg). '
                        .'Funzionano anche lo SKU della variante e il codice interno. Suffissi come _2, -3 aggiungono immagini ulteriori. '
                        .'I separatori / - _ spazio sono equivalenti.'),
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

        // codice "leggibile" che apre il nome del prodotto, es. "CG3873/13" -> regex tollerante sui separatori
        $perNome = Prodotto::get(['id', 'nome'])
            ->map(fn ($p) => ['id' => $p->id, 'code' => self::nameCode($p->nome)])
            ->filter(fn ($x) => $x['code'] !== null)
            ->sortByDesc(fn ($x) => strlen($x['code'])) // codici piu' lunghi prima
            ->map(fn ($x) => ['id' => $x['id'], 'rx' => self::codeRegex($x['code'])])
            ->values();

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
            } elseif ($pid = self::matchByNameCode($base, $perNome)) {
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

    /** Codice che apre il nome prodotto: "CG3873/13 PANTALONE…" -> "CG3873/13"; "OC/STOLA 01 STOLA…" -> "OC/STOLA 01". */
    private static function nameCode(?string $nome): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $nome), -1, PREG_SPLIT_NO_EMPTY);
        if (! $parts || ! preg_match('/[A-Za-z]/', $parts[0]) || strlen($parts[0]) < 3) {
            return null;
        }
        $code = $parts[0];
        // codice spezzato da uno spazio ("OC/STOLA 01"): il numero breve che segue fa parte del codice
        if (! preg_match('/\d/', $code) && isset($parts[1]) && preg_match('/^\d{1,3}$/', $parts[1])) {
            $code .= ' '.$parts[1];
        }

        return $code;
    }

    /** Regex che trova il codice nel nome file ignorando i separatori, con confini netti (CG3873-1 non e' CG3873-13). */
    private static function codeRegex(string $code): string
    {
        $chunks = preg_split('/[\/\-_\s]+/', $code, -1, PREG_SPLIT_NO_EMPTY);

        return '/(?<![a-z0-9])'.implode('[\/\-_\s]*', array_map(fn ($c) => preg_quote($c, '/'), $chunks)).'(?![a-z0-9])/i';
    }

    private static function matchByNameCode(string $base, $perNome): ?string
    {
        foreach ($perNome as $x) {
            if (preg_match($x['rx'], $base)) {
                return $x['id'];
            }
        }

        return null;
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
