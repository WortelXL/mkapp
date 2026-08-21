-- ============================================================
-- MIGRATIE: wijzigingenlog-entry voor V1.3.8
-- Voer dit uit op je bestaande database (na migratie_versies.sql).
-- ============================================================

INSERT INTO versies (versienummer, datum, wijzigingen) VALUES
('V1.3.8', '12 augustus 2026', '## Nieuw
- Backup & Restore onder Beheer: crew, classificaties, statussen, protocollen, locaties en labels exporteren als .json-bestand, met aanvinkvakjes voor wat je wel/niet meeneemt.
- Bestand weer importeren om dezelfde configuratie snel bij een nieuw evenement te herstellen, zonder alles opnieuw in te vullen.
- Classificaties, statussen, locaties en labels worden bij import herkend op naam en overgeslagen als ze al bestaan (dus veilig om dezelfde backup meerdere keren te importeren). Protocollen en crew worden altijd als nieuw toegevoegd.');
