# Meldkamer systeem

Een PHP-applicatie om meldingen tijdens een meerdaags evenement bij te houden,
met een uniek meld-ID per melding, een tweeledige classificatie
(hoofdclassificatie + subclassificatie), koppeling van protocollen aan
meldingen, en een beheerpaneel om dit alles in te richten.

## Snel starten met Docker

De eenvoudigste manier om het systeem te proberen of te draaien is met Docker
Compose — dit start zowel de applicatie als een MariaDB-database, en
importeert automatisch het schema uit `database.sql`.

```bash
docker compose up -d --build
```

Ga daarna naar **http://localhost:8080**. Zolang er nog geen gebruiker
bestaat, kom je automatisch op de setup-pagina terecht om het eerste
beheerdersaccount aan te maken.

**Instellingen aanpassen** doe je in `docker-compose.yml`, onder
`environment:` bij de `app`-service (databasegegevens, evenementnaam,
startdatum, aantal dagen) en bij de `db`-service (wachtwoorden). Wijzig in
elk geval de wachtwoorden voordat je dit ergens publiek draait. Na een
wijziging herstart je met:

```bash
docker compose up -d --build
```

**Gegevens blijven bewaard** in een Docker-volume (`meldkamer_db_data`),
ook als je de containers stopt of herbouwt. Wil je helemaal opnieuw
beginnen (let op: dit verwijdert alle meldingen en gebruikers)?

```bash
docker compose down -v
```

**Logs bekijken:**
```bash
docker compose logs -f app
```

Wil je de applicatie liever rechtstreeks op een server draaien zonder
Docker (bijvoorbeeld met een externe/gehoste database)? Volg dan de
handmatige installatie hieronder.

## Handmatige installatie (zonder Docker)

### Vereisten

- PHP 8.0 of hoger, met de `pdo_mysql` extensie
- Een MySQL of MariaDB database (mag extern gehost zijn)
- Een webserver (Apache/Nginx) of `php -S` voor lokaal testen

### Stappen

1. **Database aanmaken.** Importeer `database.sql` in je (externe) database, bijvoorbeeld:
   ```
   mysql -h <host> -u <gebruiker> -p <database_naam> < database.sql
   ```
   Dit maakt alle tabellen aan en zet er 4 voorbeeld-hoofdclassificaties
   (Medisch, Beveiliging, Techniek, Logistiek) met bijbehorende
   subclassificaties in, die je meteen via het beheerpaneel kan aanpassen
   of verwijderen.

   **Had je al een eerdere versie geinstalleerd?** Voer dan de migratiescripts
   uit die van toepassing zijn, in deze volgorde (overslaan wat je al eerder
   hebt gedraaid):
   1. `migratie_gebruikersrollen.sql` — als je nog een `admins`-tabel had
      in plaats van `gebruikers`.
   2. `migratie_classificaties.sql` — als je nog een platte `categorieen`-
      tabel had in plaats van hoofd-/subclassificaties.

2. **Configuratie instellen.** Kopieer `config.example.php` naar `config.php`
   en vul de gegevens van je externe database in:
   ```
   cp config.example.php config.php
   ```
   ```php
   define('DB_HOST', 'jouw-database-host');
   define('DB_NAME', 'meldkamer');
   define('DB_USER', 'gebruikersnaam');
   define('DB_PASS', 'wachtwoord');
   ```
   Pas ook `EVENT_NAAM`, `EVENT_START_DATUM` en `EVENT_AANTAL_DAGEN` aan naar
   jouw evenement. `EVENT_START_DATUM` (yyyy-mm-dd) bepaalt vanaf wanneer
   dag 1 van het meld-ID (bv. `MK-D1-001`) begint te tellen.
   `config.php` staat in `.gitignore` zodat je echte wachtwoorden nooit
   in git terechtkomen.

3. **Bestanden op de server plaatsen** zodat de map met `index.php` de
   document root is (of een subfolder daarvan met de juiste paden).

4. **Eerste beheerder aanmaken.** Ga naar `/admin/login.php`. Zolang er nog
   geen enkele gebruiker bestaat, word je automatisch doorgestuurd naar
   `/admin/setup.php` om het eerste account (naam, gebruikersnaam en
   wachtwoord) aan te maken. Dit account krijgt automatisch de rol
   **beheerder**. Daarna verdwijnt deze pagina vanzelf.

## Gebruikers & rollen

De hele applicatie vereist nu een login. Er zijn twee rollen:

- **Beheerder** — mag alles wat een medewerker ook mag, en heeft daarnaast
  toegang tot het beheerpaneel: gebruikers toevoegen/verwijderen/rol
  wijzigen, hoofd- en subclassificaties beheren, en protocollen beheren.
