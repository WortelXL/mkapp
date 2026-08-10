-- ============================================================
-- MIGRATIE: losse taken per melding (los van protocollen)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

CREATE TABLE IF NOT EXISTS melding_taken (
    id INT AUTO_INCREMENT PRIMARY KEY,
    melding_id INT NOT NULL,
    omschrijving VARCHAR(255) NOT NULL,
    afgevinkt TINYINT(1) NOT NULL DEFAULT 0,
    afgevinkt_door_id INT DEFAULT NULL,
    afgevinkt_op DATETIME DEFAULT NULL,
    aangemaakt_door_id INT DEFAULT NULL,
    volgorde INT NOT NULL DEFAULT 0,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (afgevinkt_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL,
    FOREIGN KEY (aangemaakt_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
