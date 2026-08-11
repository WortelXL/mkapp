<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$fout = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'hoofd_aanmaken') {
        $naam         = trim($_POST['naam'] ?? '');
        $kleur        = trim($_POST['kleur'] ?? '#f5a524');
        $beschrijving = trim($_POST['beschrijving'] ?? '');

        if ($naam === '') {
            $fout = 'Vul een naam voor de hoofdclassificatie in.';
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $kleur)) {
            $fout = 'Kies een geldige kleur.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO hoofdclassificaties (naam, kleur, beschrijving) VALUES (:n, :k, :b)'
            );
            $stmt->execute(['n' => $naam, 'k' => $kleur, 'b' => $beschrijving ?: null]);
            $succes = 'Hoofdclassificatie "' . $naam . '" is aangemaakt.';
        }
    }

    if ($actie === 'hoofd_verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM hoofdclassificaties WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Hoofdclassificatie (en de bijbehorende subclassificaties) verwijderd.';
    }

    if ($actie === 'sub_aanmaken') {
        $hoofd_id     = (int) ($_POST['hoofdclassificatie_id'] ?? 0);
        $naam         = trim($_POST['naam'] ?? '');
        $beschrijving = trim($_POST['beschrijving'] ?? '');
        $prioriteit   = $_POST['standaard_prioriteit'] ?? '';
        $prioriteit   = in_array($prioriteit, ['laag','normaal','hoog','kritiek'], true) ? $prioriteit : null;

        if ($hoofd_id <= 0 || $naam === '') {
            $fout = 'Kies een hoofdclassificatie en vul een naam voor de subclassificatie in.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO subclassificaties (hoofdclassificatie_id, naam, beschrijving, standaard_prioriteit) VALUES (:h, :n, :b, :p)'
            );
            $stmt->execute(['h' => $hoofd_id, 'n' => $naam, 'b' => $beschrijving ?: null, 'p' => $prioriteit]);
            $succes = 'Subclassificatie "' . $naam . '" is aangemaakt.';
        }
    }

    if ($actie === 'sub_verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM subclassificaties WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Subclassificatie verwijderd.';
    }

    if ($actie === 'sub_prioriteit_wijzigen') {
        $id = (int) ($_POST['id'] ?? 0);
        $prioriteit = $_POST['standaard_prioriteit'] ?? '';
        $prioriteit = in_array($prioriteit, ['laag','normaal','hoog','kritiek'], true) ? $prioriteit : null;
        $stmt = $pdo->prepare('UPDATE subclassificaties SET standaard_prioriteit = :p WHERE id = :id');
        $stmt->execute(['p' => $prioriteit, 'id' => $id]);
        $succes = 'Standaardprioriteit bijgewerkt.';
    }
}

$hoofdclassificaties = get_hoofdclassificaties($pdo);
$subs_per_hoofd = get_subclassificaties_gegroepeerd($pdo);

$actief = 'admin';
$paginatitel = 'Classificaties beheren';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Classificaties</h1>
        <p>Meldingen worden geclassificeerd met een hoofdclassificatie en optioneel een subclassificatie daaronder.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2>Nieuwe hoofdclassificatie</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="hoofd_aanmaken">
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
            <button type="submit" class="btn btn-primary">Hoofdclassificatie aanmaken</button>
        </div>
    </form>
</div>

<?php if (!$hoofdclassificaties): ?>
    <div class="panel">
        <p style="color:var(--muted); margin:0;">Nog geen hoofdclassificaties aangemaakt. Maak er hierboven eerst een aan.</p>
    </div>
<?php endif; ?>

<?php foreach ($hoofdclassificaties as $h): ?>
    <div class="panel classificatie-panel">
        <div class="row-top" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h2 style="margin:0; display:flex; align-items:center;">
                <span class="color-dot" style="background:<?= e($h['kleur']) ?>;"></span><?= e($h['naam']) ?>
            </h2>
            <div style="display:flex; gap:12px; align-items:center;">
                <label for="hoofd-toggle-<?= $h['id'] ?>" class="log-toggle-wrap" title="Details in-/uitklappen">
                    <span class="log-toggle-switch"></span> Details
                </label>
                <form method="post" onsubmit="return confirm('Hoofdclassificatie \'<?= e($h['naam']) ?>\' verwijderen? Alle subclassificaties eronder worden ook verwijderd. Meldingen met deze classificatie verliezen 'm, maar worden niet verwijderd.');">
                    <input type="hidden" name="actie" value="hoofd_verwijderen">
                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                    <button type="submit" class="btn btn-small btn-danger">Hoofdclassificatie verwijderen</button>
                </form>
            </div>
        </div>
        <input type="checkbox" id="hoofd-toggle-<?= $h['id'] ?>" class="log-toggle-checkbox">
        <div class="row-log">
        <?php if ($h['beschrijving']): ?>
            <p style="color:var(--muted); margin-top:-8px;"><?= e($h['beschrijving']) ?></p>
        <?php endif; ?>

        <?php $subs = $subs_per_hoofd[$h['id']] ?? []; ?>
        <?php if ($subs): ?>
            <table class="admin-table" style="margin-bottom:14px;">
                <thead><tr><th>Subclassificatie</th><th>Beschrijving</th><th>Standaardprioriteit</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($subs as $s): ?>
                    <tr>
                        <td><?= e($s['naam']) ?></td>
                        <td style="color:var(--muted);"><?= e($s['beschrijving'] ?: '—') ?></td>
                        <td>
                            <form method="post" style="display:inline-flex; align-items:center;">
                                <input type="hidden" name="actie" value="sub_prioriteit_wijzigen">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <select name="standaard_prioriteit" onchange="this.form.submit()" style="padding:5px 8px; font-size:12.5px;">
                                    <option value="">Geen standaard</option>
                                    <?php foreach (['laag','normaal','hoog','kritiek'] as $p): ?>
                                        <option value="<?= $p ?>" <?= $s['standaard_prioriteit'] === $p ? 'selected' : '' ?>><?= prioriteit_label($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <form method="post" onsubmit="return confirm('Subclassificatie \'<?= e($s['naam']) ?>\' verwijderen?');">
                                <input type="hidden" name="actie" value="sub_verwijderen">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger">Verwijderen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:var(--muted); font-size:13px;">Nog geen subclassificaties onder <?= e($h['naam']) ?>.</p>
        <?php endif; ?>

        <form method="post" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="actie" value="sub_aanmaken">
            <input type="hidden" name="hoofdclassificatie_id" value="<?= $h['id'] ?>">
            <div class="field" style="margin-bottom:0; flex:1; min-width:160px;">
                <label>Nieuwe subclassificatie</label>
                <input type="text" name="naam" placeholder="bv. Reanimatie" required>
            </div>
            <div class="field" style="margin-bottom:0; flex:1; min-width:160px;">
                <label>Beschrijving (optioneel)</label>
                <input type="text" name="beschrijving" placeholder="Korte toelichting">
            </div>
            <div class="field" style="margin-bottom:0; min-width:150px;">
                <label>Standaardprioriteit</label>
                <select name="standaard_prioriteit">
                    <option value="">Geen standaard</option>
                    <?php foreach (['laag','normaal','hoog','kritiek'] as $p): ?>
                        <option value="<?= $p ?>"><?= prioriteit_label($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Toevoegen</button>
        </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
