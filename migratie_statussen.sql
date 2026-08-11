-- ============================================================
-- MIGRATIE: eigen statussen beheerbaar maken (Beheer -> Statussen)
-- Voer dit eenmalig uit op je bestaande database.
-- ============================================================

CREATE TABLE IF NOT EXISTS statussen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sleutel VARCHAR(50) NOT NULL UNIQUE,
    naam VARCHAR(100) NOT NULL,
    kleur VARCHAR(7) NOT NULL DEFAULT '#6b7280',
    categorie ENUM('actief','afgerond') NOT NULL DEFAULT 'actief',
    ingebouwd TINYINT(1) NOT NULL DEFAULT 0,
    volgorde INT NOT NULL DEFAULT 0,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO statussen (sleutel, naam, kleur, categorie, ingebouwd, volgorde) VALUES
    ('open', 'Open', '#ef4444', 'actief', 1, 1),
    ('in_behandeling', 'In behandeling', '#f5a524', 'actief', 1, 2),
    ('afgehandeld', 'Afgehandeld', '#22c55e', 'afgerond', 1, 3),
    ('geannuleerd', 'Geannuleerd', '#6b7280', 'afgerond', 1, 4)
ON DUPLICATE KEY UPDATE sleutel = sleutel;

-- Kolomtype verruimen van ENUM naar VARCHAR, zodat eigen statussen erin
-- passen (bestaande waarden blijven gewoon behouden)
ALTER TABLE meldingen MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'open';
ALTER TABLE melding_status_log MODIFY COLUMN status VARCHAR(50) NOT NULL;