- **Medewerker** — kan inloggen, het dashboard bekijken, meldingen aanmaken
  en bijwerken, notities toevoegen en protocollen aan meldingen koppelen,
  maar heeft geen toegang tot het beheerpaneel.
- **Viewer** — mag alleen de Overview-pagina bekijken en het eigen profiel
  (verversingstijd/geluid) aanpassen. Dashboard, nieuwe melding aanmaken,
  melddetail, archief en beheer zijn niet toegankelijk — een viewer die
  toch naar zo'n URL navigeert wordt automatisch teruggestuurd naar
  Overview. Handig voor een scherm dat alleen hoeft mee te kijken, zonder
  dat diegene iets kan aanpassen. Een API-token van een viewer-account kan
  ook geen meldingen aanmaken.

Beheerders voegen nieuwe gebruikers toe via **Beheer &rarr; Gebruikers**
(`/admin/gebruikers.php`): naam, gebruikersnaam, tijdelijk wachtwoord en rol.
Gebruikers kunnen daar ook gedeactiveerd (in plaats van meteen verwijderd),
van rol gewisseld, of verwijderd worden. Het systeem voorkomt dat je de
laatste actieve beheerder deactiveert of verwijdert, zodat je nooit buiten
het beheerpaneel gesloten raakt.

Elke melding en elke notitie registreert automatisch **wie** hem heeft
aangemaakt of bijgewerkt (op basis van de ingelogde gebruiker) — dit is
zichtbaar per regel op het dashboard ("ingevoerd door ...") en op de
melddetailpagina.

## Classificatie: hoofd- en subclassificatie

Elke melding kan worden ingedeeld met een **hoofdclassificatie** (bv.
"Medisch", eigen kleur) en optioneel een **subclassificatie** daaronder
(bv. "Reanimatie", "EHBO klein letsel"). Een subclassificatie hoort altijd
bij precies 1 hoofdclassificatie — kies je in het formulier eerst de
hoofdclassificatie, dan ververst automatisch de lijst met bijpassende
subclassificaties. Het losse "categorie"-veld van eerdere versies bestaat
niet meer.

Beheerders richten dit in via **Beheer &rarr; Classificaties**
(`/admin/classificaties.php`): hoofdclassificaties aanmaken/verwijderen
(met kleur), en per hoofdclassificatie de bijbehorende subclassificaties
aanmaken/verwijderen. Een hoofdclassificatie verwijderen verwijdert ook
de subclassificaties eronder; meldingen met die classificatie blijven
gewoon bestaan, maar verliezen de koppeling.

**Titel van een melding.** Er is geen los titelveld meer bij het aanmaken
van een melding — de titel wordt automatisch samengesteld als
"Hoofdclassificatie - Subclassificatie" (bv. "Medisch - Reanimatie"), of
alleen de hoofdclassificatie als er geen subclassificatie gekozen is, of
"Ongeclassificeerde melding" als er helemaal geen classificatie is gekozen.
Wijzig je de classificatie later op de melddetailpagina, dan wordt de
titel automatisch mee bijgewerkt.

## Gebruik

- **Dashboard (`/index.php`)** — live overzicht van alle meldingen met
  filters op status, prioriteit en hoofdclassificatie, een statusbord met
  tellingen (open / in behandeling / afgehandeld / kritiek-en-open), en
  per regel de naam van de gebruiker die de melding heeft ingevoerd. Klik
  op het statuslabel van een melding om de status direct te wijzigen,
  zonder de melding te hoeven openen.
- **Overview (`/overview.php`)** — passief overzichtsscherm (bv. voor op
  een extra beeldscherm in de meldkamer) met dezelfde actieve meldingen en
  hetzelfde statusbord als het dashboard, maar zonder filters en zonder
  door te kunnen klikken naar een melding. Per melding staat een
  schakelaartje "Logboek" dat de omschrijving en notities direct in de
  rij zelf in-/uitklapt. Toegankelijk voor alle rollen; de rol "Viewer"
  komt hier automatisch op uit en kan verder nergens anders komen.
- **Nieuwe melding (`/melding_nieuw.php`)** — elke ingelogde gebruiker kan
  een melding aanmaken met hoofd-/subclassificatie; er wordt automatisch
  een uniek meld-ID gegenereerd (bv. `MK-D2-014` = dag 2, 14e melding) en
  de aanmaker wordt vastgelegd.
