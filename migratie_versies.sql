-- ============================================================
-- MIGRATIE: versiebeheer / wijzigingenlog
--
-- Dit bestand kun je gewoon steeds opnieuw draaien bij elke
-- update -- bestaande versienummers worden overgeslagen
-- (INSERT IGNORE), alleen de nieuwe rij(en) worden toegevoegd.
-- Geen losse migratie_versie_vX.X.X.sql-bestanden meer nodig.
-- ============================================================

CREATE TABLE IF NOT EXISTS versies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    versienummer VARCHAR(20) NOT NULL,
    datum VARCHAR(50) NOT NULL,
    wijzigingen TEXT NOT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY versienummer_uniek (versienummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Voegt de unieke sleutel toe als de tabel al bestond van vóór
-- deze wijziging (zonder deze stap zou INSERT IGNORE hieronder
-- niets kunnen negeren en dus dubbele rijen aanmaken)
SET @unique_bestaat = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE() AND table_name = 'versies' AND index_name = 'versienummer_uniek'
);
SET @ddl = IF(@unique_bestaat = 0, 'ALTER TABLE versies ADD UNIQUE KEY versienummer_uniek (versienummer)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


INSERT IGNORE INTO versies (versienummer, datum, wijzigingen) VALUES
('V1.3.5', '12 augustus 2026', '## Meldingen & werkproces
- Meldingen aanmaken met automatisch gegenereerd meld-ID (bv. MK-D2-014), classificatie, prioriteit en locatie.
- Titel wordt automatisch samengesteld uit de classificatie.
- Logboek per melding, met notities, tijdstip en auteur.
- Subtaken bij protocollen en losse taken per melding, beide aan te vinken met tijdstip/wie.
- Toegewezen aan (crew) en toegewezen centralist (gebruiker), beide met doorzoekbare dropdown.
- Labels om meldingen te markeren voor latere opvolging, ook in het archief.
- Statusgeschiedenis met doorlooptijden per status.
- Snelle statuswijziging direct vanaf het dashboard.
- Automatisch verversen (instelbaar) met optioneel geluidssignaal bij nieuwe meldingen.
## Classificatie, protocollen en locaties
- Tweeledige classificatie: hoofd- en subclassificatie, met eigen kleur.
- Protocollen met subtaken en tot 5 hyperlinks naar naslag/documenten.
- Protocollen automatisch koppelen aan een melding bij een matchende subclassificatie.
- Vooraf ingestelde locaties, op te roepen met ;locatienaam in tekstvelden.
- Eigen statussen aanmaken/aanpassen naast de 4 ingebouwde.
## Gebruikers en rechten
- Rollen: beheerder, medewerker en viewer (alleen Overview-toegang).
- Crew-lijst (contactpersonen zonder login) voor Toegewezen aan.
- Persoonlijke instellingen: verversingstijd en geluid.
## Overzicht en archief
- Dashboard, Overview (passief scherm) en Archief met filters op categorie/prioriteit/label.
- Export naar CSV en PDF, inclusief logboek, protocollen, subtaken, losse taken en doorlooptijden.
## Koppelingen
- API-endpoint voor het aanmaken van meldingen (bv. vanaf een Stream Deck), met token per beheerder-account.'),
('V1.3.6', '12 augustus 2026', '## Nieuw
- Filteren op prioriteit en hoofdclassificatie, ook op de Overview-pagina (was alleen dashboard/archief).
- Sorteren op prioriteit (standaard) of nieuwste melding bovenaan, op dashboard en Overview.
- Versiebeheer verplaatst naar de database, beheerbaar via Beheer -> Instellingen (in plaats van een los bestand).
## Reparaties
- Databasecontainer draaide op UTC in plaats van Europe/Amsterdam, waardoor tijdstippen 1-2 uur konden afwijken.
- Archief-tellingen en -leegmaken gebruikten nog een vaste statuslijst van vóór de eigen-statussen-functie, werkten daardoor niet correct met een zelf toegevoegde afgeronde status.
- SQL-fout op de Overview-pagina door een ontbrekende tabel-alias.'),
('V1.3.7', '12 augustus 2026', '## Nieuw
- Attentiesignaal: knop op de melddetailpagina om nadrukkelijk de aandacht te vragen voor een melding.
- Bij een attentiesignaal verschijnt ⚠️ voor het meld-ID op dashboard, Overview en archief, tot iemand het weer uitzet.
- Eigen belgeluid voor een attentiesignaal (dezelfde toon, twee keer), duidelijk anders dan het geluid bij een nieuwe melding.'),
('V1.3.8', '12 augustus 2026', '## Nieuw
- Backup & Restore onder Beheer: crew, classificaties, statussen, protocollen, locaties en labels exporteren als .json-bestand, met aanvinkvakjes voor wat je wel/niet meeneemt.
- Bestand weer importeren om dezelfde configuratie snel bij een nieuw evenement te herstellen, zonder alles opnieuw in te vullen.
- Classificaties, statussen, locaties en labels worden bij import herkend op naam en overgeslagen als ze al bestaan (dus veilig om dezelfde backup meerdere keren te importeren). Protocollen en crew worden altijd als nieuw toegevoegd.'),
('V1.3.9', '12 augustus 2026', '## Nieuw
- Connectiviteit onder Beheer: uitgaande webhooks naar externe applicaties (bv. Slack, Teams, een eigen systeem).
- Webhook afgaan op zelf gekozen gebeurtenissen: nieuwe melding aangemaakt, status gewijzigd, en/of attentiesignaal gegeven.
- Testknop per webhook om de koppeling meteen te controleren, met status en laatste foutmelding zichtbaar in het overzicht.'),
('V1.4.0', '13 augustus 2026', '## Nieuw
- Gekoppelde meldingen: meldingen aan elkaar koppelen (bv. een EHBO-inzet die een AMBU-inzet oplevert), meerdere tegelijk mogelijk.
- Koppeling met type: "is vervolg van" of "is gerelateerd aan" — op de andere melding zie je automatisch het passende omgekeerde label.
- Sneltoets "+ Vervolgmelding aanmaken": opent het aanmaakformulier met locatie al ingevuld en koppelt automatisch terug naar de melding waar je vandaan kwam.
- 🔗-icoon voor het meld-ID op dashboard, Overview en archief zodra een melding ergens aan gekoppeld is.
- Gekoppelde meldingen blijven zelfstandig (eigen status, protocol, logboek, doorlooptijden) — koppelen beïnvloedt elkaars status niet.
- Nieuwe pagina "Samengevoegd" (naast Dashboard) met alle koppelingen waar minstens 1 melding nog actief is, met direct kunnen loskoppelen.');

-- V1.4.0 is achteraf aangevuld (Samengevoegd-pagina); INSERT IGNORE
-- hierboven slaat 'm dan over als de rij al bestond, dus deze UPDATE
-- zorgt dat de tekst ook bij een herhaalde run up-to-date komt.
UPDATE versies SET wijzigingen = '## Nieuw
- Gekoppelde meldingen: meldingen aan elkaar koppelen (bv. een EHBO-inzet die een AMBU-inzet oplevert), meerdere tegelijk mogelijk.
- Koppeling met type: "is vervolg van" of "is gerelateerd aan" — op de andere melding zie je automatisch het passende omgekeerde label.
- Sneltoets "+ Vervolgmelding aanmaken": opent het aanmaakformulier met locatie al ingevuld en koppelt automatisch terug naar de melding waar je vandaan kwam.
- 🔗-icoon voor het meld-ID op dashboard, Overview en archief zodra een melding ergens aan gekoppeld is.
- Gekoppelde meldingen blijven zelfstandig (eigen status, protocol, logboek, doorlooptijden) — koppelen beïnvloedt elkaars status niet.
- Nieuwe pagina "Samengevoegd" (naast Dashboard) met alle koppelingen waar minstens 1 melding nog actief is, met direct kunnen loskoppelen.'
WHERE versienummer = 'V1.4.0';
