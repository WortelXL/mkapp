-- ============================================================
-- MIGRATIE: standaardprioriteit per subclassificatie
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

ALTER TABLE subclassificaties
    ADD COLUMN standaard_prioriteit ENUM('laag','normaal','hoog','kritiek') DEFAULT NULL AFTER beschrijving;