- **Melddetail (`/melding.php?id=...`)** — classificatie, status en
  prioriteit bijwerken, toewijzen aan een team/persoon, protocollen
  koppelen of loskoppelen, en een logboek met notities bijhouden (elke
  notitie toont wie hem plaatste). Het logboek staat bovenaan en is net
  als op Overview in-/uit te klappen via een schakelaartje (standaard
  uitgeklapt). Protocollen die aan de gekozen
  subclassificatie zijn gekoppeld (via Beheer &rarr; Protocollen) worden
  automatisch aan de melding gekoppeld zodra die subclassificatie gekozen
  wordt — zowel bij het aanmaken als bij het achteraf wijzigen van de
  classificatie. Dit gebeurt naast de handmatige koppeling: je kunt een
  automatisch gekoppeld protocol gewoon loskoppelen, of zelf een ander
  protocol toevoegen, zoals altijd.
- **Beheer (`/admin/`)** — alleen voor gebruikers met de rol beheerder:
  - **Gebruikers** toevoegen, activeren/deactiveren, van rol wisselen of
    verwijderen.
  - **Classificaties** — hoofd- en subclassificaties aanmaken en
    verwijderen (met eigen kleur per hoofdclassificatie).
  - **Protocollen** aanmaken, bewerken en verwijderen, gekoppeld aan een
    subclassificatie — deze protocollen zijn het wat automatisch (en
    handmatig) aan een melding gekoppeld kan worden. Per protocol kun je
    ook tot **5 hyperlinks** naar extern naslag (draaiboek, plattegrond,
    documentatie) toevoegen, elk met een zelf te kiezen knoptekst. Een
    link-knop verschijnt op de melddetailpagina alleen als je 'm ook echt
    hebt ingevuld — leeg blijft onzichtbaar.

## API: meldingen aanmaken vanaf een Stream Deck of andere koppeling

Naast de webinterface is er een eenvoudige API om meldingen aan te maken
zonder in te loggen — bedoeld voor een Stream Deck-knop, een script, of
een andere externe koppeling.

**1. Token aanmaken.** Ga als beheerder naar **Beheer &rarr; Gebruikers**
en klik bij de betreffende gebruiker op "Token genereren". Het token
identificeert die gebruiker (zo zie je later wie/welke knop een melding
heeft aangemaakt) en werkt als een wachtwoord — behandel het ook zo.

**2. Aanroepen.** `POST` naar `/api/melding.php` met het token in de
header (`Authorization: Bearer <token>`) of als veld `token` in de body
(handig als je plugin geen custom headers ondersteunt). Alle velden zijn
optioneel — meestal volstaat `classificatie`:

```bash
curl -X POST https://jouw-domein/api/melding.php \
  -H "Authorization: Bearer <token>" \
  -d "classificatie=reanimatie" \
  -d "locatie=Podium 1"
```

Beschikbare velden:

| Veld            | Verplicht | Omschrijving |
|-----------------|-----------|--------------|
| `titel`         | Nee       | Korte omschrijving. Ontbreekt dit, dan wordt de titel automatisch "Hoofdclassificatie - Subclassificatie" |
| `classificatie` | Nee       | Naam van een hoofd- of subclassificatie (bv. `medisch` of `reanimatie`) — wordt op dezelfde manier herkend als het zoekcommando op het dashboard |
| `subclassificatie` | Nee    | Naam van een subclassificatie, direct en specifiek. Wint van `classificatie` als beide zijn meegestuurd; de hoofdclassificatie wordt automatisch overgenomen |
| `status`        | Nee       | `open` / `in_behandeling` / `afgehandeld` / `geannuleerd`. Standaard `open` |
| `prioriteit`    | Nee       | `laag` / `normaal` / `hoog` / `kritiek`. Ontbreekt dit, dan wordt de standaardprioriteit van de gevonden subclassificatie gebruikt, anders `normaal` |
| `locatie`       | Nee       | |
| `omschrijving`  | Nee       | Ondersteunt ook het `;locatienaam`-commando |
| `gemeld_door`   | Nee       | Standaard "Stream Deck (naam gebruiker)" |

Bij succes antwoordt de API met `201` en JSON: `{"success": true, "id": 42, "meld_id": "MK-D2-014"}`.
Bij een fout (bv. ontbrekende titel, ongeldig token): een 4xx-status met
`{"success": false, "error": "..."}`.

**3. Stream Deck instellen.** Gebruik de ingebouwde "Website"-actie (of de
"System: Website"-actie uit de Stream Deck-software) op een knop, stel als
methode `POST` in, vul de URL, header en velden hierboven in. Voor meerdere
scenario's (bv. "Medisch kritiek" / "Beveiliging vermist persoon") maak je
gewoon meerdere knoppen met een net iets andere combinatie van `titel`,
`classificatie` en `prioriteit`.

