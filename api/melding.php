<?php
/**
 * API-endpoint om een melding aan te maken zonder in te loggen, bedoeld
 * voor koppelingen zoals een Stream Deck-knop.
 *
 * Aanroep: POST /api/melding.php
 * Authenticatie: header "Authorization: Bearer <token>", of een veld
 *                "token" in de POST-body (handig voor plugins die geen
 *                custom headers ondersteunen).
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

// ---- Token ophalen: header of body -------------------------------------
$token = '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
foreach ($headers as $naam => $waarde) {
    if (strtolower($naam) === 'authorization' && stripos($waarde, 'Bearer ') === 0) {
        $token = trim(substr($waarde, 7));
    }
}
if ($token === '') {
    $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
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

// ---- Velden -------------------------------------------------------------
// "titel" is optioneel: geef je 'm niet mee (of laat 'm leeg), dan wordt de
// titel net als in de webinterface samengesteld uit de classificatie
// ("Hoofdclassificatie - Subclassificatie"). Wil je toch een eigen titel
// meesturen vanaf de Stream Deck, dan kan dat nog steeds.
$titel_override        = trim($_POST['titel'] ?? '');
$omschrijving          = trim($_POST['omschrijving'] ?? '');
$locatie               = trim($_POST['locatie'] ?? '');
$gemeld_door           = trim($_POST['gemeld_door'] ?? '');
$classificatie_tekst   = trim($_POST['classificatie'] ?? '');
$subclassificatie_tekst = trim($_POST['subclassificatie'] ?? '');
$prioriteit_input      = trim($_POST['prioriteit'] ?? '');
$status_input          = trim($_POST['status'] ?? '');

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

api_antwoord(201, [
    'success'  => true,
    'id'       => (int) $pdo->lastInsertId(),
    'meld_id'  => $meld_id,
    'status'   => $status,
]);
