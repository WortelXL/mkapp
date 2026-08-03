-- ============================================================
-- MIGRATIE: protocollen koppelen aan subclassificatie i.p.v.
-- hoofdclassificatie
-- Voer dit eenmalig uit op je bestaande database.
--
-- Let op: een 1-op-1 omzetting is niet mogelijk (1 hoofdclassificatie
-- kan meerdere subclassificaties hebben), dus bestaande koppelingen
-- worden losgelaten. Je koppelt protocollen opnieuw aan een
-- subclassificatie via Beheer -> Protocollen.
-- ============================================================

ALTER TABLE protocollen
    ADD COLUMN subclassificatie_id INT DEFAULT NULL AFTER titel,
    ADD FOREIGN KEY (subclassificatie_id) REFERENCES subclassificaties(id) ON DELETE SET NULL;

-- Oude foreign key + kolom verwijderen (naam van de FK-constraint kan
-- per installatie verschillen; check met SHOW CREATE TABLE protocollen;
-- als onderstaande regel faalt, en pas de constraintnaam aan)
ALTER TABLE protocollen DROP FOREIGN KEY protocollen_ibfk_1;
ALTER TABLE protocollen DROP COLUMN hoofdclassificatie_id;
