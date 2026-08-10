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

/**
 * Zoekt een subclassificatie direct op naam (exact, anders gedeeltelijk).
 * Gebruikt door de API als iemand een subclassificatie expliciet wil
 * kiezen, los van het bredere classificatie-zoekcommando.
 */
function vind_subclassificatie_op_naam(PDO $pdo, string $naam): ?array
{
    $naam = trim($naam);
    if ($naam === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM subclassificaties WHERE naam = :n LIMIT 1');
    $stmt->execute(['n' => $naam]);
    if ($rij = $stmt->fetch()) {
        return $rij;
    }

    $stmt = $pdo->prepare('SELECT * FROM subclassificaties WHERE naam LIKE :n ORDER BY naam ASC LIMIT 1');
    $stmt->execute(['n' => '%' . $naam . '%']);
    return $stmt->fetch() ?: null;
}

/** Haalt alle labels op, alfabetisch */
function get_labels(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM labels ORDER BY naam ASC')->fetchAll();
}

/** Haalt de labels op die aan 1 specifieke melding gekoppeld zijn */
function get_labels_voor_melding(PDO $pdo, int $melding_id): array
{
    $stmt = $pdo->prepare(
        'SELECT l.* FROM labels l
         JOIN melding_labels ml ON ml.label_id = l.id
         WHERE ml.melding_id = :m ORDER BY l.naam ASC'
    );
    $stmt->execute(['m' => $melding_id]);
    return $stmt->fetchAll();
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

/** Haalt de protocollen op die aan 1 specifieke subclassificatie gekoppeld zijn */
function protocollen_voor_subclassificatie(PDO $pdo, int $subclassificatie_id): array
{
    $stmt = $pdo->prepare('SELECT * FROM protocollen WHERE subclassificatie_id = :s');
    $stmt->execute(['s' => $subclassificatie_id]);
    return $stmt->fetchAll();
}

/**
 * Koppelt automatisch alle protocollen die bij deze subclassificatie horen
 * aan een melding (bv. bij het aanmaken, of bij het wijzigen van de
 * classificatie). Gebruikt INSERT IGNORE, dus al gekoppelde protocollen
 * worden overgeslagen en handmatig losgekoppelde protocollen komen niet
 * vanzelf terug tenzij de classificatie opnieuw wordt opgeslagen.
 */
function koppel_protocollen_automatisch(PDO $pdo, int $melding_id, ?int $subclassificatie_id): void
{
    if (!$subclassificatie_id) {
        return;
    }
    $stmt = $pdo->prepare('INSERT IGNORE INTO melding_protocollen (melding_id, protocol_id) VALUES (:m, :p)');
    foreach (protocollen_voor_subclassificatie($pdo, $subclassificatie_id) as $protocol) {
        $stmt->execute(['m' => $melding_id, 'p' => $protocol['id']]);
    }
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

/** Haalt de (max. 5) hyperlinks van 1 protocol op, in de ingestelde volgorde */
function get_protocol_links(PDO $pdo, int $protocol_id): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM protocol_links WHERE protocol_id = :p ORDER BY volgorde ASC, id ASC'
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

/** Losse taken van 1 melding (los van elk protocol), in de ingestelde volgorde */
function get_losse_taken(PDO $pdo, int $melding_id): array
{
    $stmt = $pdo->prepare(
        'SELECT t.*, g.naam AS afgevinkt_door_naam
         FROM melding_taken t
         LEFT JOIN gebruikers g ON g.id = t.afgevinkt_door_id
         WHERE t.melding_id = :m
         ORDER BY t.volgorde ASC, t.id ASC'
    );
    $stmt->execute(['m' => $melding_id]);
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

/** Logt een status in de geschiedenis van een melding (bij aanmaken of wijzigen) */
function log_status(PDO $pdo, int $melding_id, string $status, ?int $gebruiker_id): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO melding_status_log (melding_id, status, gebruiker_id) VALUES (:m, :s, :g)'
    );
    $stmt->execute(['m' => $melding_id, 's' => $status, 'g' => $gebruiker_id]);
}

/** Haalt de statusgeschiedenis van 1 melding op, chronologisch (oud -> nieuw) */
function get_status_geschiedenis(PDO $pdo, int $melding_id): array
{
    $stmt = $pdo->prepare(
        'SELECT sl.*, g.naam AS gebruiker_naam FROM melding_status_log sl
         LEFT JOIN gebruikers g ON g.id = sl.gebruiker_id
         WHERE sl.melding_id = :m ORDER BY sl.aangemaakt_op ASC'
    );
    $stmt->execute(['m' => $melding_id]);
    return $stmt->fetchAll();
}

/**
 * Zet de statusgeschiedenis om in tijdvakken met duur, bv.
 * [['status'=>'open','van'=>DateTime,'tot'=>DateTime,'duur_seconden'=>1234], ...]
 * Het laatste tijdvak loopt door tot nu (nog actief) of tot het laatste
 * bekende tijdstip (afgehandeld/geannuleerd, dan is 'tot' die eindtijd).
 */
function bereken_status_tijdvakken(array $geschiedenis, string $huidige_status, string $laatst_bijgewerkt): array
{
    if (!$geschiedenis) {
        return [];
    }
    $tijdvakken = [];
    $aantal = count($geschiedenis);
    for ($i = 0; $i < $aantal; $i++) {
        $van = new DateTime($geschiedenis[$i]['aangemaakt_op']);
        if ($i + 1 < $aantal) {
            $tot = new DateTime($geschiedenis[$i + 1]['aangemaakt_op']);
        } elseif (in_array($geschiedenis[$i]['status'], ['afgehandeld', 'geannuleerd'], true)) {
            $tot = new DateTime($laatst_bijgewerkt);
        } else {
            $tot = new DateTime(); // nog actief: loopt door tot nu
        }
        $tijdvakken[] = [
            'status'        => $geschiedenis[$i]['status'],
            'gebruiker'     => $geschiedenis[$i]['gebruiker_naam'],
            'van'           => $van,
            'tot'           => $tot,
            'duur_seconden' => max(0, $tot->getTimestamp() - $van->getTimestamp()),
            'lopend'        => $i + 1 === $aantal && !in_array($geschiedenis[$i]['status'], ['afgehandeld', 'geannuleerd'], true),
        ];
    }
    return $tijdvakken;
}

/** Formatteert een duur in seconden als "2u 15m" / "45m" / "3d 4u" */
function format_duur(int $seconden): string
{
    if ($seconden < 60) {
        return $seconden . 's';
    }
    $dagen = intdiv($seconden, 86400);
    $uren = intdiv($seconden % 86400, 3600);
    $minuten = intdiv($seconden % 3600, 60);

    if ($dagen > 0) {
        return $dagen . 'd ' . $uren . 'u';
    }
    if ($uren > 0) {
        return $uren . 'u ' . $minuten . 'm';
    }
    return $minuten . 'm';
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

/** De rol 'view' mag alleen de Overview-pagina bekijken (en het eigen profiel) */
function is_viewer(): bool
{
    return huidige_gebruiker_rol() === 'view';
}

/** Elke ingelogde gebruiker (beheerder, medewerker of view) mag verder */
function vereis_login(): void
{
    if (!is_ingelogd()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Voor pagina's die de rol 'view' NIET mag zien (dashboard, nieuwe melding,
 * melddetail, archief). Een viewer wordt automatisch teruggestuurd naar
 * de Overview-pagina, waar die wel mag komen.
 */
function vereis_volledige_toegang(): void
{
    vereis_login();
    if (is_viewer()) {
        header('Location: /overview.php');
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
        'view'       => 'Viewer',
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

/** Crewleden (contactpersonen zonder login), voor de "Toegewezen aan"-dropdown */
function get_crew(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM crew ORDER BY naam ASC')->fetchAll();
}

/** Actieve gebruikers, voor de toewijzingsdropdowns (Toegewezen aan / Toegewezen centralist) */
function get_toewijsbare_gebruikers(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, naam, functie FROM gebruikers WHERE actief = 1 ORDER BY naam ASC"
    )->fetchAll();
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
