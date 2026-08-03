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
        $beschrijving = trim($_POST['beschrijving'] ?? '');

        if ($naam === '') {
            $fout = 'Vul een naam voor de locatie in.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE locaties SET naam = :n, beschrijving = :b WHERE id = :id');
                    $stmt->execute(['n' => $naam, 'b' => $beschrijving ?: null, 'id' => $id]);
                    $succes = 'Locatie bijgewerkt.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO locaties (naam, beschrijving) VALUES (:n, :b)');
                    $stmt->execute(['n' => $naam, 'b' => $beschrijving ?: null]);
                    $succes = 'Locatie "' . $naam . '" is aangemaakt.';
                }
            } catch (PDOException $e) {
                $fout = (int) $e->getCode() === 23000
                    ? 'Deze locatienaam bestaat al.'
                    : 'Er ging iets mis bij het opslaan van de locatie.';
            }
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM locaties WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Locatie verwijderd.';
    }
}

if (isset($_GET['bewerk'])) {
    $stmt = $pdo->prepare('SELECT * FROM locaties WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['bewerk']]);
    $bewerk = $stmt->fetch() ?: null;
}

$locaties = get_locaties($pdo);

$actief = 'admin';
$paginatitel = 'Locaties beheren';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Locaties</h1>
        <p>Vooraf ingestelde locaties. Typ <code>;naam</code> in de omschrijving bij een nieuwe melding, of in een notitie, om het locatieveld automatisch bij te werken.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2><?= $bewerk ? 'Locatie bewerken' : 'Nieuwe locatie' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="opslaan">
        <input type="hidden" name="id" value="<?= $bewerk['id'] ?? 0 ?>">
        <div class="field">
            <label for="naam">Naam</label>
            <input type="text" id="naam" name="naam" required value="<?= e($bewerk['naam'] ?? '') ?>" placeholder="bv. Podium 1">
        </div>
        <div class="field">
            <label for="beschrijving">Beschrijving (optioneel)</label>
            <input type="text" id="beschrijving" name="beschrijving" value="<?= e($bewerk['beschrijving'] ?? '') ?>" placeholder="bv. Hoofdpodium, zuidzijde terrein">
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary"><?= $bewerk ? 'Wijzigingen opslaan' : 'Locatie aanmaken' ?></button>
            <?php if ($bewerk): ?>
                <a href="/admin/locaties.php" class="btn">Annuleren</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Bestaande locaties</h2>
    <?php if (!$locaties): ?>
        <p style="color:var(--muted);">Nog geen locaties aangemaakt.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>Naam</th><th>Beschrijving</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($locaties as $l): ?>
            <tr>
                <td><code><?= e($l['naam']) ?></code></td>
                <td style="color:var(--muted);"><?= e($l['beschrijving'] ?: '—') ?></td>
                <td style="white-space:nowrap;">
                    <a href="/admin/locaties.php?bewerk=<?= $l['id'] ?>" class="btn btn-small">Bewerken</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Locatie \'<?= e($l['naam']) ?>\' verwijderen?');">
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
