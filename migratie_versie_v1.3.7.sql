-- ============================================================
-- MIGRATIE: wijzigingenlog-entry voor V1.3.7
-- Voer dit uit op je bestaande database (na migratie_versies.sql).
-- ============================================================

INSERT INTO versies (versienummer, datum, wijzigingen) VALUES
('V1.3.7', '12 augustus 2026', '## Nieuw
- Attentiesignaal: knop op de melddetailpagina om nadrukkelijk de aandacht te vragen voor een melding.
- Bij een attentiesignaal verschijnt ⚠️ voor het meld-ID op dashboard, Overview en archief, tot iemand het weer uitzet.
- Eigen belgeluid voor een attentiesignaal (dezelfde toon, twee keer), duidelijk anders dan het geluid bij een nieuwe melding.');
