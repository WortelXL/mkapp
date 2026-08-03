<?php
require_once __DIR__ . '/db.php';

/** Korte htmlspecialchars helper */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Instellingen (evenementnaam, startdatum, aantal dagen) staan in de
 * databasetabel 'instellingen' en zijn aanpasbaar via Beheer -> Instellingen.
 * Ontbreekt een sleutel (bv. bij een verse installatie), dan valt de
 * applicatie terug op de waarde uit config.php.
 */
function get_instelling(PDO $pdo, string $sleutel, string $standaard = ''): string
{
    static $cache = [];
    if (array_key_exists($sleutel, $cache)) {
        return $cache[$sleutel];
    }
    $stmt = $pdo->prepare('SELECT waarde FROM instellingen WHERE sleutel = :s');
    $stmt->execute(['s' => $sleutel]);
    $waarde = $stmt->fetchColumn();
    $cache[$sleutel] = $waarde !== false && $waarde !== '' ? $waarde : $standaard;
    return $cache[$sleutel];
}

function set_instelling(PDO $pdo, string $sleutel, string $waarde): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO instellingen (sleutel, waarde) VALUES (:s, :w)
         ON DUPLICATE KEY UPDATE waarde = VALUES(waarde)'
    );
    $stmt->execute(['s' => $sleutel, 'w' => $waarde]);
}

function event_naam(PDO $pdo): string
{
    return get_instelling($pdo, 'event_naam', EVENT_NAAM);
}

function event_start_datum(PDO $pdo): string
{
    return get_instelling($pdo, 'event_start_datum', EVENT_START_DATUM);
}

function event_aantal_dagen(PDO $pdo): int
{
    return (int) get_instelling($pdo, 'event_aantal_dagen', (string) EVENT_AANTAL_DAGEN);
}

/** Bepaalt op welke festivaldag (1, 2, 3...) een tijdstip valt */
function bepaal_evenement_dag(PDO $pdo, ?DateTime $moment = null): int
{
    $moment = $moment ?? new DateTime();
    $start  = new DateTime(event_start_datum($pdo));
    $totaal = event_aantal_dagen($pdo);
    $diff   = (int) $start->diff($moment)->format('%r%a');
    $dag    = $diff + 1;
    if ($dag < 1) {
        $dag = 1;
    }
    if ($dag > $totaal) {
        $dag = $totaal;
    }
    return $dag;
}

/** Genereert een uniek, oplopend meld-ID zoals MK-D2-014 */
function genereer_meld_id(PDO $pdo): string
{
    $dag    = bepaal_evenement_dag($pdo);
    $prefix = sprintf('MK-D%d-', $dag);

    $stmt = $pdo->prepare(
        "SELECT meld_id FROM meldingen WHERE meld_id LIKE :prefix ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute(['prefix' => $prefix . '%']);
    $laatste = $stmt->fetchColumn();

    $volgnummer = 1;
    if ($laatste) {
        $delen = explode('-', $laatste);
        $volgnummer = (int) end($delen) + 1;
    }

    return $prefix . str_pad((string) $volgnummer, 3, '0', STR_PAD_LEFT);
}

/** Haalt alle hoofdclassificaties op, alfabetisch gesorteerd */
function get_hoofdclassificaties(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM hoofdclassificaties ORDER BY naam ASC')->fetchAll();
}

/** Haalt subclassificaties op, optioneel gefilterd op 1 hoofdclassificatie */
function get_subclassificaties(PDO $pdo, ?int $hoofdclassificatie_id = null): array
{
    if ($hoofdclassificatie_id !== null) {
        $stmt = $pdo->prepare('SELECT * FROM subclassificaties WHERE hoofdclassificatie_id = :h ORDER BY naam ASC');
        $stmt->execute(['h' => $hoofdclassificatie_id]);
        return $stmt->fetchAll();
    }
    return $pdo->query('SELECT * FROM subclassificaties ORDER BY naam ASC')->fetchAll();
}

/** Alle subclassificaties gegroepeerd per hoofdclassificatie_id, handig voor JS-dropdowns */
function get_subclassificaties_gegroepeerd(PDO $pdo): array
{
    $gegroepeerd = [];
    foreach (get_subclassificaties($pdo) as $sub) {
        $gegroepeerd[(int) $sub['hoofdclassificatie_id']][] = $sub;
    }
    return $gegroepeerd;
}

/** Haalt alle vooraf ingestelde locaties op, alfabetisch */
function get_locaties(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM locaties ORDER BY naam ASC')->fetchAll();
}

/**
 * Zoekt naar een ";locatienaam"-commando in vrije tekst (omschrijving of
 * notitie) en probeert dat te matchen met een vooraf ingestelde locatie.
 * Pakt het laatste ";..."-stuk in de tekst, tot het einde van de regel.
 * Exacte naam-match heeft voorrang op een gedeeltelijke match.
 * Retourneert de gevonden locatierij, of null als er geen commando is
 * gebruikt of niets matcht.
 */
function vind_locatie_commando(PDO $pdo, string $tekst): ?array
{
    if (!preg_match('/;\s*([^\n;]+)\s*$/', rtrim($tekst), $match)) {
        return null;
    }
    $zoekterm = trim($match[1]);
    if ($zoekterm === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM locaties WHERE naam = :z LIMIT 1');
    $stmt->execute(['z' => $zoekterm]);
    if ($rij = $stmt->fetch()) {
        return $rij;
    }

    $stmt = $pdo->prepare('SELECT * FROM locaties WHERE naam LIKE :z ORDER BY naam ASC LIMIT 1');
    $stmt->execute(['z' => '%' . $zoekterm . '%']);
    return $stmt->fetch() ?: null;
}

/** Haalt alle protocollen op, met naam van gekoppelde sub-/hoofdclassificatie erbij */
function get_protocollen(PDO $pdo): array
{
    return $pdo->query(
        'SELECT p.*, s.naam AS sub_naam, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur
         FROM protocollen p
         LEFT JOIN subclassificaties s ON s.id = p.subclassificatie_id
         LEFT JOIN hoofdclassificaties h ON h.id = s.hoofdclassificatie_id
         ORDER BY p.titel ASC'
    )->fetchAll();
}

/** Haalt de subtaken van 1 protocol op, in de ingestelde volgorde */
function get_subtaken(PDO $pdo, int $protocol_id): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM protocol_subtaken WHERE protocol_id = :p ORDER BY volgorde ASC, id ASC'
    );
    $stmt->execute(['p' => $protocol_id]);
    return $stmt->fetchAll();
}

