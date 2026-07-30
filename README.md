# Meldkamer systeem

Een PHP-applicatie om meldingen tijdens een meerdaags evenement bij te houden,
met een uniek meld-ID per melding, koppeling van protocollen aan meldingen,
en een beheerpaneel voor het aanmaken/verwijderen van categorieen en het
beheren van protocollen.

## Vereisten

- PHP 8.0 of hoger, met de `pdo_mysql` extensie
- Een MySQL of MariaDB database (mag extern gehost zijn)
- Een webserver (Apache/Nginx) of `php -S` voor lokaal testen

## Installatie

1. **Database aanmaken.** Importeer `database.sql` in je (externe) database, bijvoorbeeld:
   ```
   mysql -h <host> -u <gebruiker> -p <database_naam> < database.sql
   ```
   Dit maakt alle tabellen aan en zet er 4 voorbeeldcategorieen in (Medisch,
   Beveiliging, Techniek, Logistiek) die je meteen via het beheerpaneel kan
   aanpassen of verwijderen.

   **Had je al een eerdere versie geinstalleerd** (met een `admins`-tabel in
   plaats van `gebruikers`)? Voer dan in plaats daarvan eenmalig
   `migratie_gebruikersrollen.sql` uit op je bestaande database om je
   installatie bij te werken naar het nieuwe gebruikerssysteem met rollen.

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
  wijzigen, categorieen aanmaken/verwijderen, en protocollen beheren.
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

## Gebruik

- **Dashboard (`/index.php`)** — live overzicht van alle meldingen met
  filters op status, prioriteit en categorie, een statusbord met tellingen
  (open / in behandeling / afgehandeld / kritiek-en-open), en per regel de
  naam van de gebruiker die de melding heeft ingevoerd.
- **Nieuwe melding (`/melding_nieuw.php`)** — elke ingelogde gebruiker kan
  een melding aanmaken; er wordt automatisch een uniek meld-ID gegenereerd
  (bv. `MK-D2-014` = dag 2, 14e melding) en de aanmaker wordt vastgelegd.
- **Melddetail (`/melding.php?id=...`)** — status en prioriteit bijwerken,
  toewijzen aan een team/persoon, protocollen koppelen of loskoppelen, en
  een logboek met notities bijhouden (elke notitie toont wie hem plaatste).
- **Beheer (`/admin/`)** — alleen voor gebruikers met de rol beheerder:
  - **Gebruikers** toevoegen, activeren/deactiveren, van rol wisselen of
    verwijderen.
  - **Categorieen** aanmaken en verwijderen (met eigen kleur).
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
