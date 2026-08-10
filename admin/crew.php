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
        $id             = (int) ($_POST['id'] ?? 0);
        $naam           = trim($_POST['naam'] ?? '');
        $functie        = trim($_POST['functie'] ?? '');
        $telefoonnummer = trim($_POST['telefoonnummer'] ?? '');

        if ($naam === '') {
            $fout = 'Vul een naam in.';
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('UPDATE crew SET naam = :n, functie = :f, telefoonnummer = :t WHERE id = :id');
            $stmt->execute(['n' => $naam, 'f' => $functie ?: null, 't' => $telefoonnummer ?: null, 'id' => $id]);
            $succes = 'Crewlid bijgewerkt.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO crew (naam, functie, telefoonnummer) VALUES (:n, :f, :t)');
            $stmt->execute(['n' => $naam, 'f' => $functie ?: null, 't' => $telefoonnummer ?: null]);
            $succes = 'Crewlid "' . $naam . '" is toegevoegd.';
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM crew WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Crewlid verwijderd.';
    }
}

if (isset($_GET['bewerk'])) {
    $stmt = $pdo->prepare('SELECT * FROM crew WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['bewerk']]);
    $bewerk = $stmt->fetch() ?: null;
}

$crew = get_crew($pdo);

$actief = 'admin';
$paginatitel = 'Crew beheren';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Crew</h1>
        <p>Contactpersonen die geen account/login hebben, maar wel aan een melding toegewezen kunnen worden via "Toegewezen aan" — een soort telefoonlijst.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2><?= $bewerk ? 'Crewlid bewerken' : 'Nieuw crewlid' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="opslaan">
        <input type="hidden" name="id" value="<?= $bewerk['id'] ?? 0 ?>">
        <div class="field">
            <label for="naam">Naam</label>
            <input type="text" id="naam" name="naam" required value="<?= e($bewerk['naam'] ?? '') ?>" placeholder="bv. Jan de Boer">
        </div>
        <div class="field">
            <label for="functie">Functie</label>
            <input type="text" id="functie" name="functie" value="<?= e($bewerk['functie'] ?? '') ?>" placeholder="bv. EHBO, Beveiliging, Techniek">
        </div>
        <div class="field">
            <label for="telefoonnummer">Telefoonnummer</label>
            <input type="text" id="telefoonnummer" name="telefoonnummer" value="<?= e($bewerk['telefoonnummer'] ?? '') ?>" placeholder="bv. 06-12345678">
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary"><?= $bewerk ? 'Wijzigingen opslaan' : 'Crewlid toevoegen' ?></button>
            <?php if ($bewerk): ?>
                <a href="/admin/crew.php" class="btn">Annuleren</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Crewlijst</h2>
    <?php if (!$crew): ?>
        <p style="color:var(--muted);">Nog geen crewleden toegevoegd.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>Naam</th><th>Functie</th><th>Telefoonnummer</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($crew as $c): ?>
            <tr>
                <td><?= e($c['naam']) ?></td>
                <td style="color:var(--muted);"><?= e($c['functie'] ?: '—') ?></td>
                <td style="font-family:var(--font-mono); color:var(--muted);">
                    <?= $c['telefoonnummer'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $c['telefoonnummer'])) . '" style="color:var(--text);">' . e($c['telefoonnummer']) . '</a>' : '—' ?>
                </td>
                <td style="white-space:nowrap;">
                    <a href="/admin/crew.php?bewerk=<?= $c['id'] ?>" class="btn btn-small">Bewerken</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Crewlid \'<?= e($c['naam']) ?>\' verwijderen? Meldingen die aan deze persoon zijn toegewezen verliezen die koppeling.');">
                        <input type="hidden" name="actie" value="verwijderen">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
