-- ============================================================
-- MIGRATIE: hoofd- en subclassificatie in plaats van categorie
-- Alleen nodig als je database.sql AL eerder hebt geimporteerd
-- (dus je hebt al een werkende "categorieen"-tabel). Voer dit
-- script eenmalig uit op je bestaande database.
--
-- Dit script:
--   1. Maakt hoofdclassificaties en subclassificaties aan
--   2. Zet je bestaande categorieen om naar hoofdclassificaties
--      (zonder subclassificaties -- die maak je zelf aan via het
--      beheerpaneel)
--   3. Koppelt bestaande meldingen en protocollen aan hun nieuwe
--      hoofdclassificatie
--   4. Verwijdert de oude categorie_id kolommen en de categorieen-tabel
-- ============================================================

-- 1. Nieuwe tabellen
CREATE TABLE IF NOT EXISTS hoofdclassificaties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL,
    kleur VARCHAR(7) NOT NULL DEFAULT '#f5a524',
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subclassificaties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hoofdclassificatie_id INT NOT NULL,
    naam VARCHAR(100) NOT NULL,
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hoofdclassificatie_id) REFERENCES hoofdclassificaties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Bestaande categorieen overzetten (zelfde id's, zodat de koppeling
--    hieronder simpel 1-op-1 kan)
INSERT INTO hoofdclassificaties (id, naam, kleur, beschrijving, aangemaakt_op)
SELECT id, naam, kleur, beschrijving, aangemaakt_op FROM categorieen;

-- Zorg dat nieuwe hoofdclassificaties (aangemaakt na deze migratie)
-- niet botsen met de overgezette id's
ALTER TABLE hoofdclassificaties AUTO_INCREMENT = 1000;

-- 3. Meldingen: nieuwe kolommen toevoegen en vullen
ALTER TABLE meldingen
    ADD COLUMN hoofdclassificatie_id INT DEFAULT NULL AFTER omschrijving,
    ADD COLUMN subclassificatie_id INT DEFAULT NULL AFTER hoofdclassificatie_id;

UPDATE meldingen SET hoofdclassificatie_id = categorie_id;

ALTER TABLE meldingen
    ADD FOREIGN KEY (hoofdclassificatie_id) REFERENCES hoofdclassificaties(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (subclassificatie_id) REFERENCES subclassificaties(id) ON DELETE SET NULL;

-- Oude foreign key + kolom verwijderen (naam van de FK-constraint kan
-- per installatie verschillen; zoek 'm op als onderstaande regel faalt met
-- SHOW CREATE TABLE meldingen; en pas de constraintnaam aan)
ALTER TABLE meldingen DROP FOREIGN KEY meldingen_ibfk_1;
ALTER TABLE meldingen DROP COLUMN categorie_id;

-- 4. Protocollen: nieuwe kolom toevoegen en vullen
ALTER TABLE protocollen
    ADD COLUMN hoofdclassificatie_id INT DEFAULT NULL AFTER titel;

UPDATE protocollen SET hoofdclassificatie_id = categorie_id;

ALTER TABLE protocollen
    ADD FOREIGN KEY (hoofdclassificatie_id) REFERENCES hoofdclassificaties(id) ON DELETE SET NULL;

ALTER TABLE protocollen DROP FOREIGN KEY protocollen_ibfk_1;
ALTER TABLE protocollen DROP COLUMN categorie_id;

-- 5. Oude tabel opruimen
DROP TABLE categorieen;
