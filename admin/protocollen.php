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
        $subclassificatie_id = $_POST['subclassificatie_id'] !== '' ? (int) $_POST['subclassificatie_id'] : null;
        $inhoud       = trim($_POST['inhoud'] ?? '');

        if ($titel === '' || $inhoud === '') {
            $fout = 'Vul zowel een titel als de inhoud van het protocol in.';
        } elseif ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE protocollen SET titel = :t, subclassificatie_id = :c, inhoud = :i WHERE id = :id'
            );
            $stmt->execute(['t' => $titel, 'c' => $subclassificatie_id, 'i' => $inhoud, 'id' => $id]);
            $succes = 'Protocol bijgewerkt.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO protocollen (titel, subclassificatie_id, inhoud) VALUES (:t, :c, :i)'
            );
            $stmt->execute(['t' => $titel, 'c' => $subclassificatie_id, 'i' => $inhoud]);
            $succes = 'Protocol aangemaakt.';
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM protocollen WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Protocol verwijderd.';
    }

    if ($actie === 'subtaak_aanmaken') {
        $protocol_id  = (int) ($_POST['protocol_id'] ?? 0);
        $omschrijving = trim($_POST['omschrijving'] ?? '');
        if ($protocol_id <= 0 || $omschrijving === '') {
            $fout = 'Vul een omschrijving voor de subtaak in.';
        } else {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(volgorde), 0) + 1 FROM protocol_subtaken WHERE protocol_id = :p');
            $stmt->execute(['p' => $protocol_id]);
            $volgende_volgorde = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO protocol_subtaken (protocol_id, omschrijving, volgorde) VALUES (:p, :o, :v)'
            );
            $stmt->execute(['p' => $protocol_id, 'o' => $omschrijving, 'v' => $volgende_volgorde]);
            $succes = 'Subtaak toegevoegd.';
        }
    }

    if ($actie === 'subtaak_verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM protocol_subtaken WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Subtaak verwijderd.';
    }
}

if (isset($_GET['bewerk'])) {
    $stmt = $pdo->prepare('SELECT * FROM protocollen WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['bewerk']]);
    $bewerk = $stmt->fetch() ?: null;
}

$protocollen = get_protocollen($pdo);
$hoofdclassificaties = get_hoofdclassificaties($pdo);
$subs_per_hoofd = get_subclassificaties_gegroepeerd($pdo);

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
            <label for="subclassificatie_id">Gekoppelde subclassificatie (optioneel)</label>
            <select id="subclassificatie_id" name="subclassificatie_id">
                <option value="">Geen specifieke subclassificatie</option>
                <?php foreach ($hoofdclassificaties as $h): ?>
                    <?php $subs = $subs_per_hoofd[$h['id']] ?? []; ?>
                    <?php if ($subs): ?>
                        <optgroup label="<?= e($h['naam']) ?>">
                            <?php foreach ($subs as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= (($bewerk['subclassificatie_id'] ?? null) == $s['id']) ? 'selected' : '' ?>><?= e($s['naam']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <?php if (!array_filter($subs_per_hoofd)): ?>
                <p style="color:var(--muted); font-size:12px; margin:6px 0 0;">Er zijn nog geen subclassificaties aangemaakt. Ga naar <a href="/admin/classificaties.php" style="color:var(--amber);">Beheer &rarr; Classificaties</a> om er een toe te voegen.</p>
            <?php endif; ?>
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
            <?php if ($p['sub_naam']): ?>
                <p class="cat-chip" style="display:inline-block; background: <?= e($p['hoofd_kleur']) ?>22; color: <?= e($p['hoofd_kleur']) ?>; margin: 0 0 8px;">
                    <?= e($p['hoofd_naam']) ?> &middot; <?= e($p['sub_naam']) ?>
                </p>
            <?php endif; ?>
            <p><?= e($p['inhoud']) ?></p>

            <?php $subtaken = get_subtaken($pdo, $p['id']); ?>
            <div style="margin-top:10px; padding-top:10px; border-top:1px solid var(--border);">
                <p style="font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:0 0 8px;">Subtaken</p>
                <?php if (!$subtaken): ?>
                    <p style="color:var(--muted); font-size:13px; margin:0 0 8px;">Nog geen subtaken voor dit protocol.</p>
                <?php else: ?>
                    <ul style="margin:0 0 10px; padding-left:18px;">
                        <?php foreach ($subtaken as $t): ?>
                            <li style="font-size:13.5px; margin-bottom:4px; display:flex; align-items:center; gap:8px;">
                                <span style="flex:1;"><?= e($t['omschrijving']) ?></span>
                                <form method="post" onsubmit="return confirm('Subtaak verwijderen?');">
                                    <input type="hidden" name="actie" value="subtaak_verwijderen">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-small btn-danger">Verwijderen</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" style="display:flex; gap:8px;">
                    <input type="hidden" name="actie" value="subtaak_aanmaken">
                    <input type="hidden" name="protocol_id" value="<?= $p['id'] ?>">
                    <input type="text" name="omschrijving" placeholder="Nieuwe subtaak, bv. 'AED gehaald'" style="flex:1;" required>
                    <button type="submit" class="btn btn-small">Toevoegen</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
