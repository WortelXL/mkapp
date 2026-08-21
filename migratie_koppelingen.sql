-- ============================================================
-- MIGRATIE: gekoppelde meldingen (V1.4.0)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

CREATE TABLE IF NOT EXISTS melding_koppelingen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    melding_id INT NOT NULL,
    gekoppelde_melding_id INT NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'gerelateerd',
    aangemaakt_door_id INT DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (gekoppelde_melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (aangemaakt_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL,
    UNIQUE KEY unieke_koppeling (melding_id, gekoppelde_melding_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
