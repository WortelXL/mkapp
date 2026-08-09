<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$fout = '';
$succes = '';
$bewerk = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'opslaan') {
        $id           = (int) ($_POST['id'] ?? 0);
        $naam         = trim($_POST['naam'] ?? '');
        $kleur        = trim($_POST['kleur'] ?? '#f5a524');
        $beschrijving = trim($_POST['beschrijving'] ?? '');

        if ($naam === '') {
            $fout = 'Vul een naam voor het label in.';
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $kleur)) {
            $fout = 'Kies een geldige kleur.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE labels SET naam = :n, kleur = :k, beschrijving = :b WHERE id = :id');
                    $stmt->execute(['n' => $naam, 'k' => $kleur, 'b' => $beschrijving ?: null, 'id' => $id]);
                    $succes = 'Label bijgewerkt.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO labels (naam, kleur, beschrijving) VALUES (:n, :k, :b)');
                    $stmt->execute(['n' => $naam, 'k' => $kleur, 'b' => $beschrijving ?: null]);
                    $succes = 'Label "' . $naam . '" is aangemaakt.';
                }
            } catch (PDOException $e) {
                $fout = (int) $e->getCode() === 23000
                    ? 'Deze labelnaam bestaat al.'
                    : 'Er ging iets mis bij het opslaan van het label.';
            }
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM labels WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Label verwijderd.';
    }
}

if (isset($_GET['bewerk'])) {
    $stmt = $pdo->prepare('SELECT * FROM labels WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['bewerk']]);
    $bewerk = $stmt->fetch() ?: null;
}

$labels = get_labels($pdo);

$actief = 'admin';
$paginatitel = 'Labels beheren';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Labels</h1>
        <p>Labels om meldingen te markeren voor latere opvolging — werkt op zowel actieve als afgesloten (gearchiveerde) meldingen.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2><?= $bewerk ? 'Label bewerken' : 'Nieuw label' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="opslaan">
        <input type="hidden" name="id" value="<?= $bewerk['id'] ?? 0 ?>">
        <div class="field">
            <label for="naam">Naam</label>
            <input type="text" id="naam" name="naam" required value="<?= e($bewerk['naam'] ?? '') ?>" placeholder="bv. Later opvolgen">
        </div>
        <div class="field">
            <label for="kleur">Kleur</label>
            <input type="color" id="kleur" name="kleur" value="<?= e($bewerk['kleur'] ?? '#f5a524') ?>" style="height:42px; padding:4px;">
        </div>
        <div class="field full">
            <label for="beschrijving">Beschrijving (optioneel)</label>
            <input type="text" id="beschrijving" name="beschrijving" value="<?= e($bewerk['beschrijving'] ?? '') ?>" placeholder="Korte toelichting">
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary"><?= $bewerk ? 'Wijzigingen opslaan' : 'Label aanmaken' ?></button>
            <?php if ($bewerk): ?>
                <a href="/admin/labels.php" class="btn">Annuleren</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Bestaande labels</h2>
    <?php if (!$labels): ?>
        <p style="color:var(--muted);">Nog geen labels aangemaakt.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>Label</th><th>Beschrijving</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($labels as $l): ?>
            <tr>
                <td><span class="color-dot" style="background:<?= e($l['kleur']) ?>;"></span><?= e($l['naam']) ?></td>
                <td style="color:var(--muted);"><?= e($l['beschrijving'] ?: '—') ?></td>
                <td style="white-space:nowrap;">
                    <a href="/admin/labels.php?bewerk=<?= $l['id'] ?>" class="btn btn-small">Bewerken</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Label \'<?= e($l['naam']) ?>\' verwijderen? Wordt dan ook overal losgekoppeld van meldingen.');">
                        <input type="hidden" name="actie" value="verwijderen">
                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button type="submit" class="btn btn-small btn-danger">Verwijderen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
