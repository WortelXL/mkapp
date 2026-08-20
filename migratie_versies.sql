-- ============================================================
-- MIGRATIE: versiebeheer / wijzigingenlog (database-backed, beheerbaar
-- via Beheer -> Instellingen in plaats van een los PHP-bestand)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

CREATE TABLE IF NOT EXISTS versies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    versienummer VARCHAR(20) NOT NULL,
    datum VARCHAR(50) NOT NULL,
    wijzigingen TEXT NOT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO versies (versienummer, datum, wijzigingen) VALUES
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
- API-endpoint voor het aanmaken van meldingen (bv. vanaf een Stream Deck), met token per beheerder-account.');
