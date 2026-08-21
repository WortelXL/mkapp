-- ============================================================
-- MIGRATIE: uitgaande webhooks (Beheer -> Connectiviteit)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    events TEXT NOT NULL,
    actief TINYINT(1) NOT NULL DEFAULT 1,
    laatst_verzonden_op DATETIME DEFAULT NULL,
    laatste_status VARCHAR(20) DEFAULT NULL,
    laatste_foutmelding VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
