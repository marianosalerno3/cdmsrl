<?php

namespace Tests\Feature;

use App\Enums\ListinoTipo;
use App\Enums\RichiestaStato;
use App\Models\AppConfig;
use App\Models\Carrello;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SvuotaClientiImportatiTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(array $over = []): Cliente
    {
        return Cliente::create($over + [
            'ragione_sociale' => 'Cliente '.random_int(1000, 9999), 'partita_iva' => 'IT'.random_int(10000000, 99999999),
            'tipo' => 'b2b', 'tipo_listino' => ListinoTipo::Standard, 'attivo' => true,
            'stato_richiesta' => RichiestaStato::InAttesa,
        ]);
    }

    /** Un cliente di test con accesso, due importati da WinMino e uno nato sul portale. */
    private function scenario(): array
    {
        $test = $this->cliente(['email' => 'test@example.test', 'password' => 'una-password-lunga', 'stato_richiesta' => RichiestaStato::Approvato, 'codice_cliente_erp' => 'C-TEST']);
        $erp1 = $this->cliente(['codice_cliente_erp' => 'C-1', 'email' => 'uno@example.test']);
        $erp2 = $this->cliente(['codice_cliente_erp' => 'C-2']);
        $portale = $this->cliente(['email' => 'nuovo@example.test']); // registrato sul portale: niente codice ERP
        Carrello::create(['cliente_id' => $erp1->id, 'righe' => [['varianteId' => 'v', 'quantita' => 1]], 'totale' => 10, 'aggiornato_at' => now()]);

        return [$test, $erp1, $erp2, $portale];
    }

    public function test_senza_conferma_non_cancella_nulla(): void
    {
        $this->scenario();

        $this->artisan('clienti:svuota-importati')->assertSuccessful();

        $this->assertSame(4, Cliente::count());
        $this->assertTrue((bool) AppConfig::get('sync_clienti_attivo', true));
    }

    public function test_cancella_gli_importati_e_tiene_il_cliente_di_test_e_quelli_nati_sul_portale(): void
    {
        [$test, $erp1, $erp2, $portale] = $this->scenario();

        $this->artisan('clienti:svuota-importati', ['--conferma' => true])->assertSuccessful();

        $this->assertEqualsCanonicalizing([$test->id, $portale->id], Cliente::withTrashed()->pluck('id')->all());
        $this->assertSame(0, Carrello::count());                      // il carrello del cliente cancellato segue
        $this->assertFalse((bool) AppConfig::get('sync_clienti_attivo', true)); // l'import notturno non li riporta
    }

    public function test_con_piu_clienti_con_accesso_chiede_quale_tenere(): void
    {
        [$test, $erp1] = $this->scenario();
        $erp1->update(['password' => 'altra-password-lunga', 'stato_richiesta' => RichiestaStato::Approvato]);

        $this->artisan('clienti:svuota-importati', ['--conferma' => true])->assertFailed();
        $this->assertSame(4, Cliente::count());

        $this->artisan('clienti:svuota-importati', ['--tieni' => ['test@example.test'], '--conferma' => true])->assertSuccessful();
        $this->assertNotNull(Cliente::find($test->id));
        $this->assertNull(Cliente::find($erp1->id));
    }

    public function test_l_import_dei_clienti_si_ferma_se_spento(): void
    {
        AppConfig::put('sync_clienti_attivo', false);

        $this->artisan('sync:clienti')->expectsOutputToContain('spento')->assertSuccessful();
    }
}
