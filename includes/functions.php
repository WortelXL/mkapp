<?php
require_once __DIR__ . '/db.php';

/** Korte htmlspecialchars helper */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Bepaalt op welke festivaldag (1, 2, 3...) een tijdstip valt */
function bepaal_evenement_dag(?DateTime $moment = null): int
{
    $moment = $moment ?? new DateTime();
    $start  = new DateTime(EVENT_START_DATUM);
    $diff   = (int) $start->diff($moment)->format('%r%a');
    $dag    = $diff + 1;
    if ($dag < 1) {
        $dag = 1;
    }
    if ($dag > EVENT_AANTAL_DAGEN) {
        $dag = EVENT_AANTAL_DAGEN;
    }
    return $dag;
}

/** Genereert een uniek, oplopend meld-ID zoals MK-D2-014 */
function genereer_meld_id(PDO $pdo): string
{
    $dag    = bepaal_evenement_dag();
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

/** Haalt alle protocollen op */
function get_protocollen(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM protocollen ORDER BY titel ASC')->fetchAll();
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
