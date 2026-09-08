# Integrazione WinMino (ERP)

Fonte: Magis Soluzioni Informatiche — 4 documenti in [`docs/winmino/`](winmino/).
WinMino è il **master** di prodotti, varianti, prezzi, giacenze, clienti, agenti,
stagioni, categorie. Il portale **legge** (GET) e **scrive** ordini + clienti (POST).

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

> **Immagini**: WinMino ha i webservice, ma per CDM le immagini sono gestite nel
> pannello. Se in WinMino ci fossero immagini, l'import può tirarle
> (`GetListaImmaginiArticolo` + `GetImmagineBase64`) — da confermare.

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
- [x] `WinMinoDriver`: **POST completi** (`AddCliente`, `AddDestinazione`, `AddOrdineCliente`) con builder payload, parser `result`, gestione codici errore
- [x] `DataSnapClient` (HTTP, auth, URL) + `MetaDecoder` (`{meta,data}` → righe associative)
- [x] GET: firme pronte, decoder generico; mappatura campi **da completare** con i `meta` reali (punto 1)
- [x] tabella `destinazioni` + model, campi ERP su schema, config `winmino.php`
- [x] job `InviaOrdineErp`, comando `sync:clienti` (upsert quando i GET saranno attivi)
