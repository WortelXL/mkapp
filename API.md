# Meldkamer API — overzicht

Dit document beschrijft alles wat je kan met de API van het meldkamersysteem.
Er is op dit moment één endpoint: het aanmaken van een melding zonder in te
loggen (bedoeld voor een Stream Deck-knop, een script, of een andere externe
koppeling).

---

## Authenticatie

Elke aanroep vereist een **API-token**, gekoppeld aan een gebruikersaccount.
Dat token identificeert wie/welke knop de melding aanmaakt (net zoals een
wachtwoord — behandel het ook zo, en deel het niet).

**Token aanmaken/beheren:** Beheer &rarr; Gebruikers (`/admin/gebruikers.php`,
alleen voor beheerders). Tokens zijn alleen beschikbaar voor accounts met
de rol **beheerder** — bij medewerkers en viewers is er geen token-optie
zichtbaar. Bij een beheerder-account kun je een token automatisch laten
genereren ("Token genereren"), of zelf een specifieke waarde invullen
("...of zelf token invullen") — handig om een token te hergebruiken dat
je al ergens anders had geconfigureerd. Intrekken kan altijd met de knop
"Intrekken".

**Token meesturen**, op een van deze twee manieren:

- Als header (voorkeur): `Authorization: Bearer <token>`
- Als veld in de body: `token=<token>` — handig als je tool/plugin geen
  aangepaste headers ondersteunt.

Geen (geldig) token meegestuurd? Dan antwoordt de API met **401**.

---

## Endpoint: melding aanmaken

```
POST /api/melding.php
Content-Type: application/json
   -- of --
Content-Type: application/x-www-form-urlencoded  (of multipart/form-data)
```

Beide werken; kies wat je koppeling het makkelijkst aanbiedt. Veel
Stream Deck-plugins (zoals "API Request") sturen standaard JSON.

### Velden

| Veld | Verplicht | Omschrijving |
|------|-----------|--------------|
| `titel` | Nee | Eigen titel. Ontbreekt dit, dan wordt de titel automatisch samengesteld uit de classificatie: `"Hoofdclassificatie - Subclassificatie"`, of alleen de hoofdclassificatie zonder sub, of `"Ongeclassificeerde melding"` zonder classificatie. |
| `classificatie` | Nee | Naam van een hoofd- of subclassificatie, bv. `medisch` of `reanimatie`. Werkt op dezelfde manier als het zoekcommando op het dashboard: exacte naam gaat voor, anders een gedeeltelijke match. Subclassificaties gaan voor hoofdclassificaties bij een match. |
| `subclassificatie` | Nee | Naam van een subclassificatie, direct en specifiek gezocht (niet gecombineerd zoals bij `classificatie`). Wint van `classificatie` als beide zijn meegestuurd — handig als je zeker wil zijn van de juiste subclassificatie zonder kans op een verkeerde combinatie-match. De bijbehorende hoofdclassificatie wordt automatisch overgenomen. |
| `status` | Nee | Sleutel van een status (Beheer &rarr; Statussen): `open` / `in_behandeling` / `afgehandeld` / `geannuleerd`, of een eigen aangemaakte status. Standaard `open`. Handig als je bv. een melding met terugwerkende kracht of als test meteen als afgehandeld wil aanmaken. |
| `prioriteit` | Nee | `laag` / `normaal` / `hoog` / `kritiek`. Ontbreekt dit, dan wordt de standaardprioriteit van de gevonden subclassificatie gebruikt (indien ingesteld via Beheer &rarr; Classificaties), anders `normaal`. |
| `locatie` | Nee | Vrije tekst. Kan ook via een `;locatienaam`-commando in `omschrijving` worden gezet (zie hieronder) — dat commando overschrijft dit veld bij een match. |
| `omschrijving` | Nee | Vrije tekst. Ondersteunt het `;locatienaam`-commando: staat er ergens in de tekst `;` gevolgd door een vooraf ingestelde locatienaam (Beheer &rarr; Locaties), dan wordt `locatie` automatisch op die gevonden locatie gezet. |
| `gemeld_door` | Nee | Wie de melding doorgaf. Standaard: `"Stream Deck (naam van de gekoppelde gebruiker)"`. |

