<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Mail\NuovoOrdineBackofficeMail;
use App\Models\Cliente;
use App\Models\OrdineB2B;
use App\Models\VarianteProdotto;
use App\Services\PriceService;
use App\Services\StripeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function __construct(
        private readonly PriceService $prices,
        private readonly StripeService $stripe,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $agente = $request->user();
        $cliente = Cliente::findOrFail($request->validated('cliente_id'));
        abort_unless($cliente->agente_id === $agente->id, 403, 'Cliente non nel portafoglio agente.');

        $listino = $cliente->listinoEffettivo();

        $ordine = DB::transaction(function () use ($request, $agente, $cliente, $listino) {
            $righeInput = collect($request->validated('righe'));
            $varianti = VarianteProdotto::with('prodotto')
                ->whereIn('id', $righeInput->pluck('variante_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $righe = [];
            foreach ($righeInput as $r) {
                /** @var VarianteProdotto $v */
                $v = $varianti[$r['variante_id']];
                $prezzo = $this->prices->forVariant($v, $listino);
                $righe[] = [
                    'variante_prodotto_id' => $v->id,
                    'prodotto_codice' => $v->prodotto->codice,
                    'prodotto_nome' => $v->prodotto->nome,
                    'taglia' => $v->taglia?->nome,
                    'colore' => $v->colore?->nome,
                    'sku' => $v->sku,
                    'quantita' => $r['quantita'],
                    'prezzo_unitario' => $prezzo,
                    'totale_riga' => round($prezzo * $r['quantita'], 2),
                ];
                $v->decrement('quantita', $r['quantita']);
            }

            $metodo = PaymentMethod::from($request->validated('metodo_pagamento'));
            $spedizione = $metodo === PaymentMethod::Contrassegno ? $this->prices->shippingCost() : $this->prices->shippingCost();
            $totali = $this->prices->totals($righe, $spedizione);

            $ordine = OrdineB2B::create([
                'agente_id' => $agente->id,
                'cliente_id' => $cliente->id,
                'cliente_nome' => $cliente->denominazione,
                'cliente_email' => $cliente->email,
                'cliente_telefono' => $cliente->telefono,
                'indirizzo_spedizione' => $request->validated('indirizzo_spedizione')
                    ?: trim("{$cliente->indirizzo}, {$cliente->cap} {$cliente->citta} ({$cliente->provincia})", ', '),
                'stato' => OrderStatus::Ricevuto,
                'metodo_pagamento' => $metodo,
                'listino_applicato' => $listino,
                'note_agente' => $request->validated('note_agente'),
                ...$totali,
            ]);

            $ordine->righe()->createMany($righe);

            return $ordine;
        });

        // notifica il backoffice CDM (il commerciale carica l'ordine su WinMino)
        if ($destinatari = array_filter(array_map('trim', explode(',', (string) config('portale.backoffice_email'))))) {
            Mail::to($destinatari)->queue(new NuovoOrdineBackofficeMail($ordine));
        }

        return response()->json([
            'message' => 'Ordine inviato con successo!',
            'data' => $this->serialize($ordine->fresh('righe')),
        ], 201);
    }

    public function show(Request $request, OrdineB2B $ordine): JsonResponse
    {
        $this->authorizeOrder($request, $ordine);

        return response()->json(['data' => $this->serialize($ordine->load('righe'))]);
    }

    public function update(Request $request, OrdineB2B $ordine): JsonResponse
    {
        $this->authorizeOrder($request, $ordine);
        abort_if($ordine->isPagato(), 422, 'Ordine già pagato, non modificabile.');

        $data = $request->validate([
            'note_agente' => ['nullable', 'string', 'max:2000'],
            'indirizzo_spedizione' => ['nullable', 'string', 'max:500'],
        ]);

        $ordine->update($data);

        return response()->json(['data' => $this->serialize($ordine->fresh('righe'))]);
    }

    public function destroy(Request $request, OrdineB2B $ordine): JsonResponse
    {
        $this->authorizeOrder($request, $ordine);
        abort_if($ordine->isPagato(), 422, 'Ordine già pagato, non annullabile.');

        DB::transaction(function () use ($ordine) {
            foreach ($ordine->righe as $riga) {
                $riga->variante?->increment('quantita', $riga->quantita);
            }
            $ordine->update(['stato' => OrderStatus::Annullato]);
            $ordine->delete();
        });

        return response()->json(['message' => 'Ordine annullato.']);
    }

    /** GET /api/orders/{ordine}/pdf/v2 */
    public function pdf(Request $request, OrdineB2B $ordine)
    {
        $this->authorizeOrder($request, $ordine);
        $ordine->load('righe', 'cliente', 'agente');

        $pdf = Pdf::loadView('pdf.order', ['ordine' => $ordine])->setPaper('a4');

        return $pdf->download("{$ordine->numero}.pdf");
    }

    /** POST /api/orders/{ordine}/checkout-session */
    public function checkoutSession(Request $request, OrdineB2B $ordine): JsonResponse
    {
        $this->authorizeOrder($request, $ordine);
        abort_unless($ordine->metodo_pagamento === PaymentMethod::Stripe, 422, 'Metodo di pagamento non Stripe.');

        $session = $this->stripe->createCheckoutSession($ordine);
        $ordine->update(['stripe_session_id' => $session->id]);

        return response()->json(['url' => $session->url, 'session_id' => $session->id]);
    }

    /** POST /api/orders/{ordine}/verify-stripe-payment */
    public function verifyStripePayment(Request $request, OrdineB2B $ordine): JsonResponse
    {
        $this->authorizeOrder($request, $ordine);
        $sessionId = $request->validate(['session_id' => ['required', 'string']])['session_id'];

        $paid = $this->stripe->confirmSession($ordine, $sessionId);

        return response()->json([
            'paid' => $paid,
            'data' => $this->serialize($ordine->fresh('righe')),
        ]);
    }

    /** POST /api/stripe/webhook */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $this->stripe->handleWebhook($request);

        return response()->json(['received' => true]);
    }

    // ---------------------------------------------------------------------

    private function authorizeOrder(Request $request, OrdineB2B $ordine): void
    {
        abort_unless($ordine->agente_id === $request->user()->id, 403);
    }

    private function serialize(OrdineB2B $ordine): array
    {
        return [
            'id' => $ordine->id,
            'numero' => $ordine->numero,
            'stato' => $ordine->stato->value,
            'metodo_pagamento' => $ordine->metodo_pagamento?->value,
            'subtotale' => (float) $ordine->subtotale,
            'spese_spedizione' => (float) $ordine->spese_spedizione,
            'iva_perc' => (float) $ordine->iva_perc,
            'iva_importo' => (float) $ordine->iva_importo,
            'totale' => (float) $ordine->totale,
            'totale_pezzi' => $ordine->totale_pezzi,
            'data_ordine' => $ordine->data_ordine?->toDateString(),
            'pagato' => $ordine->isPagato(),
            'righe' => $ordine->righe->map(fn ($r) => [
                'prodotto' => $r->prodotto_nome,
                'codice' => $r->prodotto_codice,
                'taglia' => $r->taglia,
                'colore' => $r->colore,
                'quantita' => $r->quantita,
                'prezzo_unitario' => (float) $r->prezzo_unitario,
                'totale_riga' => (float) $r->totale_riga,
            ])->values(),
        ];
    }
}
