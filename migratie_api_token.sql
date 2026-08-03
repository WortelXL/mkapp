-- ============================================================
-- MIGRATIE: API-token per gebruiker (voor Stream Deck / externe koppelingen)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

ALTER TABLE gebruikers
    ADD COLUMN api_token VARCHAR(64) DEFAULT NULL UNIQUE AFTER actief;
