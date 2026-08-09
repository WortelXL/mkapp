-- ============================================================
-- MIGRATIE: labels voor latere opvolging (open en gearchiveerd)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

CREATE TABLE IF NOT EXISTS labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL UNIQUE,
    kleur VARCHAR(7) NOT NULL DEFAULT '#f5a524',
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS melding_labels (
    melding_id INT NOT NULL,
    label_id INT NOT NULL,
    gekoppeld_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (melding_id, label_id),
    FOREIGN KEY (melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
