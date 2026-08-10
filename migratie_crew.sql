-- ============================================================
-- MIGRATIE: Crew (contactpersonen zonder login), gekoppeld aan
-- "Toegewezen aan" op een melding.
-- Voer dit eenmalig uit op je bestaande database.
--
-- Let op: de vorige koppeling van "Toegewezen aan" aan een gebruikers-
-- account (toegewezen_aan_gebruiker_id) blijft in de database staan
-- (voor eventuele al ingevulde waarden), maar wordt door de webinterface
-- niet meer gebruikt -- vanaf nu wijst "Toegewezen aan" naar de nieuwe
-- crew-tabel. "Toegewezen centralist" blijft wel gewoon gekoppeld aan
-- gebruikersaccounts.
-- ============================================================

CREATE TABLE IF NOT EXISTS crew (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL,
    functie VARCHAR(100) DEFAULT NULL,
    telefoonnummer VARCHAR(30) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE meldingen
    ADD COLUMN toegewezen_aan_crew_id INT DEFAULT NULL AFTER toegewezen_aan_gebruiker_id,
    ADD FOREIGN KEY (toegewezen_aan_crew_id) REFERENCES crew(id) ON DELETE SET NULL;
