<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

// ---- Filters ---------------------------------------------------------
$f_status     = $_GET['status'] ?? '';
$f_categorie  = $_GET['categorie'] ?? '';
$f_prioriteit = $_GET['prioriteit'] ?? '';
$f_zoek       = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($f_status !== '' && in_array($f_status, ['open','in_behandeling','afgehandeld','geannuleerd'], true)) {
    $where[] = 'm.status = :status';
    $params['status'] = $f_status;
}
if ($f_categorie !== '' && ctype_digit($f_categorie)) {
    $where[] = 'm.categorie_id = :categorie';
    $params['categorie'] = (int) $f_categorie;
}
if ($f_prioriteit !== '' && in_array($f_prioriteit, ['laag','normaal','hoog','kritiek'], true)) {
    $where[] = 'm.prioriteit = :prioriteit';
    $params['prioriteit'] = $f_prioriteit;
}
if ($f_zoek !== '') {
    $where[] = '(m.titel LIKE :zoek OR m.meld_id LIKE :zoek OR m.locatie LIKE :zoek)';
    $params['zoek'] = '%' . $f_zoek . '%';
}

$sql = "SELECT m.*, c.naam AS categorie_naam, c.kleur AS categorie_kleur, g.naam AS aangemaakt_door_naam
        FROM meldingen m
        LEFT JOIN categorieen c ON c.id = m.categorie_id
        LEFT JOIN gebruikers g ON g.id = m.aangemaakt_door_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY FIELD(m.prioriteit,"kritiek","hoog","normaal","laag"), m.aangemaakt_op DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meldingen = $stmt->fetchAll();

// ---- Statusbord (tellingen) -------------------------------------------
$tellingen = $pdo->query(
    "SELECT status, COUNT(*) AS aantal FROM meldingen GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$open           = $tellingen['open'] ?? 0;
$in_behandeling = $tellingen['in_behandeling'] ?? 0;
$afgehandeld    = $tellingen['afgehandeld'] ?? 0;
$kritiek_open   = (int) $pdo->query(
    "SELECT COUNT(*) FROM meldingen WHERE prioriteit='kritiek' AND status NOT IN ('afgehandeld','geannuleerd')"
)->fetchColumn();

$categorieen = get_categorieen($pdo);

$actief = 'dashboard';
$paginatitel = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Dag <?= bepaal_evenement_dag() ?> van <?= EVENT_AANTAL_DAGEN ?></p>
        <h1>Overzicht meldingen</h1>
        <p>Live status van alle meldingen tijdens het evenement.</p>
    </div>
    <a href="/melding_nieuw.php" class="btn btn-primary">+ Nieuwe melding</a>
</div>

<div class="board">
    <div class="board-cell <?= $kritiek_open > 0 ? 'pulse' : '' ?>">
        <div class="num c-red"><?= $kritiek_open ?></div>
        <div class="lbl">Kritiek &amp; open</div>
    </div>
    <div class="board-cell">
        <div class="num c-red"><?= $open ?></div>
        <div class="lbl">Open</div>
    </div>
    <div class="board-cell">
        <div class="num c-amber"><?= $in_behandeling ?></div>
        <div class="lbl">In behandeling</div>
    </div>
    <div class="board-cell">
        <div class="num c-green"><?= $afgehandeld ?></div>
        <div class="lbl">Afgehandeld</div>
    </div>
</div>

<form class="filters" method="get">
    <input type="text" name="q" placeholder="Zoek op titel, ID of locatie..." value="<?= e($f_zoek) ?>">
    <select name="status">
        <option value="">Alle statussen</option>
        <?php foreach (['open','in_behandeling','afgehandeld','geannuleerd'] as $s): ?>
            <option value="<?= $s ?>" <?= $f_status === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="prioriteit">
        <option value="">Alle prioriteiten</option>
        <?php foreach (['kritiek','hoog','normaal','laag'] as $p): ?>
            <option value="<?= $p ?>" <?= $f_prioriteit === $p ? 'selected' : '' ?>><?= prioriteit_label($p) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="categorie">
        <option value="">Alle categorieen</option>
        <?php foreach ($categorieen as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $f_categorie == $c['id'] ? 'selected' : '' ?>><?= e($c['naam']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-small">Filteren</button>
    <?php if ($f_status || $f_categorie || $f_prioriteit || $f_zoek): ?>
        <a href="/index.php" class="btn btn-small">Wissen</a>
    <?php endif; ?>
</form>

<div class="melding-list">
    <?php if (!$meldingen): ?>
        <div class="empty">Geen meldingen gevonden. Pas de filters aan of maak een nieuwe melding aan.</div>
    <?php endif; ?>

    <?php foreach ($meldingen as $m): ?>
        <a href="/melding.php?id=<?= (int) $m['id'] ?>" class="melding-row prio-<?= e($m['prioriteit']) ?>">
            <span class="melding-id"><?= e($m['meld_id']) ?></span>
            <span class="melding-main">
                <span class="titel"><?= e($m['titel']) ?></span>
                <span class="meta">
                    <?= e($m['locatie'] ?: 'Geen locatie opgegeven') ?>
                    · <?= (new DateTime($m['aangemaakt_op']))->format('d-m H:i') ?>
                    · ingevoerd door <?= e($m['aangemaakt_door_naam'] ?: 'onbekend') ?>
                </span>
            </span>
            <?php if ($m['categorie_naam']): ?>
                <span class="cat-chip" style="background: <?= e($m['categorie_kleur']) ?>22; color: <?= e($m['categorie_kleur']) ?>;">
                    <?= e($m['categorie_naam']) ?>
                </span>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <span class="tag <?= prioriteit_class($m['prioriteit']) ?>"><?= prioriteit_label($m['prioriteit']) ?></span>
            <span class="tag <?= status_class($m['status']) ?>">
                <span class="tag-dot"></span><?= status_label($m['status']) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
