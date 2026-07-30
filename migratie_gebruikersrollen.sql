-- ============================================================
-- MIGRATIE: gebruikersrollen toevoegen
-- Alleen nodig als je database.sql AL eerder hebt geimporteerd
-- (dus je hebt al een werkende "admins"-tabel). Voer dit script
-- eenmalig uit op je bestaande database.
--
-- Heb je nog helemaal geen database? Gebruik dan gewoon het
-- (bijgewerkte) database.sql — dit migratiescript is dan niet nodig.
-- ============================================================

-- 1. Hernoem admins -> gebruikers en voeg naam/rol/actief toe
RENAME TABLE admins TO gebruikers;

ALTER TABLE gebruikers
    ADD COLUMN naam VARCHAR(100) NOT NULL DEFAULT '' AFTER wachtwoord_hash,
    ADD COLUMN rol ENUM('beheerder','medewerker') NOT NULL DEFAULT 'beheerder' AFTER naam,
    ADD COLUMN actief TINYINT(1) NOT NULL DEFAULT 1 AFTER rol;

-- Bestaande admin-accounts krijgen de rol 'beheerder' en hun
-- gebruikersnaam als weergavenaam (pas dit later aan via het beheerpaneel)
UPDATE gebruikers SET naam = gebruikersnaam WHERE naam = '';

-- 2. Voeg koppelingen naar gebruikers toe op meldingen
ALTER TABLE meldingen
    ADD COLUMN aangemaakt_door_id INT DEFAULT NULL AFTER toegewezen_aan,
    ADD COLUMN bijgewerkt_door_id INT DEFAULT NULL AFTER aangemaakt_door_id,
    ADD FOREIGN KEY (aangemaakt_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (bijgewerkt_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL;

-- 3. Voeg koppeling naar gebruikers toe op notities
ALTER TABLE melding_notities
    ADD COLUMN gebruiker_id INT DEFAULT NULL AFTER auteur,
    ADD FOREIGN KEY (gebruiker_id) REFERENCES gebruikers(id) ON DELETE SET NULL;
