-- ============================================================
-- MIGRATIE: functie per gebruiker, en "toegewezen aan" / "toegewezen
-- centralist" koppelen aan echte gebruikersaccounts (in plaats van vrije
-- tekst) met een doorzoekbare dropdown.
-- Voer dit eenmalig uit op je bestaande database.
--
-- Let op: de oude, vrije-tekst kolom "toegewezen_aan" blijft gewoon
-- bestaan (voor eventuele oude/historische waarden), maar wordt door de
-- webinterface niet meer gebruikt -- er komen twee nieuwe kolommen bij
-- die naar een gebruiker verwijzen.
-- ============================================================

ALTER TABLE gebruikers
    ADD COLUMN functie VARCHAR(100) DEFAULT NULL AFTER rol;

ALTER TABLE meldingen
    ADD COLUMN toegewezen_aan_gebruiker_id INT DEFAULT NULL AFTER toegewezen_aan,
    ADD COLUMN toegewezen_centralist_id INT DEFAULT NULL AFTER toegewezen_aan_gebruiker_id,
    ADD FOREIGN KEY (toegewezen_aan_gebruiker_id) REFERENCES gebruikers(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (toegewezen_centralist_id) REFERENCES gebruikers(id) ON DELETE SET NULL;
