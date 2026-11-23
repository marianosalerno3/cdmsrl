<?php

namespace App\Http\Controllers;

use App\Models\Cliente;

/** Link "non voglio più ricevere comunicazioni" presente in ogni email commerciale (URL firmato). */
class ComunicazioniController extends Controller
{
    public function annulla(Cliente $cliente)
    {
        if ($cliente->consenso_comunicazioni_revocato_at === null) {
            $cliente->forceFill(['consenso_comunicazioni_revocato_at' => now()])->saveQuietly();
        }

        return view('comunicazioni-annullate', ['cliente' => $cliente]);
    }
}
