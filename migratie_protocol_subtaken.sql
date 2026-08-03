-- ============================================================
-- MIGRATIE: subtaken bij protocollen
-- Voer dit eenmalig uit op je bestaande database om subtaken toe
-- te voegen aan protocollen, aanvinkbaar per melding.
-- ============================================================

CREATE TABLE IF NOT EXISTS protocol_subtaken (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocol_id INT NOT NULL,
    omschrijving VARCHAR(255) NOT NULL,
    volgorde INT NOT NULL DEFAULT 0,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (protocol_id) REFERENCES protocollen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS melding_subtaak_status (
    melding_id INT NOT NULL,
    subtaak_id INT NOT NULL,
    afgevinkt TINYINT(1) NOT NULL DEFAULT 0,
    afgevinkt_door_id INT DEFAULT NULL,
    afgevinkt_op DATETIME DEFAULT NULL,
    PRIMARY KEY (melding_id, subtaak_id),
    FOREIGN KEY (melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (subtaak_id) REFERENCES protocol_subtaken(id) ON DELETE CASCADE,
    FOREIGN KEY (afgevinkt_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
