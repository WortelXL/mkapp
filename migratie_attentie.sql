-- ============================================================
-- MIGRATIE: attentiesignaal per melding (los geluid + ⚠️ in overzichten)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

ALTER TABLE meldingen
    ADD COLUMN attentie TINYINT(1) NOT NULL DEFAULT 0 AFTER toegewezen_centralist_id,
    ADD COLUMN attentie_door_id INT DEFAULT NULL AFTER attentie,
    ADD COLUMN attentie_op DATETIME DEFAULT NULL AFTER attentie_door_id,
    ADD FOREIGN KEY (attentie_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL;
