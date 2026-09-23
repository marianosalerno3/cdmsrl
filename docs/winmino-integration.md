# Integrazione WinMino (ERP)

Fonte: Magis Soluzioni Informatiche — 4 documenti in [`docs/winmino/`](winmino/).
WinMino è il **master** di prodotti, varianti, prezzi, giacenze, clienti, agenti,
stagioni, categorie.

## Perimetro (rev. 2026-09-09)

Il flusso principale è **read-only**: `WinMino → portale → Shopify B2C`.

- **Import** (GET): prodotti, varianti, prezzi, giacenze, clienti, agenti →
  comando `sync:prodotti` (+ `sync:clienti`). Vedi `SyncProdotti` / `SyncClienti`.
- **Ordini**: **restano nel portale**. A ogni nuovo ordine parte una mail al
  backoffice CDM (`BACKOFFICE_EMAIL`, `NuovoOrdineBackofficeMail`); il commerciale
  lo carica manualmente su WinMino. **Nessun push automatico.**
- Il lato **scrittura** (`AddCliente` / `AddDestinazione` / `AddOrdineCliente`)
  è **implementato nel driver ma non collegato** — pronto se in futuro si vuole
  automatizzare.

## Protocollo

- **DataSnap REST** (Delphi). URL: `http://<host>:8080/datasnap/rest/<modulo>/<PREFISSO>_<Funzione>[/parametri]`
- Formati GET via prefisso: **`JSO_`** (JSON compatto — usiamo questo), `JSS_`, `XML_`
- Risposta GET: `{ "meta": [...campi con nome/tipo/dimensione...], "data": [[...],[...]] }` — decodifica **dinamica** dal `meta`, mai posizionale
- Parametri: `/Nome=Valore&Nome2=Valore2` — o solo valore se il parametro è unico
- **Date `dd-mm-yyyy`**, **decimali col punto** (`12345.67`)
- Auth: **username + password** (da richiedere a Magis) — presumibilmente HTTP Basic; **da confermare per i POST**
- ⚠️ **HTTP in chiaro** su porta custom → serve VPN / IP whitelisting tra il server API e WinMino

## Moduli / endpoint

| Operazione | Metodo | Modulo | Funzione |
|---|---|---|---|
| Letture anagrafiche/movimenti | GET | `TsmStandard` | `JSO_Get*` (vedi tabella sotto) |
| Inserimento/modifica cliente | POST | `TDSClientiServerModule` | `AddCliente` |
| Inserimento/modifica destinazione | POST | `TDSClientiServerModule` | `AddDestinazione` |
| Inserimento ordine cliente | POST | `TDSOrdiniCLienti` | `AddOrdineCliente` |

> Il nome del `Server module` per le GET (`TsmStandard`) va confermato sull'installazione CDM.

---

## POST — payload verificati

### `AddCliente`
Upsert per **`CODICEFISCALE`**. Ritorna `{"result":[{"<codiceCliente>":"SUCCESSO"}]}` →
il `<codiceCliente>` (intero) va salvato in `clienti.codice_cliente_erp`.

| Campo WinMino | Tipo | Obbl. | Sorgente portale |
|---|---|---|---|
| `CODICEFISCALE` | VARCHAR(16) | ✅ | `clienti.codice_fiscale` |
| `RAGIONESOCIALE` | VARCHAR(50) | ✅ | `clienti.ragione_sociale` |
| `PARTITAIVA` | VARCHAR(15)¹ | | `clienti.partita_iva` |
| `AGENTE` | INTEGER | | `agenti.codice_agente` |
| `LISTINO` | VARCHAR(6) | | `winmino.listino_map[tipo_listino]` |
| `PAGAMENTO` | VARCHAR(6) | | `winmino.pagamento_map[…]` |
| `NAZIONE` | VARCHAR(6) | | `clienti.nazione` → map |
| `INDIRIZZO` / `LOCALITA` / `PROVINCIA` / `CAP` | VARCHAR(40/40/4/8) | | `clienti.indirizzo/citta/provincia/cap` |
| `TELEFONO` / `CELLULARE` / `EMAIL` | VARCHAR(15)¹ | | `clienti.telefono` / — / `clienti.email` |
| `TIPOLOGIAFE` | VARCHAR(2) | | `PR` (privato) / `PA` (PA) |
| `TIPOLOGIACODICEFE` | VARCHAR(1) | | `P` se si invia PEC, `C` se codice SDI |
| `CODICEDESTINATARIOFE` | VARCHAR(100) | | `clienti.pec` **oppure** `clienti.codice_sdi` (uno solo) |
| `CRITERIOSCONTO` | VARCHAR(1) | | `N`/`A`/`S`/`L` — default `N` |
| `NOTE` | VARCHAR(255) | | — |

