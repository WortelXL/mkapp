-- ============================================================
-- MIGRATIE: statusgeschiedenis per melding (voor doorlooptijden)
-- Voer dit eenmalig uit op je bestaande database.
--
-- Let op: bestaande meldingen krijgen geen terugwerkende geschiedenis
-- (die is niet eerder bijgehouden) -- vanaf nu wordt elke statuswijziging
-- gelogd, met tijdstip en wie de wijziging deed.
-- ============================================================

CREATE TABLE IF NOT EXISTS melding_status_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    melding_id INT NOT NULL,
    status ENUM('open','in_behandeling','afgehandeld','geannuleerd') NOT NULL,
    gebruiker_id INT DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (gebruiker_id) REFERENCES gebruikers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