/**
 * Haalt de subtaken van 1 protocol op, mét de afvinkstatus voor 1 specifieke
 * melding (afgevinkt, door wie, wanneer). Gebruikt op de melddetailpagina.
 */
function get_subtaken_met_status(PDO $pdo, int $protocol_id, int $melding_id): array
{
    $stmt = $pdo->prepare(
        'SELECT t.*, st.afgevinkt, st.afgevinkt_op, g.naam AS afgevinkt_door_naam
         FROM protocol_subtaken t
         LEFT JOIN melding_subtaak_status st ON st.subtaak_id = t.id AND st.melding_id = :m
         LEFT JOIN gebruikers g ON g.id = st.afgevinkt_door_id
         WHERE t.protocol_id = :p
         ORDER BY t.volgorde ASC, t.id ASC'
    );
    $stmt->execute(['m' => $melding_id, 'p' => $protocol_id]);
    return $stmt->fetchAll();
}

/**
 * Zoekt een hoofd-/subclassificatie op basis van een commando-tekst
 * (bv. "medisch" of "reanimatie", zonder het voorloop-streepje).
 * Exacte naam-matches gaan voor gedeeltelijke matches; subclassificaties
 * gaan voor hoofdclassificaties. Retourneert null als niets gevonden is.
 */
function vind_classificatie_commando(PDO $pdo, string $zoekterm): ?array
{
    $zoekterm = trim($zoekterm);
    if ($zoekterm === '') {
        return null;
    }

    $subquery = "SELECT s.id AS sub_id, s.naam AS sub_naam, h.id AS hoofd_id, h.naam AS hoofd_naam
                 FROM subclassificaties s JOIN hoofdclassificaties h ON h.id = s.hoofdclassificatie_id
                 WHERE s.naam %s ORDER BY s.naam ASC LIMIT 1";
    $hoofdquery = "SELECT id AS hoofd_id, naam AS hoofd_naam FROM hoofdclassificaties WHERE naam %s ORDER BY naam ASC LIMIT 1";

    // 1. Exacte match, subclassificatie heeft voorrang
    $stmt = $pdo->prepare(sprintf($subquery, '= :z'));
    $stmt->execute(['z' => $zoekterm]);
    if ($rij = $stmt->fetch()) {
        return $rij;
    }

    $stmt = $pdo->prepare(sprintf($hoofdquery, '= :z'));
    $stmt->execute(['z' => $zoekterm]);
    if ($rij = $stmt->fetch()) {
        return ['hoofd_id' => $rij['hoofd_id'], 'hoofd_naam' => $rij['hoofd_naam'], 'sub_id' => null, 'sub_naam' => null];
    }

    // 2. Gedeeltelijke match
    $stmt = $pdo->prepare(sprintf($subquery, 'LIKE :z'));
    $stmt->execute(['z' => '%' . $zoekterm . '%']);
    if ($rij = $stmt->fetch()) {
        return $rij;
    }

    $stmt = $pdo->prepare(sprintf($hoofdquery, 'LIKE :z'));
    $stmt->execute(['z' => '%' . $zoekterm . '%']);
    if ($rij = $stmt->fetch()) {
        return ['hoofd_id' => $rij['hoofd_id'], 'hoofd_naam' => $rij['hoofd_naam'], 'sub_id' => null, 'sub_naam' => null];
    }

    return null;
}