**Nieuwe meldingen zichtbaar voor anderen.** Het dashboard ververst
zichzelf automatisch (interval instelbaar per gebruiker, zie hieronder),
zodat een melding die via de API of door een collega wordt aangemaakt
vanzelf verschijnt bij iedereen die het dashboard open heeft staan —
niemand hoeft handmatig te verversen.

## Archief exporteren (CSV/PDF)

Op het archief (`/archief.php`) staat een exportbalk bovenaan de lijst:

- **Exporteer gefilterd** (CSV of PDF) neemt alle actieve filters mee —
  status, hoofd-/subclassificatie, prioriteit, label en zoekterm — dus je
  bepaalt de inhoud van het bestand door eerst de filters op de pagina in
  te stellen. Dit werkt ook als er meer resultaten zijn dan er op het
  scherm getoond worden (de 150-resultatenlimiet op het scherm geldt niet
  voor de export).
- **Exporteer selectie** (CSV of PDF) gebruikt de selectievakjes vóór elke
  melding — vink er één of meerdere aan (of "Alles selecteren" voor de
  hele huidige lijst) en exporteer alleen die meldingen, ongeacht de
  filters.

Beide formaten bevatten dezelfde basisgegevens: meld-ID, titel, hoofd-/
subclassificatie, prioriteit, status, locatie, gemeld door, ingevoerd
door, laatst bijgewerkt door, tijdstippen en labels. Daarnaast bevatten
beide ook de **omschrijving**, het **volledige logboek** (elke notitie
met tijdstip en auteur), de **gekoppelde protocollen** (titel + volledige
inhoud), de **subtaken** van die protocollen en de **losse taken** per
melding — allemaal met afvinkstatus, en bij een afgevinkte taak ook
tijdstip en wie 'm afvinkte:

- De **CSV** heeft hiervoor 5 extra kolommen (Omschrijving, Logboek,
  Protocollen, Subtaken, Losse taken) — regels staan onder elkaar binnen
  de cel, elke taak begint met `[x]` (afgevinkt) of `[ ]` (nog niet).
- De **PDF** is geen platte tabel meer, maar een leesbaar rapport met een
  apart blok per melding (kenmerken, statusverloop, omschrijving, logboek,
  protocollen met hun subtaken, losse taken), met automatische
  regelterugloop en paginawissels waar nodig.

