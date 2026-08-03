-- ============================================================
-- MIGRATIE: algemene instellingen (evenementnaam, startdatum, dagen)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

CREATE TABLE IF NOT EXISTS instellingen (
    sleutel VARCHAR(50) PRIMARY KEY,
    waarde VARCHAR(255) NOT NULL,
    bijgewerkt_op DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
