-- ============================================================
-- MELDKAMER SYSTEEM - Database schema
-- Importeer dit script in je (externe) MySQL/MariaDB database
-- voordat je de applicatie voor het eerst gebruikt.
-- ============================================================

-- Algemene, via het beheerpaneel aanpasbare instellingen (evenementnaam,
-- startdatum, aantal dagen). Ontbreekt een sleutel hier, dan valt de
-- applicatie terug op de waarde uit config.php.
CREATE TABLE IF NOT EXISTS instellingen (
    sleutel VARCHAR(50) PRIMARY KEY,
    waarde VARCHAR(255) NOT NULL,
    bijgewerkt_op DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gebruikers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gebruikersnaam VARCHAR(50) NOT NULL UNIQUE,
    wachtwoord_hash VARCHAR(255) NOT NULL,
    naam VARCHAR(100) NOT NULL,
    rol ENUM('beheerder','medewerker') NOT NULL DEFAULT 'medewerker',
    actief TINYINT(1) NOT NULL DEFAULT 1,
    api_token VARCHAR(64) DEFAULT NULL UNIQUE,
    auto_refresh_seconden INT NOT NULL DEFAULT 20,
    geluid_nieuwe_melding TINYINT(1) NOT NULL DEFAULT 1,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vooraf ingestelde locaties. Kunnen in de omschrijving (bij een nieuwe
-- melding) of in een notitie opgeroepen worden met ";naam", waarna het
-- locatieveld van de melding automatisch wordt bijgewerkt bij een match.
CREATE TABLE IF NOT EXISTS locaties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(150) NOT NULL UNIQUE,
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hoofdclassificatie, bv. "Medisch", "Beveiliging", "Techniek"
-- Labels om meldingen te markeren voor latere opvolging, onafhankelijk van
-- de status (werkt dus zowel op actieve als afgesloten/gearchiveerde
-- meldingen). Zie melding_labels hieronder voor de koppeling.
CREATE TABLE IF NOT EXISTS labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL UNIQUE,
    kleur VARCHAR(7) NOT NULL DEFAULT '#f5a524',
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hoofdclassificaties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(100) NOT NULL,
    kleur VARCHAR(7) NOT NULL DEFAULT '#f5a524',
    beschrijving VARCHAR(255) DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subclassificatie, hangt altijd onder precies 1 hoofdclassificatie,
-- bv. hoofdclassificatie "Medisch" -> subclassificatie "Reanimatie".
-- standaard_prioriteit wordt als voorstel getoond bij het aanmaken van
-- een nieuwe melding met deze subclassificatie (blijft aanpasbaar).
CREATE TABLE IF NOT EXISTS subclassificaties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hoofdclassificatie_id INT NOT NULL,
    naam VARCHAR(100) NOT NULL,
    beschrijving VARCHAR(255) DEFAULT NULL,
    standaard_prioriteit ENUM('laag','normaal','hoog','kritiek') DEFAULT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hoofdclassificatie_id) REFERENCES hoofdclassificaties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS protocollen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titel VARCHAR(150) NOT NULL,
    subclassificatie_id INT DEFAULT NULL,
    inhoud TEXT NOT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    bijgewerkt_op DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subclassificatie_id) REFERENCES subclassificaties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subtaken binnen een protocol, bv. protocol "Reanimatie" -> subtaak
-- "AED gehaald", "112 gebeld". Worden per melding afzonderlijk afgevinkt
-- (zie melding_subtaak_status hieronder).
CREATE TABLE IF NOT EXISTS protocol_subtaken (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocol_id INT NOT NULL,
    omschrijving VARCHAR(255) NOT NULL,
    volgorde INT NOT NULL DEFAULT 0,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (protocol_id) REFERENCES protocollen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Externe verwijzingen (hyperlinks) bij een protocol, bv. naar een
-- draaiboek, plattegrond of ander naslagdocument. Knoptekst is per protocol
-- vrij te kiezen. Maximaal 5 per protocol (afgedwongen in de beheer-UI,
-- niet in de database).
CREATE TABLE IF NOT EXISTS protocol_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocol_id INT NOT NULL,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    volgorde INT NOT NULL DEFAULT 0,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (protocol_id) REFERENCES protocollen(id) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS melding_labels (
    melding_id INT NOT NULL,
    label_id INT NOT NULL,
    gekoppeld_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (melding_id, label_id),
    FOREIGN KEY (melding_id) REFERENCES meldingen(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
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

-- Afvinkstatus van een protocol-subtaak, per melding (dezelfde subtaak kan
-- op meerdere meldingen los van elkaar worden afgevinkt)
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
