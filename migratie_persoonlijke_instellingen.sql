-- ============================================================
-- MIGRATIE: persoonlijke instellingen (auto-refresh tijd, geluid)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

ALTER TABLE gebruikers
    ADD COLUMN auto_refresh_seconden INT NOT NULL DEFAULT 20 AFTER api_token,
    ADD COLUMN geluid_nieuwe_melding TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_refresh_seconden;
