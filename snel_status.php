<?php
/**
 * Snelle statuswijziging vanaf een overzichtspagina (dashboard/archief),
 * zonder naar de melddetailpagina te hoeven navigeren. Stuurt na afloop
 * terug naar de pagina waar de aanvraag vandaan kwam (incl. actieve
 * filters), of naar het dashboard als dat niet veilig te bepalen is.
 */
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

// Alleen een relatief pad binnen deze applicatie toestaan (voorkomt open
// redirect via een extern of protocol-relatief adres)
function veilig_terug_pad(string $pad): string
{
    if ($pad !== '' && $pad[0] === '/' && (strlen($pad) < 2 || $pad[1] !== '/')) {
        return $pad;
    }
    return '/index.php';
}

$melding_id = (int) ($_POST['melding_id'] ?? 0);
$status     = $_POST['status'] ?? '';
$terug      = veilig_terug_pad($_POST['terug'] ?? '/index.php');

if ($melding_id > 0 && is_geldige_status($pdo, $status)) {
    $huidige_stmt = $pdo->prepare('SELECT status, meld_id, titel FROM meldingen WHERE id = :id');
    $huidige_stmt->execute(['id' => $melding_id]);
    $huidige_melding = $huidige_stmt->fetch();

    if ($huidige_melding) {
        $stmt = $pdo->prepare(
            'UPDATE meldingen SET status = :status, bijgewerkt_door_id = :gebruiker WHERE id = :id'
        );
        $stmt->execute([
            'status'    => $status,
            'gebruiker' => $_SESSION['gebruiker_id'],
            'id'        => $melding_id,
        ]);
        if ($status !== $huidige_melding['status']) {
            log_status($pdo, $melding_id, $status, $_SESSION['gebruiker_id']);
            verstuur_webhooks($pdo, 'status_gewijzigd', [
                'id' => $melding_id,
                'meld_id' => $huidige_melding['meld_id'],
                'titel' => $huidige_melding['titel'],
                'oude_status' => $huidige_melding['status'],
                'nieuwe_status' => $status,
            ]);
        }
    }
}

header('Location: ' . $terug);
exit;
