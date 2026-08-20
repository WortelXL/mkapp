-- ============================================================
-- MIGRATIE: wijzigingenlog-entry voor V1.3.6
-- Voer dit uit op je bestaande database (na migratie_versies.sql).
-- ============================================================

INSERT INTO versies (versienummer, datum, wijzigingen) VALUES
('V1.3.6', '12 augustus 2026', '## Nieuw
- Filteren op prioriteit en hoofdclassificatie, ook op de Overview-pagina (was alleen dashboard/archief).
- Sorteren op prioriteit (standaard) of nieuwste melding bovenaan, op dashboard en Overview.
- Versiebeheer verplaatst naar de database, beheerbaar via Beheer -> Instellingen (in plaats van een los bestand).
## Reparaties
- Databasecontainer draaide op UTC in plaats van Europe/Amsterdam, waardoor tijdstippen 1-2 uur konden afwijken.
- Archief-tellingen en -leegmaken gebruikten nog een vaste statuslijst van vóór de eigen-statussen-functie, werkten daardoor niet correct met een zelf toegevoegde afgeronde status.
- SQL-fout op de Overview-pagina door een ontbrekende tabel-alias.');
