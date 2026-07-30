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
        $titel        = trim($_POST['titel'] ?? '');
        $categorie_id = $_POST['categorie_id'] !== '' ? (int) $_POST['categorie_id'] : null;
        $inhoud       = trim($_POST['inhoud'] ?? '');

        if ($titel === '' || $inhoud === '') {
            $fout = 'Vul zowel een titel als de inhoud van het protocol in.';
        } elseif ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE protocollen SET titel = :t, categorie_id = :c, inhoud = :i WHERE id = :id'
            );
            $stmt->execute(['t' => $titel, 'c' => $categorie_id, 'i' => $inhoud, 'id' => $id]);
            $succes = 'Protocol bijgewerkt.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO protocollen (titel, categorie_id, inhoud) VALUES (:t, :c, :i)'
            );
            $stmt->execute(['t' => $titel, 'c' => $categorie_id, 'i' => $inhoud]);
            $succes = 'Protocol aangemaakt.';
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM protocollen WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Protocol verwijderd.';
    }
}

if (isset($_GET['bewerk'])) {
    $stmt = $pdo->prepare('SELECT * FROM protocollen WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['bewerk']]);
    $bewerk = $stmt->fetch() ?: null;
}

$protocollen = get_protocollen($pdo);
$categorieen = get_categorieen($pdo);

$actief = 'admin';
$paginatitel = 'Protocollen beheren';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Protocollen</h1>
        <p>Standaardprocedures die aan meldingen gekoppeld kunnen worden.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2><?= $bewerk ? 'Protocol bewerken' : 'Nieuw protocol' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="opslaan">
        <input type="hidden" name="id" value="<?= $bewerk['id'] ?? 0 ?>">
        <div class="field full">
            <label for="titel">Titel</label>
            <input type="text" id="titel" name="titel" required value="<?= e($bewerk['titel'] ?? '') ?>" placeholder="bv. Protocol vermist kind">
        </div>
        <div class="field full">
            <label for="categorie_id">Gekoppelde categorie (optioneel)</label>
            <select id="categorie_id" name="categorie_id">
                <option value="">Geen specifieke categorie</option>
                <?php foreach ($categorieen as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($bewerk['categorie_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= e($c['naam']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field full">
            <label for="inhoud">Inhoud / stappen</label>
            <textarea id="inhoud" name="inhoud" required style="min-height:160px;" placeholder="Beschrijf de te volgen stappen..."><?= e($bewerk['inhoud'] ?? '') ?></textarea>
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary"><?= $bewerk ? 'Wijzigingen opslaan' : 'Protocol aanmaken' ?></button>
            <?php if ($bewerk): ?>
                <a href="/admin/protocollen.php" class="btn">Annuleren</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Bestaande protocollen</h2>
    <?php if (!$protocollen): ?>
        <p style="color:var(--muted);">Nog geen protocollen aangemaakt.</p>
    <?php endif; ?>
    <?php foreach ($protocollen as $p): ?>
        <div class="protocol-card">
            <div class="row-top">
                <h3><?= e($p['titel']) ?></h3>
                <div style="display:flex; gap:8px;">
                    <a href="/admin/protocollen.php?bewerk=<?= $p['id'] ?>" class="btn btn-small">Bewerken</a>
                    <form method="post" onsubmit="return confirm('Protocol \'<?= e($p['titel']) ?>\' verwijderen? Dit ontkoppelt het ook van alle meldingen.');">
                        <input type="hidden" name="actie" value="verwijderen">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-small btn-danger">Verwijderen</button>
                    </form>
                </div>
            </div>
            <p><?= e($p['inhoud']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