¹ **refuso nel doc**: `EMAIL` e gli sconti come `VARCHAR(15)` non hanno senso → confermare con Magis.

### `AddDestinazione`
Upsert per **`CODICE` VARCHAR(6)** — **generato e gestito da noi** (tabella `destinazioni`).

| Campo | Tipo | Obbl. | Sorgente |
|---|---|---|---|
| `CODICE` | VARCHAR(6) | ✅ | `destinazioni.codice` (progressivo/base36) |
| `NOME` | VARCHAR(50) | ✅ | ragione sociale / nome destinazione |
| `CLIENTE` | INTEGER | ✅ | `clienti.codice_cliente_erp` |
| `INDIRIZZO` | VARCHAR(80) | | |
| `LOCALITA`/`PROVINCIA`/`CAP` | VARCHAR(40/4/8) | | |
| `TELEFONO`/`CELLULARE`/`E_MAIL`² | VARCHAR(15/15/50) | | |
| `CODICEFISCALE`/`PARTITAIVA` | VARCHAR(16/15) | | |
| `NOTE` | VARCHAR(255) | | |

² qui il campo è `E_MAIL` (con underscore), in `AddCliente` è `EMAIL`. Ritorna `{"result":[{"0":"SUCCESSO"}]}`.

### `AddOrdineCliente`
Testata + `RIGHE[]` (una per **articolo**) + per riga `VARIANTI[]` (una per **colore/taglia**).

**Testata** — obbligatori in **grassetto**:

| Campo | Tipo | Obbl. | Sorgente |
|---|---|---|---|
| **`CLIENTE`** | INTEGER | ✅ | `clienti.codice_cliente_erp` |
| `AGENTE` | INTEGER | | `agenti.codice_agente` |
| `DESTINAZIONE` | VARCHAR(6) | | `destinazioni.codice` (se spedizione ≠ sede) |
| `PAGAMENTO` | VARCHAR(6) | | `winmino.pagamento_map[metodo_pagamento]` |
| `LISTINO` | VARCHAR(6) | | `winmino.listino_map[listino_applicato]` |
| `STAGIONE` | VARCHAR(6) | | `stagioni.codice` (se allineato a WinMino) |
| `CAMPIONARIO` | VARCHAR(6) | | — (solo se `DICAMPIONARIO=1`) |
| `DATAORDINE` | DATE | | `ordini_b2b.data_ordine` (dd-mm-yyyy) |
| `NUMEROORDINE` | VARCHAR(20) | | `ordini_b2b.numero` |
| `NOTE` | VARCHAR(200) | | `ordini_b2b.note_agente` |
| `SPESETRASPORTO` | FLOAT | | `ordini_b2b.spese_spedizione` |
| `VETTORE` / `PORTO` / `DIVISA` | VARCHAR(6/20/6) | | default da config |
| `DICAMPIONARIO` | 0/1 | | `0` |
| **`IDESTERNO`** (testata) | VARCHAR(20) | | `ordini_b2b.numero` (chiave idempotenza) |

**Riga (`RIGHE[]`)** — obbligatori `ARTICOLO`, `UNITA`, `QUANTITA`, `PREZZO`:

| Campo | Tipo | Sorgente |
|---|---|---|
| `ARTICOLO` | VARCHAR(16) | `prodotti.codice` |
| `UNITA` | VARCHAR(3) | `winmino.unita_default` (es. `NR`) — **da confermare** |
| `QUANTITA` | FLOAT | Σ quantità varianti della riga |
| `PREZZO` | FLOAT | **prezzo di listino a livello articolo** (confermato: nessun prezzo per variante) |
| `SCONTO1/2/3`, `MAGGIORAZIONE` | FLOAT | 0 |
| `PERCENTUALEAGENTE` | FLOAT | `agenti.commissione_perc` |
| `NOTE` | VARCHAR(200) | — |
| `IDESTERNO` (riga) | VARCHAR(20) | `{numero}-{indice}` (UUID troppo lungo) |

**Variante (`VARIANTI[]`)** — se presente, obbligatori `COLORE`, `TAGLIA`, `QUANTITA`:

