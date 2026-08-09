-- ============================================================
-- MIGRATIE: extra rol "view" (alleen toegang tot de Overview-pagina)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

ALTER TABLE gebruikers
    MODIFY COLUMN rol ENUM('beheerder','medewerker','view') NOT NULL DEFAULT 'medewerker';
