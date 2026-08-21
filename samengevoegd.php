<?php
require_once __DIR__ . '/includes/functions.php';
vereis_volledige_toegang();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'melding_ontkoppelen') {
    $koppeling_id = (int) ($_POST['koppeling_id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM melding_koppelingen WHERE id = :id');
    $stmt->execute(['id' => $koppeling_id]);
    header('Location: /samengevoegd.php');
    exit;
}

$actieve_sleutels = statussen_sleutels(get_actieve_statussen($pdo));
$types = koppeling_types();

// Alleen koppelingen tonen waarbij minstens 1 van de 2 meldingen nog
// actief is -- koppelingen tussen twee al helemaal afgeronde meldingen
// zijn hier niet meer relevant (die blijven wel gewoon zichtbaar op de
// melddetailpagina's zelf).
$koppelingen = [];
if ($actieve_sleutels) {
    $plekhouders = implode(',', array_fill(0, count($actieve_sleutels), '?'));
    $sql = "SELECT k.id AS koppeling_id, k.type, k.aangemaakt_op, g.naam AS aangemaakt_door_naam,
                   a.id AS a_id, a.meld_id AS a_meld_id, a.titel AS a_titel, a.status AS a_status, a.prioriteit AS a_prioriteit,
                   b.id AS b_id, b.meld_id AS b_meld_id, b.titel AS b_titel, b.status AS b_status, b.prioriteit AS b_prioriteit
            FROM melding_koppelingen k
            JOIN meldingen a ON a.id = k.melding_id
            JOIN meldingen b ON b.id = k.gekoppelde_melding_id
            LEFT JOIN gebruikers g ON g.id = k.aangemaakt_door_id
            WHERE a.status IN ($plekhouders) OR b.status IN ($plekhouders)
            ORDER BY k.aangemaakt_op DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($actieve_sleutels, $actieve_sleutels));
    $koppelingen = $stmt->fetchAll();
}

$actief = 'samengevoegd';
$paginatitel = 'Samengevoegd';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Overzicht</p>
        <h1>Samengevoegd</h1>
        <p>Alle koppelingen tussen meldingen waarbij er nog minstens 1 actief is (bv. een EHBO-inzet met een gekoppelde AMBU-inzet). Beide meldingen blijven zelfstandig — dit toont alleen de relatie.</p>
    </div>
</div>

<div class="melding-list">
    <?php if (!$koppelingen): ?>
        <div class="empty">Geen gekoppelde meldingen met een actieve status.</div>
    <?php endif; ?>

    <?php foreach ($koppelingen as $k): ?>
        <?php $type_label = $types[$k['type']]['label'] ?? $k['type']; ?>
        <div class="koppeling-rij">
            <a href="/melding.php?id=<?= (int) $k['a_id'] ?>" class="koppeling-melding">
                <span class="melding-id"><?= e($k['a_meld_id']) ?></span>
                <span class="titel"><?= e($k['a_titel']) ?></span>
                <span style="display:flex; gap:6px; margin-top:6px;">
                    <span class="tag <?= prioriteit_class($k['a_prioriteit']) ?>"><?= prioriteit_label($k['a_prioriteit']) ?></span>
                    <?= status_tag_html($k['a_status']) ?>
                </span>
            </a>
            <div class="koppeling-midden">
                <span class="koppeling-type"><?= e($type_label) ?></span>
                <span class="koppeling-pijl">&rarr;</span>
            </div>
            <a href="/melding.php?id=<?= (int) $k['b_id'] ?>" class="koppeling-melding">
                <span class="melding-id"><?= e($k['b_meld_id']) ?></span>
                <span class="titel"><?= e($k['b_titel']) ?></span>
                <span style="display:flex; gap:6px; margin-top:6px;">
                    <span class="tag <?= prioriteit_class($k['b_prioriteit']) ?>"><?= prioriteit_label($k['b_prioriteit']) ?></span>
                    <?= status_tag_html($k['b_status']) ?>
                </span>
            </a>
            <div class="koppeling-acties">
                <span class="koppeling-meta">
                    Gekoppeld door <?= e($k['aangemaakt_door_naam'] ?: 'onbekend') ?><br>
                    <?= (new DateTime($k['aangemaakt_op']))->format('d-m-Y H:i') ?>
                </span>
                <form method="post" onsubmit="return confirm('Koppeling verwijderen? De meldingen zelf blijven gewoon bestaan.');">
                    <input type="hidden" name="actie" value="melding_ontkoppelen">
                    <input type="hidden" name="koppeling_id" value="<?= $k['koppeling_id'] ?>">
                    <button type="submit" class="btn btn-small btn-danger">Loskoppelen</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
