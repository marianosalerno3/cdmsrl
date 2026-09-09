<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'uuid', 'exists:clienti,id'],
            'metodo_pagamento' => ['required', 'in:stripe,bonifico,contrassegno,rimessa'],
            'note_agente' => ['nullable', 'string', 'max:2000'],
            'indirizzo_spedizione' => ['nullable', 'string', 'max:500'],

            'righe' => ['required', 'array', 'min:1'],
            'righe.*.variante_id' => ['required', 'uuid', 'exists:variante_prodotti,id'],
            'righe.*.quantita' => ['required', 'integer', 'min:1'],
        ];
    }
}
