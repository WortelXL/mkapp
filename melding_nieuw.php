<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titel        = trim($_POST['titel'] ?? '');
    $omschrijving = trim($_POST['omschrijving'] ?? '');
    $categorie_id = $_POST['categorie_id'] !== '' ? (int) $_POST['categorie_id'] : null;
    $locatie      = trim($_POST['locatie'] ?? '');
    $prioriteit   = $_POST['prioriteit'] ?? 'normaal';
    $gemeld_door  = trim($_POST['gemeld_door'] ?? '');

    if ($titel === '') {
        $fout = 'Vul een titel in voor de melding.';
    } elseif (!in_array($prioriteit, ['laag','normaal','hoog','kritiek'], true)) {
        $fout = 'Ongeldige prioriteit.';
    } else {
        $meld_id = genereer_meld_id($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO meldingen (meld_id, titel, omschrijving, categorie_id, locatie, prioriteit, gemeld_door, aangemaakt_door_id)
             VALUES (:meld_id, :titel, :omschrijving, :categorie_id, :locatie, :prioriteit, :gemeld_door, :aangemaakt_door_id)'
        );
        $stmt->execute([
            'meld_id'            => $meld_id,
            'titel'              => $titel,
            'omschrijving'       => $omschrijving ?: null,
            'categorie_id'       => $categorie_id,
            'locatie'            => $locatie ?: null,
            'prioriteit'         => $prioriteit,
            'gemeld_door'        => $gemeld_door ?: null,
            'aangemaakt_door_id' => $_SESSION['gebruiker_id'],
        ]);
        header('Location: /melding.php?id=' . $pdo->lastInsertId() . '&aangemaakt=1');
        exit;
    }
}

$categorieen = get_categorieen($pdo);
$actief = 'nieuw';
$paginatitel = 'Nieuwe melding';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Dag <?= bepaal_evenement_dag() ?></p>
        <h1>Nieuwe melding</h1>
        <p>Volgend meld-ID: <strong style="font-family: var(--font-mono); color: var(--amber);"><?= e(genereer_meld_id($pdo)) ?></strong></p>
    </div>
</div>

<?php if ($fout): ?>
    <div class="alert alert-error"><?= e($fout) ?></div>
<?php endif; ?>

<div class="panel">
    <form method="post">
        <div class="form-grid">
            <div class="field full">
                <label for="titel">Titel</label>
                <input type="text" id="titel" name="titel" required value="<?= e($_POST['titel'] ?? '') ?>" placeholder="Korte omschrijving van de melding">
            </div>

            <div class="field full">
                <label for="omschrijving">Omschrijving</label>
                <textarea id="omschrijving" name="omschrijving" placeholder="Wat is er precies aan de hand?"><?= e($_POST['omschrijving'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label for="locatie">Locatie</label>
                <input type="text" id="locatie" name="locatie" value="<?= e($_POST['locatie'] ?? '') ?>" placeholder="bv. Podium 2, ingang Noord">
            </div>

            <div class="field">
                <label for="gemeld_door">Gemeld door</label>
                <input type="text" id="gemeld_door" name="gemeld_door" value="<?= e($_POST['gemeld_door'] ?? '') ?>" placeholder="Naam / callsign van de melder">
                <p style="color:var(--muted); font-size:12px; margin:6px 0 0;">Wie de melding doorgaf (bezoeker, portofoonverkeer). Wordt automatisch ook vastgelegd onder jouw account (<?= e(huidige_gebruiker_naam()) ?>) als invoerder.</p>
            </div>

            <div class="field">
                <label for="categorie_id">Categorie</label>
                <select id="categorie_id" name="categorie_id">
                    <option value="">Geen categorie</option>
                    <?php foreach ($categorieen as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['naam']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="prioriteit">Prioriteit</label>
                <select id="prioriteit" name="prioriteit">
                    <?php foreach (['laag','normaal','hoog','kritiek'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($_POST['prioriteit'] ?? 'normaal') === $p ? 'selected' : '' ?>><?= prioriteit_label($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Melding aanmaken</button>
            <a href="/index.php" class="btn">Annuleren</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
