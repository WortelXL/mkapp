-- ============================================================
-- MELDKAMER SYSTEEM - Database schema
-- Importeer dit script in je (externe) MySQL/MariaDB database
-- voordat je de applicatie voor het eerst gebruikt.
-- ============================================================

CREATE TABLE IF NOT EXISTS gebruikers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gebruikersnaam VARCHAR(50) NOT NULL UNIQUE,
    wachtwoord_hash VARCHAR(255) NOT NULL,
    naam VARCHAR(100) NOT NULL,
    rol ENUM('beheerder','medewerker') NOT NULL DEFAULT 'medewerker',
    actief TINYINT(1) NOT NULL DEFAULT 1,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hoofdclassificatie, bv. "Medisch", "Beveiliging", "Techniek"
CREATE TABLE IF NOT EXISTS hoofdclassificaties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL,
    kleur VARCHAR(7) NOT NULL DEFAULT '#f5a524',
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subclassificatie, hangt altijd onder precies 1 hoofdclassificatie,
-- bv. hoofdclassificatie "Medisch" -> subclassificatie "Reanimatie"
CREATE TABLE IF NOT EXISTS subclassificaties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hoofdclassificatie_id INT NOT NULL,
    naam VARCHAR(100) NOT NULL,
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hoofdclassificatie_id) REFERENCES hoofdclassificaties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS protocollen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titel VARCHAR(150) NOT NULL,
    hoofdclassificatie_id INT DEFAULT NULL,
    inhoud TEXT NOT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    bijgewerkt_op DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hoofdclassificatie_id) REFERENCES hoofdclassificaties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meldingen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meld_id VARCHAR(20) NOT NULL UNIQUE,
    titel VARCHAR(150) NOT NULL,
    omschrijving TEXT,
    hoofdclassificatie_id INT DEFAULT NULL,
    subclassificatie_id INT DEFAULT NULL,
    locatie VARCHAR(150) DEFAULT NULL,
    prioriteit ENUM('laag','normaal','hoog','kritiek') NOT NULL DEFAULT 'normaal',
    status ENUM('open','in_behandeling','afgehandeld','geannuleerd') NOT NULL DEFAULT 'open',
    gemeld_door VARCHAR(100) DEFAULT NULL,
    toegewezen_aan VARCHAR(100) DEFAULT NULL,
    aangemaakt_door_id INT DEFAULT NULL,
    bijgewerkt_door_id INT DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    bijgewerkt_op DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hoofdclassificatie_id) REFERENCES hoofdclassificaties(id) ON DELETE SET NULL,
    FOREIGN KEY (subclassificatie_id) REFERENCES subclassificaties(id) ON DELETE SET NULL,
    FOREIGN KEY (aangemaakt_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL,
    FOREIGN KEY (bijgewerkt_door_id) REFERENCES gebruikers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS melding_protocollen (
    melding_id INT NOT NULL,
    protocol_id INT NOT NULL,
    gekoppeld_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (melding_id, protocol_id),
    FOREIGN KEY (melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (protocol_id) REFERENCES protocollen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS melding_notities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    melding_id INT NOT NULL,
    notitie TEXT NOT NULL,
    auteur VARCHAR(100) DEFAULT NULL,
    gebruiker_id INT DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (gebruiker_id) REFERENCES gebruikers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enkele voorbeeld hoofd- en subclassificaties
-- (mag je aanpassen/verwijderen via het beheerpaneel)
INSERT INTO hoofdclassificaties (naam, kleur, beschrijving) VALUES
    ('Medisch', '#ef4444', 'EHBO- en medische incidenten'),
    ('Beveiliging', '#f5a524', 'Overlast, agressie, diefstal'),
    ('Techniek', '#3b82f6', 'Stroom, geluid, licht, constructies'),
    ('Logistiek', '#22c55e', 'Bevoorrading, verkeer, parkeren')
ON DUPLICATE KEY UPDATE naam = naam;

INSERT INTO subclassificaties (hoofdclassificatie_id, naam, beschrijving)
SELECT id, sub.naam, sub.beschrijving FROM hoofdclassificaties
JOIN (
    SELECT 'Medisch' AS hoofd, 'Reanimatie' AS naam, NULL AS beschrijving
    UNION ALL SELECT 'Medisch', 'EHBO klein letsel', NULL
    UNION ALL SELECT 'Medisch', 'Uitval / onwel', NULL
    UNION ALL SELECT 'Beveiliging', 'Agressie', NULL
    UNION ALL SELECT 'Beveiliging', 'Diefstal', NULL
    UNION ALL SELECT 'Beveiliging', 'Vermist persoon', NULL
    UNION ALL SELECT 'Techniek', 'Stroomuitval', NULL
    UNION ALL SELECT 'Techniek', 'Geluid / licht', NULL
    UNION ALL SELECT 'Logistiek', 'Bevoorrading', NULL
    UNION ALL SELECT 'Logistiek', 'Verkeer / parkeren', NULL
) AS sub ON sub.hoofd = hoofdclassificaties.naam;