| Campo | Tipo | Sorgente |
|---|---|---|
| `COLORE` | VARCHAR(20) | **`colori.codice`** (codice WinMino, non il nome) |
| `TAGLIA` | VARCHAR(6) | **`taglie.codice`** |
| `QUANTITA` | FLOAT | `ordine_righe.quantita` della variante |
| `IDESTERNO` (variante) | VARCHAR(20) | `sku` |

**Risposta**: `{"result":[{"0":"SUCCESSO"}]}` oppure codici errore:

| Codice | Significato |
|---|---|
| -1 | valore non specificato `<campo>` |
| -2 | valore inesistente `<campo> <valore>` |
| -3 | errore query DB |
| -4 | valore obbligatorio non specificato |
| -5 | errore in memorizzazione |
| -6 | `RIGHE` assente / non array |
| -7 | `VARIANTI` non array |

Salviamo l'intera risposta in `ordini_b2b.erp_response` (json) e, su `SUCCESSO`, `inviato_erp_at`.

---

## GET — funzioni usate (campi runtime dal `meta`)

| Scopo | Funzione | Parametri |
|---|---|---|
| Prodotti + giacenze (pubblicati) | `EC_GetGeneraleArticoliE` | — |
| Prodotti (incrementale) | `GetArticoli` / `EC_GetArticoli` | `DaData` |
| Colori articolo | `GetColoriArticoli` / `GetColoriArticolo` | (`CodArticolo`) |
| Taglie articolo | `GetTaglieArticoli` / `GetTaglieArticolo` | (`CodArticolo`) |
| Barcode | `GetBarcodeTaglieColori` | (`CodArticolo`,`CodColore`) |
| Giacenze | `GetGiacenze` | (`CodArticolo`,`CodColore`) |
| Disponibile netto e-commerce | `EC_GetSurplusCorrenteE` | — |
| Prezzi listino | `GetListiniPrezzi` | (`CodListino`) |
| Anagrafica colori | `GetListaColori` | — |
| Categorie | `GetCategorieArticoli` | — |
| Stagioni | `GetStagioni` | — |
| Gruppi taglie | `GetGruppiTaglie` | — |
| Clienti | `GetClienti` | `DaData` |
| Destinazioni | `GetDestinazioniDiverseClienti` | `DaData` |
| Agenti | `GetAgenti` | `DaData` |
| Pagamenti | `GetPagamenti` | — |
| Provvigioni | `GetProvvigioniAgentiDaDocumenti` | `DaDataRegistrazione`,`ADataRegistrazione` |
| Ordini (dedup/stato) | `GetOrdiniclienti` | `DaDataModifica*`,`DaDataRegistrazione*`,`ADataRegistrazione*`,`NumeroOrdine`,`RiferimentoCliente` |
| Immagini articolo | `GetListaImmaginiArticolo(Colori/Ex)` + `GetImmagineBase64` | `CodArticolo` |

> **Immagini**: WinMino espone i webservice (`GetListaImmaginiArticolo`,
> `GetListaImmaginiArticoloColori`, `GetListaImmaginiArticoloEx` con `DataAggiornamento`
> per l'incrementale; download con `GetImmagineBase64` o `GetStreamImmagine/<nome>?json=false`
> per lo stream binario). Oggi le immagini si caricano dal pannello; se CDM le
> tiene già in WinMino l'import può essere automatico — **da chiedere a CDM/Magis**.

### Note dall'analisi della doc GET (v. 2023.04.16.011, rev. 2026-09-23)

**Endpoint utili non ancora usati**

| Funzione | Uso possibile |
|---|---|
| `GetArticoliCategorie` (nuova in questa revisione) | legame articolo → categoria (evita di dedurlo da `GetArticoli`) |
| `GetDisponibilitaAScaderePerColore` (`CodArticolo`*, `CodColore`) | disponibilità presente **e futura** per data (ordini fornitori in arrivo): base per gli "ordini programmati" |
| `GetBlocchi` | blocchi cliente; per ordini il valore è 0 nessuna azione · 1 avviso · 2 avviso+conferma · **3 blocca**. Un cliente con blocco 3 non dovrebbe poter ordinare dal portale (verificare nel `meta` di `GetClienti` come il blocco è collegato al cliente) |
| `GetOrdiniclienti` con `RiferimentoCliente` | se il commerciale scrive il numero ordine del portale (`ORD-…`) nel campo RIFCLIENTE di WinMino, il portale può rileggere lo stato dell'ordine (sola lettura) |
| `GetMailClienti`, `GetGerarchiaCommerciale` | email clienti e commerciali interni (destinatari notifiche) |

