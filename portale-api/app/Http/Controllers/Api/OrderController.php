<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Mail\NuovoOrdineBackofficeMail;
use App\Models\Carrello;
use App\Models\Cliente;
use App\Models\OrdineB2B;
use App\Models\VarianteProdotto;
use App\Services\ConfermaOrdineCliente;
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
        $utente = $request->user();
        $daCliente = $utente instanceof Cliente;

        if ($daCliente) {
            // il cliente ordina per sé, con il proprio listino; l'ordine è attribuito al suo agente (se ne ha uno)
            $cliente = $utente;
            $agente = $cliente->agente;
            abort_if(
                $request->validated('metodo_pagamento') === PaymentMethod::Contrassegno->value && ! $cliente->contrassegno_abilitato,
                422,
                'Il pagamento in contrassegno non è abilitato per il tuo account.',
            );
        } else {
            $agente = $utente;
            $cliente = Cliente::findOrFail($request->validated('cliente_id'));
            abort_unless($cliente->agente_id === $agente->id, 403, 'Cliente non nel portafoglio agente.');
        }

        $listino = $cliente->listinoEffettivo();

        // pronto consegna e programmato non viaggiano insieme: il carrello diventa uno o due ordini,
        // il pronto con la spedizione calcolata in automatico, il programmato con la spedizione da concordare
        $ordini = DB::transaction(function () use ($request, $agente, $cliente, $listino, $daCliente) {
            $righeInput = collect($request->validated('righe'));
            $varianti = VarianteProdotto::with('prodotto.stagione')
                ->whereIn('id', $righeInput->pluck('variante_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $gruppi = ['pronto' => [], 'programmato' => []];
            foreach ($righeInput as $r) {
                /** @var VarianteProdotto $v */
                $v = $varianti[$r['variante_id']];
                $prezzo = $this->prices->forVariant($v, $listino);
                $gruppi[$v->prodotto->isProgrammato() ? 'programmato' : 'pronto'][] = [
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

            $metodoScelto = PaymentMethod::from($request->validated('metodo_pagamento'));
            $ordini = collect();

            foreach ($gruppi as $tipo => $righe) {
                if ($righe === []) {
                    continue;
                }
                $programmato = $tipo === 'programmato';
                // il programmato non si paga online (totale e spedizione si definiscono poi col commerciale): bonifico
                $metodo = $programmato && $metodoScelto === PaymentMethod::Stripe ? PaymentMethod::Bonifico : $metodoScelto;
                $spedizione = $this->prices->shippingFor(round(collect($righe)->sum('totale_riga'), 2), $programmato);
                $totali = $this->prices->totals($righe, $spedizione);

                $ordine = OrdineB2B::create([
                    'agente_id' => $agente?->id,
                    'cliente_id' => $cliente->id,
                    'da_cliente' => $daCliente,
                    'cliente_nome' => $cliente->denominazione,
                    'cliente_email' => $cliente->email,
                    'cliente_telefono' => $cliente->telefono,
                    'indirizzo_spedizione' => $request->validated('indirizzo_spedizione')
                        ?: trim("{$cliente->indirizzo}, {$cliente->cap} {$cliente->citta} ({$cliente->provincia})", ', '),
                    'stato' => OrderStatus::Ricevuto,
                    'metodo_pagamento' => $metodo,
                    'listino_applicato' => $listino,
                    'programmato' => $programmato,
                    'note_agente' => $request->validated('note_agente'),
                    ...$totali,
                ]);

                $ordine->righe()->createMany($righe);
                $ordini->push($ordine);
            }

            return $ordini;
        });

        // l'ordine è partito: il carrello salvato non serve più (e niente promemoria)
        Carrello::where('cliente_id', $cliente->id)->delete();

        $destinatari = array_filter(array_map('trim', explode(',', (string) config('portale.backoffice_email'))));

        foreach ($ordini as $ordine) {
            // notifica il backoffice CDM (il commerciale carica l'ordine su WinMino)
            if ($destinatari) {
                Mail::to($destinatari)->queue(new NuovoOrdineBackofficeMail($ordine));
            }

            // conferma al cliente subito; con la carta solo quando il pagamento è incassato (vedi StripeService)
            if ($ordine->metodo_pagamento !== PaymentMethod::Stripe) {
                app(ConfermaOrdineCliente::class)->invia($ordine);
            }
        }

        // `data` è l'ordine da pagare per primo (il pronto, se c'è); `orders` li elenca tutti
        $ordini = $ordini->map(fn ($o) => $o->fresh('righe'));

        return response()->json([
            'message' => $ordini->count() > 1 ? 'Ordini inviati con successo!' : 'Ordine inviato con successo!',
            'data' => $this->serialize($ordini->first()),
            'orders' => $ordini->map(fn ($o) => $this->serialize($o))->values(),
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
        $user = $request->user();

        // il cliente vede/gestisce solo i propri ordini; l'agente quelli che gli sono attribuiti
        $ok = $user instanceof Cliente
            ? $ordine->cliente_id === $user->id
            : $ordine->agente_id === $user->id;

        abort_unless($ok, 403);
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
            'programmato' => (bool) $ordine->programmato,
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