Alle velden zijn dus optioneel behalve het token — een lege aanroep met
alleen een geldig token maakt een "Ongeclassificeerde melding" aan.

### Antwoord bij succes — `201 Created`

```json
{
  "success": true,
  "id": 42,
  "meld_id": "MK-D2-014",
  "status": "open",
  "protocollen": ["Protocol reanimatie"]
}
```

`protocollen` toont de titels van protocollen die automatisch zijn
gekoppeld omdat ze bij de gekozen subclassificatie horen (ingesteld via
Beheer &rarr; Protocollen). Is er geen subclassificatie gekozen of matcht
er geen protocol, dan is dit een lege lijst `[]`. Automatisch gekoppelde
protocollen kun je op de melddetailpagina gewoon weer loskoppelen, of
handmatig een ander protocol toevoegen.

### Antwoord bij een fout

| Status | Betekenis | Voorbeeld |
|--------|-----------|-----------|
| 401 | Geen of ongeldig/ingetrokken/gedeactiveerd token | `{"success": false, "error": "Ongeldig of inactief API-token."}` |
| 405 | Verkeerde HTTP-methode (alleen POST wordt ondersteund) | `{"success": false, "error": "Alleen POST-verzoeken worden ondersteund."}` |
| 422 | Validatiefout in de meegestuurde velden | `{"success": false, "error": "..."}` |

---

## Voorbeelden

### curl (form-encoded)

```bash
curl -X POST https://jouw-domein/api/melding.php \
  -H "Authorization: Bearer <token>" \
  -d "classificatie=reanimatie" \
  -d "locatie=Podium 1" \
  -d "prioriteit=kritiek"
```

### curl (JSON)

```bash
curl -X POST https://jouw-domein/api/melding.php \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"classificatie": "reanimatie", "locatie": "Podium 1", "prioriteit": "kritiek"}'
```

Alleen een classificatie, verder alles automatisch (titel, prioriteit via
de standaardprioriteit van die subclassificatie):

```bash
curl -X POST https://jouw-domein/api/melding.php \
  -H "Authorization: Bearer <token>" \
  -d "classificatie=agressie"
```

Locatie automatisch laten bepalen via het `;`-commando in de omschrijving,
in plaats van een los `locatie`-veld:

```bash
curl -X POST https://jouw-domein/api/melding.php \
  -H "Authorization: Bearer <token>" \
  -d "omschrijving=Bezoeker onwel geworden ;podium1" \
  -d "classificatie=medisch"
```

Specifieke subclassificatie kiezen (los van `classificatie`), en meteen
als "in behandeling" aanmaken in plaats van "open":

```bash
curl -X POST https://jouw-domein/api/melding.php \
  -H "Authorization: Bearer <token>" \
  -d "subclassificatie=reanimatie" \
  -d "status=in_behandeling" \
  -d "locatie=Podium 1"
```

### Stream Deck

Gebruik de ingebouwde "Website"-actie (of "System: Website"):

- **URL**: `https://jouw-domein/api/melding.php`
- **Methode**: `POST`
- **Header**: `Authorization: Bearer <token>`
- **Body (form-encoded)**: bv. `classificatie=reanimatie` + `locatie=Podium 1` + `prioriteit=kritiek`

Voor elk scenario ("Medisch kritiek", "Beveiliging vermist persoon", ...)
maak je een aparte knop met een net iets andere combinatie van
`classificatie`, `locatie` en `prioriteit`. Zie ook `streamdeck/README.md`
in het project voor een stap-voor-stap-versie van deze uitleg.

---

## Zichtbaarheid na aanmaken

Een melding die via de API binnenkomt, staat direct in de database en is
dus meteen zichtbaar voor iedereen die inlogt. Voor mensen die het
dashboard al open hebben staan: dat ververst zichzelf automatisch (interval
in te stellen per gebruiker via `/profiel.php`), en speelt optioneel een
geluidsignaal af bij een nieuwe open/in-behandeling-melding.

## Wat de API (nog) niet kan

- Een bestaande melding bijwerken, afsluiten, of van notities voorzien.
- Gebruikers, classificaties, protocollen of locaties beheren.
- Meldingen ophalen/lezen (alleen aanmaken, geen "GET"-endpoint).

Laat het weten als je een van deze zou willen — dat is goed uit te breiden
op dezelfde manier als het huidige endpoint.
