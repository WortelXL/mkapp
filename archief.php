<?php
require_once __DIR__ . '/includes/functions.php';
vereis_volledige_toegang();
$pdo = get_pdo();

// ---- Filters ---------------------------------------------------------
$f_status     = $_GET['status'] ?? '';
$f_hoofd      = $_GET['hoofd'] ?? '';
$f_sub        = $_GET['sub'] ?? '';
$f_prioriteit = $_GET['prioriteit'] ?? '';
$f_label      = $_GET['label'] ?? '';
$f_zoek       = trim($_GET['q'] ?? '');

// Commando in de zoekbalk: "-medisch" of "-reanimatie" (zelfde als dashboard)
$commando_niet_gevonden = null;
if ($f_zoek !== '' && $f_zoek[0] === '-') {
    $commando = trim(substr($f_zoek, 1));
    $gevonden = $commando !== '' ? vind_classificatie_commando($pdo, $commando) : null;

    if ($gevonden) {
        $redirect_params = ['hoofd' => $gevonden['hoofd_id'], 'via' => 'commando'];
        if (!empty($gevonden['sub_id'])) {
            $redirect_params['sub'] = $gevonden['sub_id'];
        }
        if ($f_status !== '') {
            $redirect_params['status'] = $f_status;
        }
        if ($f_prioriteit !== '') {
            $redirect_params['prioriteit'] = $f_prioriteit;
        }
        header('Location: /archief.php?' . http_build_query($redirect_params));
        exit;
    }

    $commando_niet_gevonden = $commando;
    $f_zoek = $commando;
}

// Archief = alleen afgeronde meldingen (afgehandeld of geannuleerd),
// tenzij er specifiek op 1 van de twee gefilterd wordt.
if ($f_status === 'afgehandeld' || $f_status === 'geannuleerd') {
    $status_conditie = 'm.status = :status';
    $status_params = ['status' => $f_status];
} else {
    $f_status = '';
    $status_conditie = "m.status IN ('afgehandeld', 'geannuleerd')";
    $status_params = [];
}

$where  = [$status_conditie];
$params = $status_params;

if ($f_hoofd !== '' && ctype_digit($f_hoofd)) {
    $where[] = 'm.hoofdclassificatie_id = :hoofd';
    $params['hoofd'] = (int) $f_hoofd;
}
if ($f_sub !== '' && ctype_digit($f_sub)) {
    $where[] = 'm.subclassificatie_id = :sub';
    $params['sub'] = (int) $f_sub;
}
if ($f_prioriteit !== '' && in_array($f_prioriteit, ['laag','normaal','hoog','kritiek'], true)) {
    $where[] = 'm.prioriteit = :prioriteit';
    $params['prioriteit'] = $f_prioriteit;
}
if ($f_label !== '' && ctype_digit($f_label)) {
    $where[] = 'EXISTS (SELECT 1 FROM melding_labels ml2 WHERE ml2.melding_id = m.id AND ml2.label_id = :label)';
    $params['label'] = (int) $f_label;
}
if ($f_zoek !== '') {
    $where[] = '(m.titel LIKE :zoek OR m.meld_id LIKE :zoek OR m.locatie LIKE :zoek)';
    $params['zoek'] = '%' . $f_zoek . '%';
}

$MAX_RESULTATEN = 150;

$sql = "SELECT m.*,
               h.naam AS hoofd_naam, h.kleur AS hoofd_kleur,
               s.naam AS sub_naam,
               g.naam AS aangemaakt_door_naam
        FROM meldingen m
        LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
        LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
        LEFT JOIN gebruikers g ON g.id = m.aangemaakt_door_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.bijgewerkt_op DESC
        LIMIT $MAX_RESULTATEN";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meldingen = $stmt->fetchAll();

// Labels per melding ophalen (los van de hoofdquery, voorkomt GROUP BY-gedoe
// met de andere joins hierboven)
$labels_per_melding = [];
if ($meldingen) {
    $ids = array_column($meldingen, 'id');
    $plekhouders = implode(',', array_fill(0, count($ids), '?'));
    $labels_stmt = $pdo->prepare(
        "SELECT ml.melding_id, l.* FROM melding_labels ml
         JOIN labels l ON l.id = ml.label_id
         WHERE ml.melding_id IN ($plekhouders)
         ORDER BY l.naam ASC"
    );
    $labels_stmt->execute($ids);
    foreach ($labels_stmt->fetchAll() as $rij) {
        $labels_per_melding[$rij['melding_id']][] = $rij;
    }
}

// ---- Tellingen ----------------------------------------------------------
$totaal_afgehandeld = (int) $pdo->query("SELECT COUNT(*) FROM meldingen WHERE status = 'afgehandeld'")->fetchColumn();
$totaal_geannuleerd = (int) $pdo->query("SELECT COUNT(*) FROM meldingen WHERE status = 'geannuleerd'")->fetchColumn();

$hoofdclassificaties = get_hoofdclassificaties($pdo);
$alle_labels = get_labels($pdo);

$actief = 'archief';
$paginatitel = 'Archief';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Archief</p>
        <h1>Afgeronde meldingen</h1>
        <p>Overzicht van alle afgehandelde en geannuleerde meldingen.</p>
    </div>
</div>