**Semantica delle varianti `EC_*` (`T` / `E` / `S`)** — stessa formula
*q.tà = disponibile − da evadere ai clienti*, solo depositi con flag
"partecipa al calcolo del surplus (e-commerce)", solo quantità **positive**, ordini
non più vecchi di un anno. Cambia quali ordini si sottraggono:
`T` tutti gli ordini clienti · `E` solo ordini aziendali, **esclusi** quelli ricevuti dall'e-commerce · `S` solo ordini di tipo e-commerce.
(La lettura T/E/S come Tutti/Esclusi/Solo è un'inferenza dalle descrizioni.)
Quale usare per Shopify e quale per il portale B2B è una **decisione da prendere con CDM**:
oggi `sync:prodotti` usa `E`.

**Rischi da verificare sui dati reali**

1. Le `EC_*` restituiscono solo quantità positive: un articolo/variante esaurito
   può **sparire** dalla risposta. L'import deve trattare "assente" come 0, altrimenti la
   giacenza resta all'ultimo valore positivo (rischio vendita di merce esaurita).
2. `sync:prodotti` incrementale usa `GetArticoli` (anagrafica, senza quantità) mentre il
   full usa `EC_GetGeneraleArticoliE` (con quantità): i due percorsi non producono gli stessi campi.
3. `GetListiniPrezzi` e `GetGiacenze` non hanno `DaData`: non esiste un incrementale
   per prezzi e giacenze, serve sempre la lettura completa (con `CodListino` per il solo listino base).
4. `DaData` ha granularità di giorno (`31-01-2010`): l'incrementale deve sovrapporsi di almeno un giorno.
5. `GetMovimentiMagazzinoBarcode` accetta **solo** parametri nominati (`Nome=valore`), non il valore posizionale.
6. Nessuna paginazione documentata sui GET massivi.

---

## Impatto sullo schema del portale

- `colori`, `taglie` → aggiunta colonna **`codice`** (codice WinMino)
- `prodotti` → **`unita`** (default UOM)
- `clienti` → `criterio_sconto`, `zona`, `nazione`, `tipologia_fe`, `tipologia_codice_fe`, `codice_destinatario_fe`
- `ordini_b2b` → `idesterno` (= `numero`), **`erp_response`** (json)
- nuova tabella **`destinazioni`** (`codice` VARCHAR(6) univoco ↔ indirizzo, `cliente_id`)
- config **`winmino.php`**: `listino_map`, `pagamento_map`, `unita_default`, `divisa_default`, `nazione_default`, nomi moduli

---

## Punti aperti (bloccanti per i GET / test end-to-end)

1. **Una risposta di esempio (`meta`+`data`) per ogni GET** usato — o accesso in lettura a un WinMino reale.
2. **Host:porta del WinMino di CDM** + raggiungibilità dal server API (VPN / whitelist; è HTTP).
3. **Credenziali** user/password + conferma metodo auth (Basic? anche sui POST?).
4. **Nome `Server module`** GET sull'installazione CDM (default `TsmStandard`).
5. **Codici di dominio**: `LISTINO` per `standard`/`plus5`; codici `PAGAMENTO`; valore `UNITA`; `NAZIONE`/`DIVISA`/`VETTORE`/`PORTO` di default.
6. Esiste una versione più recente della doc / un OpenAPI? (queste sono 2020–2023).
7. Paginazione / limiti sui GET massivi — non documentati.

## Stato implementazione

- [x] `ErpManager` + contratto `ErpDriver` + `NullErpDriver`
- [x] `DataSnapClient` (HTTP Basic, URL, retry) + `MetaDecoder` (`{meta,data}` → righe associative)
- [x] `WinMinoDriver` — **GET** (18 funzioni, decoder generico) + **POST** (`AddCliente` / `AddDestinazione` / `AddOrdineCliente`, builder payload, parser `result` / codici −1..−7). Le POST **non sono collegate a nessun flusso** (vedi Perimetro).
- [x] `SyncProdotti` (`sync:prodotti`), `SyncClienti` (`sync:clienti`) — mappatura campi `pick(...)` **da confermare con i `meta` reali** (punto 1)
- [x] tabella `destinazioni` + model, campi ERP su schema, config `winmino.php`
- [x] notifica ordine al backoffice CDM (`NuovoOrdineBackofficeMail`, `BACKOFFICE_EMAIL`)
- [ ] finalizzare la mappatura GET con risposte reali; codici listino/pagamento/UM di CDM
