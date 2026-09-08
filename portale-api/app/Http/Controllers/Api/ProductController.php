<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListinoTipo;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Cliente;
use App\Models\Prodotto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $listino = $this->listino($request);
        $request->merge(['__listino' => $listino->value]); // letto dalle Resource

        $prodotti = Prodotto::query()
            ->where('attivo', true)
            ->with(['categoria', 'stagione', 'immagini', 'varianti'])
            ->when($request->string('search')->toString(), function ($q, $term) {
                $q->where(fn ($q) => $q
                    ->where('nome', 'like', "%{$term}%")
                    ->orWhere('codice', 'like', "%{$term}%"));
            })
            ->when($request->string('category')->toString(), fn ($q, $cat) => $q
                ->whereHas('categoria', fn ($q) => $q->where('nome', $cat)))
            ->when($request->string('season')->toString(), fn ($q, $s) => $q
                ->whereHas('stagione', fn ($q) => $q->where('codice', $s)))
            ->orderBy('codice')
            ->paginate(24)
            ->withQueryString();

        return ProductResource::collection($prodotti)
            ->additional(['listino' => $listino->value]);
    }

    public function show(Request $request, Prodotto $prodotto): ProductDetailResource
    {
        $listino = $this->listino($request);
        $request->merge(['__listino' => $listino->value]);

        $prodotto->load([
            'categoria', 'stagione', 'genere', 'immagini',
            'varianti.taglia', 'varianti.colore', 'varianti.immagini',
        ]);

        return (new ProductDetailResource($prodotto))
            ->additional(['listino' => $listino->value]);
    }

    private function listino(Request $request): ListinoTipo
    {
        $customerId = $request->string('customer_id')->toString();

        if ($customerId) {
            $cliente = Cliente::find($customerId);
            if ($cliente) {
                return $cliente->listinoEffettivo();
            }
        }

        return $request->user()?->listino_default ?? ListinoTipo::Standard;
    }
}