/** Labels en kleurklassen voor status */
function status_label(string $status): string
{
    return [
        'open'           => 'Open',
        'in_behandeling' => 'In behandeling',
        'afgehandeld'    => 'Afgehandeld',
        'geannuleerd'    => 'Geannuleerd',
    ][$status] ?? $status;
}

function status_class(string $status): string
{
    return [
        'open'           => 'status-open',
        'in_behandeling' => 'status-in_behandeling',
        'afgehandeld'    => 'status-afgehandeld',
        'geannuleerd'    => 'status-geannuleerd',
    ][$status] ?? '';
}

function prioriteit_label(string $prioriteit): string
{
    return [
        'laag'    => 'Laag',
        'normaal' => 'Normaal',
        'hoog'    => 'Hoog',
        'kritiek' => 'Kritiek',
    ][$prioriteit] ?? $prioriteit;
}

function prioriteit_class(string $prioriteit): string
{
    return 'prio-' . $prioriteit;
}

/** Hex-kleur per prioriteit, gebruikt op de retro-melddetailpagina */
function prioriteit_kleur(string $prioriteit): string
{
    return [
        'laag'    => '#a8a8a8',
        'normaal' => '#7ea6d8',
        'hoog'    => '#f5a524',
        'kritiek' => '#e04b3f',
    ][$prioriteit] ?? '#c0c0c0';
}

/** Login / rollen helpers */
function is_ingelogd(): bool
{
    return !empty($_SESSION['gebruiker_id']);
}

function huidige_gebruiker_naam(): string
{
    return $_SESSION['gebruiker_naam'] ?? '';
}

function huidige_gebruiker_rol(): string
{
    return $_SESSION['gebruiker_rol'] ?? '';
}

function is_beheerder(): bool
{
    return huidige_gebruiker_rol() === 'beheerder';
}

/** Elke ingelogde gebruiker (beheerder of medewerker) mag verder */
function vereis_login(): void
{
    if (!is_ingelogd()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/** Alleen gebruikers met de rol 'beheerder' mogen verder */
function vereis_beheerder(): void
{
    vereis_login();
    if (!is_beheerder()) {
        http_response_code(403);
        $actief = '';
        $paginatitel = 'Geen toegang';
        include __DIR__ . '/header.php';
        echo '<div class="empty">Je hebt geen beheerdersrechten om deze pagina te bekijken.</div>';
        include __DIR__ . '/footer.php';
        exit;
    }
}

function heeft_gebruiker(PDO $pdo): bool
{
    return (int) $pdo->query('SELECT COUNT(*) FROM gebruikers')->fetchColumn() > 0;
}

function rol_label(string $rol): string
{
    return [
        'beheerder'  => 'Beheerder',
        'medewerker' => 'Medewerker',
    ][$rol] ?? $rol;
}

/** Genereert een nieuw, willekeurig API-token voor Stream Deck / externe koppelingen */
function genereer_api_token(): string
{
    return bin2hex(random_bytes(20));
}

/** Haalt de persoonlijke instellingen van de ingelogde gebruiker op (met veilige standaardwaarden) */
/**
 * Stelt de titel van een melding samen uit de classificatie:
 * "Hoofdclassificatie - Subclassificatie", of alleen de hoofdclassificatie
 * als er geen subclassificatie gekozen is, of een neutrale placeholder
 * als er helemaal geen classificatie is.
 */
function bereken_melding_titel(PDO $pdo, ?int $hoofd_id, ?int $sub_id): string
{
    if (!$hoofd_id) {
        return 'Ongeclassificeerde melding';
    }

    $stmt = $pdo->prepare('SELECT naam FROM hoofdclassificaties WHERE id = :id');
    $stmt->execute(['id' => $hoofd_id]);
    $hoofd_naam = $stmt->fetchColumn();

    if (!$hoofd_naam) {
        return 'Ongeclassificeerde melding';
    }

    if ($sub_id) {
        $stmt = $pdo->prepare('SELECT naam FROM subclassificaties WHERE id = :id');
        $stmt->execute(['id' => $sub_id]);
        $sub_naam = $stmt->fetchColumn();
        if ($sub_naam) {
            return $hoofd_naam . ' - ' . $sub_naam;
        }
    }

    return $hoofd_naam;
}

function huidige_gebruiker_instellingen(PDO $pdo): array
{
    $standaard = ['auto_refresh_seconden' => 20, 'geluid_nieuwe_melding' => 1];
    if (empty($_SESSION['gebruiker_id'])) {
        return $standaard;
    }
    $stmt = $pdo->prepare('SELECT auto_refresh_seconden, geluid_nieuwe_melding FROM gebruikers WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['gebruiker_id']]);
    $rij = $stmt->fetch();
    return $rij ?: $standaard;
}
