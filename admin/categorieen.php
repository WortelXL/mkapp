<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$fout = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'aanmaken') {
        $naam         = trim($_POST['naam'] ?? '');
        $kleur        = trim($_POST['kleur'] ?? '#f5a524');
        $beschrijving = trim($_POST['beschrijving'] ?? '');

        if ($naam === '') {
            $fout = 'Vul een naam voor de categorie in.';
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $kleur)) {
            $fout = 'Kies een geldige kleur.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO categorieen (naam, kleur, beschrijving) VALUES (:n, :k, :b)'
            );
            $stmt->execute(['n' => $naam, 'k' => $kleur, 'b' => $beschrijving ?: null]);
            $succes = 'Categorie "' . $naam . '" is aangemaakt.';
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM categorieen WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Categorie is verwijderd.';
    }
}

$categorieen = get_categorieen($pdo);

$actief = 'admin';
$paginatitel = 'Categorieen beheren';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Categorieen</h1>
        <p>Meldingscategorieen aanmaken of verwijderen.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2>Nieuwe categorie</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="aanmaken">
        <div class="field">
            <label for="naam">Naam</label>
            <input type="text" id="naam" name="naam" required placeholder="bv. Medisch">
        </div>
        <div class="field">
            <label for="kleur">Kleur</label>
            <input type="color" id="kleur" name="kleur" value="#f5a524" style="height:42px; padding:4px;">
        </div>
        <div class="field full">
            <label for="beschrijving">Beschrijving (optioneel)</label>
            <input type="text" id="beschrijving" name="beschrijving" placeholder="Korte toelichting">
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary">Categorie aanmaken</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Bestaande categorieen</h2>
    <?php if (!$categorieen): ?>
        <p style="color:var(--muted);">Nog geen categorieen aangemaakt.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>Categorie</th><th>Beschrijving</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($categorieen as $c): ?>
            <tr>
                <td><span class="color-dot" style="background:<?= e($c['kleur']) ?>;"></span><?= e($c['naam']) ?></td>
                <td style="color:var(--muted);"><?= e($c['beschrijving'] ?: '—') ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Categorie \'<?= e($c['naam']) ?>\' verwijderen? Meldingen met deze categorie worden niet verwijderd, maar verliezen hun categorie.');">
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