<div class="board">
    <div class="board-cell">
        <div class="num c-green"><?= $totaal_afgehandeld ?></div>
        <div class="lbl">Afgehandeld</div>
    </div>
    <div class="board-cell">
        <div class="num c-muted"><?= $totaal_geannuleerd ?></div>
        <div class="lbl">Geannuleerd</div>
    </div>
</div>

<?php if (isset($_GET['via']) && $_GET['via'] === 'commando' && $f_hoofd): ?>
    <?php
        $hoofd_naam_geselecteerd = '';
        foreach ($hoofdclassificaties as $h) {
            if ((string) $h['id'] === (string) $f_hoofd) {
                $hoofd_naam_geselecteerd = $h['naam'];
                break;
            }
        }
        $sub_naam_geselecteerd = null;
        if ($f_sub) {
            $sub_stmt = $pdo->prepare('SELECT naam FROM subclassificaties WHERE id = :id');
            $sub_stmt->execute(['id' => $f_sub]);
            $sub_naam_geselecteerd = $sub_stmt->fetchColumn() ?: null;
        }
    ?>
    <div class="alert alert-success">
        Filter toegepast via commando: <strong><?= e($hoofd_naam_geselecteerd) ?></strong><?= $sub_naam_geselecteerd ? ' · ' . e($sub_naam_geselecteerd) : '' ?>
    </div>
<?php endif; ?>

<?php if ($commando_niet_gevonden !== null): ?>
    <div class="alert alert-error">
        Geen classificatie gevonden voor "-<?= e($commando_niet_gevonden) ?>" — in plaats daarvan gezocht op tekst.
    </div>
<?php endif; ?>

<form class="filters" method="get">
    <input type="text" name="q" placeholder="Zoek, of typ -classificatienaam..." value="<?= e($f_zoek) ?>">
    <select name="status">
        <option value="">Afgehandeld + geannuleerd</option>
        <option value="afgehandeld" <?= $f_status === 'afgehandeld' ? 'selected' : '' ?>>Alleen afgehandeld</option>
        <option value="geannuleerd" <?= $f_status === 'geannuleerd' ? 'selected' : '' ?>>Alleen geannuleerd</option>
    </select>
    <select name="prioriteit">
        <option value="">Alle prioriteiten</option>
        <?php foreach (['kritiek','hoog','normaal','laag'] as $p): ?>
            <option value="<?= $p ?>" <?= $f_prioriteit === $p ? 'selected' : '' ?>><?= prioriteit_label($p) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="hoofd">
        <option value="">Alle hoofdclassificaties</option>
        <?php foreach ($hoofdclassificaties as $h): ?>
            <option value="<?= $h['id'] ?>" <?= $f_hoofd == $h['id'] ? 'selected' : '' ?>><?= e($h['naam']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="label">
        <option value="">Alle labels</option>
        <?php foreach ($alle_labels as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $f_label == $l['id'] ? 'selected' : '' ?>><?= e($l['naam']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($f_sub): ?>
        <input type="hidden" name="sub" value="<?= (int) $f_sub ?>">
    <?php endif; ?>
    <button type="submit" class="btn btn-small">Filteren</button>
    <?php if ($f_status || $f_hoofd || $f_sub || $f_prioriteit || $f_label || $f_zoek): ?>
        <a href="/archief.php" class="btn btn-small">Wissen</a>
    <?php endif; ?>
</form>

<div class="melding-list">
    <?php if (!$meldingen): ?>
        <div class="empty">Geen afgeronde meldingen gevonden. Pas de filters aan.</div>
    <?php endif; ?>

    <?php foreach ($meldingen as $m): ?>
        <a href="/melding.php?id=<?= (int) $m['id'] ?>" class="melding-row prio-<?= e($m['prioriteit']) ?>">
            <span class="melding-id"><?= e($m['meld_id']) ?></span>
            <span class="melding-main">
                <span class="titel"><?= e($m['titel']) ?></span>
                <span class="meta">
                    <?= e($m['locatie'] ?: 'Geen locatie opgegeven') ?>
                    · afgerond <?= (new DateTime($m['bijgewerkt_op']))->format('d-m H:i') ?>
                    · ingevoerd door <?= e($m['aangemaakt_door_naam'] ?: 'onbekend') ?>
                </span>
                <?php if (!empty($labels_per_melding[$m['id']])): ?>
                    <span class="meta">
                        <?php foreach ($labels_per_melding[$m['id']] as $l): ?>
                            <span class="cat-chip" style="background: <?= e($l['kleur']) ?>22; color: <?= e($l['kleur']) ?>; margin-right:4px;"><?= e($l['naam']) ?></span>
                        <?php endforeach; ?>
                    </span>
                <?php endif; ?>
            </span>
            <?php if ($m['hoofd_naam']): ?>
                <span class="cat-chip" style="background: <?= e($m['hoofd_kleur']) ?>22; color: <?= e($m['hoofd_kleur']) ?>;">
                    <?= e($m['hoofd_naam']) ?><?= $m['sub_naam'] ? ' · ' . e($m['sub_naam']) : '' ?>
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

    <?php if (count($meldingen) === $MAX_RESULTATEN): ?>
        <p style="color: var(--muted); font-size: 12.5px; text-align:center; margin-top: 8px;">
            Toont de meest recente <?= $MAX_RESULTATEN ?> resultaten. Verfijn de filters voor een specifieker overzicht.
        </p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
