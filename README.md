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

## Gebruik

- **Dashboard (`/index.php`)** — live overzicht van alle meldingen met
  filters op status, prioriteit en hoofdclassificatie, een statusbord met
  tellingen (open / in behandeling / afgehandeld / kritiek-en-open), en
  per regel de naam van de gebruiker die de melding heeft ingevoerd.
- **Nieuwe melding (`/melding_nieuw.php`)** — elke ingelogde gebruiker kan
  een melding aanmaken met hoofd-/subclassificatie; er wordt automatisch
  een uniek meld-ID gegenereerd (bv. `MK-D2-014` = dag 2, 14e melding) en
  de aanmaker wordt vastgelegd.
- **Melddetail (`/melding.php?id=...`)** — classificatie, status en
  prioriteit bijwerken, toewijzen aan een team/persoon, protocollen
  koppelen of loskoppelen, en een logboek met notities bijhouden (elke
  notitie toont wie hem plaatste).
- **Beheer (`/admin/`)** — alleen voor gebruikers met de rol beheerder:
  - **Gebruikers** toevoegen, activeren/deactiveren, van rol wisselen of
    verwijderen.
  - **Classificaties** — hoofd- en subclassificaties aanmaken en
    verwijderen (met eigen kleur per hoofdclassificatie).
  - **Protocollen** aanmaken, bewerken en verwijderen — deze protocollen
    zijn het wat er op de melddetailpagina gekoppeld kan worden.

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