**Statusverloop & doorlooptijden.** Elke keer dat de status van een
melding wijzigt (via de melddetailpagina, de snelle statuswijziging op
het dashboard, of bij het aanmaken via de webinterface/API) wordt dat
gelogd met tijdstip en wie het deed. In de export zie je daardoor per
melding hoe lang elke status heeft geduurd (bv. "Open: 14:02 tot 14:35
(33m)") en de totale doorlooptijd van aanmaken tot afronden. Meldingen
die al bestonden vóórdat deze functie werd toegevoegd, hebben geen
historische gegevens — daar tonen alleen de aanmaak- en laatst-bijgewerkt-
tijdstippen zoals voorheen.

Deze exportfunctie werkt met een eigen, lichte PDF-generator
(`includes/minipdf.php`) zonder externe library of Composer-afhankelijkheid
— hoeft dus niet apart geïnstalleerd te worden.

## Statussen

De 4 ingebouwde statussen (open, in behandeling, afgehandeld, geannuleerd)
kun je via **Beheer &rarr; Statussen** (`/admin/statussen.php`) aanpassen
qua naam, kleur en categorie, maar niet verwijderen — dat voorkomt dat
bestaande logica (dashboard/archief) zonder geldige status komt te zitten.
Daarnaast kun je **eigen statussen** toevoegen, bv. "Wacht op onderdelen"
of "Doorverwezen". Elke status (ingebouwd of eigen) krijgt een categorie:

- **Actief** — telt mee op het dashboard en de Overview-pagina.
- **Afgerond** — telt mee in het archief.

Een eigen status verwijderen kan alleen als er geen enkele melding meer
die status heeft (anders eerst die meldingen naar een andere status
zetten). Overal waar een status gekozen of getoond wordt — dashboard,
archief, melddetail, Overview, export, de Stream Deck-API — werkt
automatisch met de volledige lijst, dus ook met je eigen toegevoegde
statussen.

## Labels (later opvolgen)

Beheerders maken labels aan via **Beheer &rarr; Labels**
(`/admin/labels.php`) — naam + kleur, bv. "Later opvolgen" of "Schade
gemeld bij verzekering". Labels zijn onafhankelijk van de status van een
melding: je kunt ze op zowel actieve als afgeronde (gearchiveerde)
meldingen zetten.

Op de melddetailpagina (`/melding.php?id=...`) staat een "Labels"-paneel
bovenaan waar je labels kan toevoegen en weer verwijderen — een melding
kan meerdere labels tegelijk hebben. Labels zijn ook zichtbaar als chips op
zowel het dashboard als het archief, en in het **archief** kun je er
specifiek op filteren (naast categorie en prioriteit) om snel te zien welke
afgeronde meldingen nog opvolging nodig hebben.

## Vooraf ingestelde locaties

Beheerders kunnen via **Beheer &rarr; Locaties** (`/admin/locaties.php`)
een lijst met vaste locaties bijhouden (bv. "Podium 1", "Ingang Noord"),
inclusief een optionele beschrijving.

Typ je ergens in de **omschrijving** bij het aanmaken van een nieuwe
melding, of in een **notitie** op de melddetailpagina, een `;` gevolgd door
(een deel van) een locatienaam — bv. `Ambulance ter plaatse ;podium1` —
dan wordt het locatieveld van de melding automatisch bijgewerkt naar de
gevonden locatie. Exacte namen gaan voor; is er geen exacte match, dan
wordt een gedeeltelijke match gebruikt. Matcht niets, dan gebeurt er
stilzwijgend niets (de tekst zelf blijft gewoon staan zoals getypt).

## Losse taken

Naast subtaken die bij een protocol horen, kan een melding ook eigen,
losse taken hebben — een simpel to-do-lijstje, onafhankelijk van elk
protocol. Handig voor iets dat alleen voor déze ene melding relevant is.
Te vinden op de melddetailpagina, in het paneel "Losse taken": toevoegen,
aanvinken en verwijderen kan direct, zonder ergens anders naartoe te gaan.

## Toewijzing: Toegewezen aan &amp; Toegewezen centralist

Op de melddetailpagina, onder "Classificatie & status", staan twee
doorzoekbare dropdowns (typen filtert de lijst):

- **Toegewezen aan** — wie de melding in het veld oppakt. Gekoppeld aan de
  **crew** (zie hieronder) — contactpersonen zonder inlog.
- **Toegewezen centralist** — welke centralist (meldkamermedewerker) de
  coördinatie doet. Gekoppeld aan een echt, ingelogd **gebruikers**account.

Beide zijn optioneel — "— Niemand toegewezen —" laat het veld leeg.

## Crew (contactpersonen zonder login)

Beheer &rarr; Crew (`/admin/crew.php`) is een eenvoudige telefoonlijst:
naam, functie en telefoonnummer, zonder dat deze mensen kunnen inloggen op
het systeem. Bedoeld voor bijvoorbeeld EHBO'ers, beveiligers of technici
die je aan een melding wil koppelen via "Toegewezen aan", maar die zelf
niets in de applicatie hoeven te doen. Op de melddetailpagina is het
telefoonnummer direct klikbaar (`tel:`-link).

Elke gebruiker (die wél inlogt) kan ook een **functie** hebben (bv.
"Centralist"), in te stellen via **Beheer &rarr; Gebruikers** — zowel bij
het aanmaken van een nieuwe gebruiker als achteraf, inline per rij. De
functie wordt getoond naast de naam in de "Toegewezen centralist"-dropdown.

## Persoonlijke instellingen

Elke gebruiker heeft een eigen instellingenpagina, bereikbaar door op de
eigen naam te klikken rechtsboven in de balk (`/profiel.php`):

- **Automatisch verversen elke ...** — interval voor het dashboard en de
  melddetailpagina (uit, 10/15/20/30/60 seconden). Pauzeert altijd vanzelf
  zolang iemand aan het typen is, ongeacht deze instelling.
- **Geluid bij een nieuwe melding** — aan/uit. Werkt alleen zolang het
  dashboard open staat in de browser; sommige browsers blokkeren geluid
  totdat er ergens op de pagina is geklikt (browserbeveiliging tegen
  autoplay, geen bug).

Deze instellingen zijn per account en beïnvloeden niemand anders.

## Beveiliging & aanpassingen

- Wachtwoorden worden gehasht opgeslagen (`password_hash`), er wordt geen
  platte tekst bewaard.
- Alle queries gebruiken *prepared statements* (PDO) tegen SQL-injectie.
- Elke pagina behalve de inlogpagina vereist een ingelogde gebruiker
  (`vereis_login()`); het beheerpaneel vereist bovendien de rol beheerder
  (`vereis_beheerder()`). Zie `includes/functions.php` als je dit wilt
  aanpassen.
- Overweeg de site achter HTTPS te draaien, zeker omdat er wachtwoorden
  worden verzonden bij het inloggen.
