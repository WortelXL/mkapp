-- ============================================================
-- MIGRATIE: vooraf ingestelde locaties (;locatienaam-commando)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

CREATE TABLE IF NOT EXISTS locaties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(150) NOT NULL UNIQUE,
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
