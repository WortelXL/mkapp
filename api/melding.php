<?php
/**
 * API-endpoint om een melding aan te maken zonder in te loggen, bedoeld
 * voor koppelingen zoals een Stream Deck-knop.
 *
 * Aanroep: POST /api/melding.php
 * Authenticatie: header "Authorization: Bearer <token>", of een veld
 *                "token" in de body (handig voor plugins die geen
 *                custom headers ondersteunen).
 *
 * Body-formaat: zowel "application/json" (bv. Stream Deck-plugins zoals
 * "API Request") als "application/x-www-form-urlencoded" / multipart
 * formulierdata worden ondersteund — kies wat je koppeling het makkelijkst
 * aanbiedt.
 *
 * Velden (form-data of x-www-form-urlencoded):
 *   titel          (optioneel) - ontbreekt dit, dan wordt de titel net als
 *                    in de webinterface samengesteld uit de classificatie
 *                    ("Hoofdclassificatie - Subclassificatie")
 *   classificatie  (optioneel) - naam van een hoofd- of subclassificatie,
 *                    bv. "medisch" of "reanimatie". Wordt op dezelfde manier
 *                    herkend als het zoekcommando op het dashboard.
 *   subclassificatie (optioneel) - naam van een subclassificatie, direct en
 *                    specifiek (niet gecombineerd zoeken zoals bij
 *                    "classificatie"). Wint van "classificatie" als beide
 *                    zijn meegestuurd. De bijbehorende hoofdclassificatie
 *                    wordt er automatisch bij gezet.
 *   status         (optioneel) - open/in_behandeling/afgehandeld/geannuleerd.
 *                    Standaard "open".
 *   prioriteit     (optioneel) - laag/normaal/hoog/kritiek. Ontbreekt dit,
 *                    dan wordt de standaardprioriteit van de gevonden
 *                    subclassificatie gebruikt, anders "normaal".
 *   locatie        (optioneel)
 *   omschrijving   (optioneel)
 *   gemeld_door    (optioneel)
 *
 * Antwoord (JSON): {"success": true, "id": 42, "meld_id": "MK-D2-014"}
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function api_antwoord(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_antwoord(405, ['success' => false, 'error' => 'Alleen POST-verzoeken worden ondersteund.']);
}

$pdo = get_pdo();

// ---- Body inlezen: ondersteunt zowel JSON als normale formuliervelden ---
// (application/x-www-form-urlencoded of multipart/form-data vullen $_POST
// automatisch; bij application/json moet dat handmatig, want PHP doet dat
// niet zelf)
$content_type = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($content_type, 'application/json') !== false) {
    $json_data = json_decode((string) file_get_contents('php://input'), true);
    $velden = is_array($json_data) ? $json_data : [];
} else {
    $velden = $_POST;
}

// ---- Token ophalen: header, JSON/form-veld, of querystring --------------
$token = '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
foreach ($headers as $naam => $waarde) {
    if (strtolower($naam) === 'authorization' && stripos($waarde, 'Bearer ') === 0) {
        $token = trim(substr($waarde, 7));
    }
}
if ($token === '') {
    $token = trim((string) ($velden['token'] ?? $_GET['token'] ?? ''));
}

if ($token === '') {
    api_antwoord(401, ['success' => false, 'error' => 'Geen API-token opgegeven.']);
}

$stmt = $pdo->prepare('SELECT * FROM gebruikers WHERE api_token = :t');
$stmt->execute(['t' => $token]);
$gebruiker = $stmt->fetch();

if (!$gebruiker || !$gebruiker['actief']) {
    api_antwoord(401, ['success' => false, 'error' => 'Ongeldig of inactief API-token.']);
}

if ($gebruiker['rol'] === 'view') {
    api_antwoord(403, ['success' => false, 'error' => 'Deze gebruiker heeft alleen leestoegang en mag geen meldingen aanmaken.']);
}

// ---- Velden -------------------------------------------------------------
// "titel" is optioneel: geef je 'm niet mee (of laat 'm leeg), dan wordt de
// titel net als in de webinterface samengesteld uit de classificatie
// ("Hoofdclassificatie - Subclassificatie"). Wil je toch een eigen titel
// meesturen vanaf de Stream Deck, dan kan dat nog steeds.
$titel_override        = trim((string) ($velden['titel'] ?? ''));
$omschrijving          = trim((string) ($velden['omschrijving'] ?? ''));
$locatie               = trim((string) ($velden['locatie'] ?? ''));
$gemeld_door           = trim((string) ($velden['gemeld_door'] ?? ''));
$classificatie_tekst   = trim((string) ($velden['classificatie'] ?? ''));
$subclassificatie_tekst = trim((string) ($velden['subclassificatie'] ?? ''));
$prioriteit_input      = trim((string) ($velden['prioriteit'] ?? ''));
$status_input           = trim((string) ($velden['status'] ?? ''));

$hoofd_id = null;
$sub_id = null;
$standaard_prioriteit = null;

if ($classificatie_tekst !== '') {
    $gevonden = vind_classificatie_commando($pdo, $classificatie_tekst);
    if ($gevonden) {
        $hoofd_id = $gevonden['hoofd_id'];
        $sub_id   = $gevonden['sub_id'] ?? null;
        if ($sub_id) {
            $prio_stmt = $pdo->prepare('SELECT standaard_prioriteit FROM subclassificaties WHERE id = :id');
            $prio_stmt->execute(['id' => $sub_id]);
            $standaard_prioriteit = $prio_stmt->fetchColumn() ?: null;
        }
    }
}

// "subclassificatie" is specifieker en wint van "classificatie" als beide
// zijn meegestuurd; de hoofdclassificatie wordt automatisch overgenomen.
if ($subclassificatie_tekst !== '') {
    $sub_gevonden = vind_subclassificatie_op_naam($pdo, $subclassificatie_tekst);
    if ($sub_gevonden) {
        $hoofd_id = (int) $sub_gevonden['hoofdclassificatie_id'];
        $sub_id   = (int) $sub_gevonden['id'];
        $standaard_prioriteit = $sub_gevonden['standaard_prioriteit'] ?: null;
    }
}

$titel = $titel_override !== '' ? $titel_override : bereken_melding_titel($pdo, $hoofd_id, $sub_id);

$geldige_prioriteiten = ['laag', 'normaal', 'hoog', 'kritiek'];
if (in_array($prioriteit_input, $geldige_prioriteiten, true)) {
    $prioriteit = $prioriteit_input;
} elseif ($standaard_prioriteit) {
    $prioriteit = $standaard_prioriteit;
} else {
    $prioriteit = 'normaal';
}

$geldige_statussen = ['open', 'in_behandeling', 'afgehandeld', 'geannuleerd'];
if (in_array($status_input, $geldige_statussen, true)) {
    $status = $status_input;
} else {
    $status = 'open';
}

// ---- Melding aanmaken -----------------------------------------------------
$meld_id = genereer_meld_id($pdo);
$stmt = $pdo->prepare(
    'INSERT INTO meldingen (meld_id, titel, omschrijving, hoofdclassificatie_id, subclassificatie_id, locatie, prioriteit, status, gemeld_door, aangemaakt_door_id)
     VALUES (:meld_id, :titel, :omschrijving, :hoofd, :sub, :locatie, :prioriteit, :status, :gemeld_door, :gebruiker)'
);
$stmt->execute([
    'meld_id'      => $meld_id,
    'titel'        => $titel,
    'omschrijving' => $omschrijving ?: null,
    'hoofd'        => $hoofd_id,
    'sub'          => $sub_id,
    'locatie'      => $locatie ?: null,
    'prioriteit'   => $prioriteit,
    'status'       => $status,
    'gemeld_door'  => $gemeld_door ?: ('Stream Deck (' . $gebruiker['naam'] . ')'),
    'gebruiker'    => $gebruiker['id'],
]);

$nieuwe_melding_id = (int) $pdo->lastInsertId();
koppel_protocollen_automatisch($pdo, $nieuwe_melding_id, $sub_id);
$gekoppelde_protocol_titels = array_column(
    $sub_id ? protocollen_voor_subclassificatie($pdo, $sub_id) : [],
    'titel'
);

api_antwoord(201, [
    'success'    => true,
    'id'         => $nieuwe_melding_id,
    'meld_id'    => $meld_id,
    'status'     => $status,
    'protocollen' => $gekoppelde_protocol_titels,
]);
