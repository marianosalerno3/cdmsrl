<?php

namespace Tests\Feature;

use App\Enums\Linea;
use App\Filament\Resources\PacchettoResource\Pages\ManagePacchetti;
use App\Filament\Resources\ProdottoResource\Pages\EditProdotto;
use App\Filament\Resources\ProdottoResource\Pages\ListProdotti;
use App\Models\Pacchetto;
use App\Models\Prodotto;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestione dei pacchetti dal pannello: ogni pacchetto ha una linea e si assegna solo a prodotti di quella linea. */
class PacchettiPannelloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('access'));
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin-pacchetti@example.test', 'password' => 'password-admin', 'is_active' => true]));
    }

    private function prodotto(string $codice, Linea $linea): Prodotto
    {
        return Prodotto::create(['codice' => $codice, 'nome' => 'Abito '.$codice, 'prezzo_base' => 100, 'attivo' => true, 'linea' => $linea]);
    }

    public function test_si_crea_un_pacchetto_per_linea_e_il_nome_e_unico_solo_dentro_la_linea(): void
    {
        Livewire::test(ManagePacchetti::class)
            ->callAction('create', ['linea' => Linea::ClasseDiValentina->value, 'nome' => 'BASICI', 'ordine' => 1])
            ->assertHasNoActionErrors();

        // stessa linea, stesso nome: rifiutato
        Livewire::test(ManagePacchetti::class)
            ->callAction('create', ['linea' => Linea::ClasseDiValentina->value, 'nome' => 'BASICI', 'ordine' => 2])
            ->assertHasActionErrors(['nome']);

        // altra linea, stesso nome: ok
        Livewire::test(ManagePacchetti::class)
            ->callAction('create', ['linea' => Linea::ClaraG->value, 'nome' => 'BASICI', 'ordine' => 3])
            ->assertHasNoActionErrors();

        $this->assertSame(2, Pacchetto::where('nome', 'BASICI')->count());
    }

    public function test_la_linea_e_obbligatoria(): void
    {
        Livewire::test(ManagePacchetti::class)
            ->callAction('create', ['nome' => 'SENZA LINEA', 'ordine' => 1])
            ->assertHasActionErrors(['linea' => 'required']);
    }

    public function test_dalla_scheda_prodotto_si_assegna_un_pacchetto_della_sua_linea(): void
    {
        $classe = Pacchetto::create(['nome' => 'ROSSO', 'linea' => Linea::ClasseDiValentina, 'ordine' => 1]);
        $prodotto = $this->prodotto('A1', Linea::ClasseDiValentina);

        Livewire::test(EditProdotto::class, ['record' => $prodotto->getRouteKey()])
            ->fillForm(['pacchetto_id' => $classe->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($classe->id, $prodotto->fresh()->pacchetto_id);
    }

    public function test_assegnazione_in_blocco_salta_i_prodotti_di_un_altra_linea(): void
    {
        $classe = Pacchetto::create(['nome' => 'ROSSO', 'linea' => Linea::ClasseDiValentina, 'ordine' => 1]);
        $suo = $this->prodotto('A1', Linea::ClasseDiValentina);
        $altro = $this->prodotto('C1', Linea::ClaraG);

        Livewire::test(ListProdotti::class)
            ->callTableBulkAction('assegna_pacchetto', [$suo, $altro], ['pacchetto_id' => $classe->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame($classe->id, $suo->fresh()->pacchetto_id);
        $this->assertNull($altro->fresh()->pacchetto_id); // di un'altra linea: non assegnato
    }
}
