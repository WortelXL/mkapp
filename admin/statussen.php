<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$fout = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'aanmaken') {
        $naam      = trim($_POST['naam'] ?? '');
        $kleur     = trim($_POST['kleur'] ?? '#6b7280');
        $categorie = $_POST['categorie'] ?? 'actief';

        if ($naam === '') {
            $fout = 'Vul een naam voor de status in.';
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $kleur)) {
            $fout = 'Kies een geldige kleur.';
        } elseif (!in_array($categorie, ['actief', 'afgerond'], true)) {
            $fout = 'Ongeldige categorie.';
        } else {
            // Sleutel afleiden van de naam: kleine letters, spaties -> underscore,
            // alleen a-z/0-9/underscore. Bij botsing een cijfer erachter.
            $basis_sleutel = strtolower(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($naam)), '_'));
            if ($basis_sleutel === '') {
                $basis_sleutel = 'status';
            }
            $sleutel = $basis_sleutel;
            $poging = 1;
            while (true) {
                $check = $pdo->prepare('SELECT 1 FROM statussen WHERE sleutel = :s');
                $check->execute(['s' => $sleutel]);
                if (!$check->fetchColumn()) {
                    break;
                }
                $poging++;
                $sleutel = $basis_sleutel . '_' . $poging;
            }

            $volgorde_stmt = $pdo->query('SELECT COALESCE(MAX(volgorde), 0) + 1 FROM statussen');
            $volgende_volgorde = (int) $volgorde_stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO statussen (sleutel, naam, kleur, categorie, ingebouwd, volgorde) VALUES (:s, :n, :k, :c, 0, :v)'
            );
            $stmt->execute(['s' => $sleutel, 'n' => $naam, 'k' => $kleur, 'c' => $categorie, 'v' => $volgende_volgorde]);
            $succes = 'Status "' . $naam . '" is aangemaakt.';
        }
    }

    if ($actie === 'wijzigen') {
        $id        = (int) ($_POST['id'] ?? 0);
        $naam      = trim($_POST['naam'] ?? '');
        $kleur     = trim($_POST['kleur'] ?? '#6b7280');
        $categorie = $_POST['categorie'] ?? 'actief';

        if ($naam === '') {
            $fout = 'Vul een naam voor de status in.';
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $kleur)) {
            $fout = 'Kies een geldige kleur.';
        } elseif (!in_array($categorie, ['actief', 'afgerond'], true)) {
            $fout = 'Ongeldige categorie.';
        } else {
            $stmt = $pdo->prepare('UPDATE statussen SET naam = :n, kleur = :k, categorie = :c WHERE id = :id');
            $stmt->execute(['n' => $naam, 'k' => $kleur, 'c' => $categorie, 'id' => $id]);
            $succes = 'Status bijgewerkt.';
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $rij_stmt = $pdo->prepare('SELECT sleutel, ingebouwd FROM statussen WHERE id = :id');
        $rij_stmt->execute(['id' => $id]);
        $rij = $rij_stmt->fetch();

        if (!$rij) {
            $fout = 'Status niet gevonden.';
        } elseif ((int) $rij['ingebouwd'] === 1) {
            $fout = 'De 4 ingebouwde statussen kunnen niet verwijderd worden (alleen aangepast).';
        } else {
            $gebruikt_stmt = $pdo->prepare('SELECT COUNT(*) FROM meldingen WHERE status = :s');
            $gebruikt_stmt->execute(['s' => $rij['sleutel']]);
            $aantal_in_gebruik = (int) $gebruikt_stmt->fetchColumn();

            if ($aantal_in_gebruik > 0) {
                $fout = 'Deze status is nog in gebruik bij ' . $aantal_in_gebruik . ' melding(en) en kan niet verwijderd worden. Wijzig eerst die meldingen naar een andere status.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM statussen WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $succes = 'Status verwijderd.';
            }
        }
    }
}

$statussen = get_statussen($pdo);

$actief = 'admin';
$paginatitel = 'Statussen beheren';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Statussen</h1>
        <p>De 4 ingebouwde statussen (open, in behandeling, afgehandeld, geannuleerd) kunnen niet verwijderd worden, maar wel van naam, kleur en categorie wisselen. Eigen statussen kun je vrij toevoegen en verwijderen.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2>Nieuwe status</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="aanmaken">
        <div class="field">
            <label for="naam">Naam</label>
            <input type="text" id="naam" name="naam" required placeholder="bv. Wacht op onderdelen">
        </div>
        <div class="field">
            <label for="kleur">Kleur</label>
            <input type="color" id="kleur" name="kleur" value="#6b7280" style="height:42px; padding:4px;">
        </div>
        <div class="field full">
            <label for="categorie">Categorie</label>
            <select id="categorie" name="categorie">
                <option value="actief">Actief — zichtbaar op dashboard/Overview</option>
                <option value="afgerond">Afgerond — zichtbaar in archief</option>
            </select>
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary">Status aanmaken</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Bestaande statussen</h2>
    <table class="admin-table">
        <thead>
            <tr><th>Status</th><th>Sleutel</th><th>Categorie</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($statussen as $s): ?>
            <tr>
                <td>
                    <form method="post" style="display:flex; gap:8px; align-items:center;">
                        <input type="hidden" name="actie" value="wijzigen">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <input type="color" name="kleur" value="<?= e($s['kleur']) ?>" style="width:36px; height:32px; padding:2px; flex-shrink:0;" onchange="this.form.requestSubmit()">
                        <input type="text" name="naam" value="<?= e($s['naam']) ?>" style="width:180px; padding:6px 8px; font-size:13px;">
                        <input type="hidden" name="categorie" value="<?= e($s['categorie']) ?>">
                        <button type="submit" class="btn btn-small">Opslaan</button>
                    </form>
                </td>
                <td style="font-family:var(--font-mono); color:var(--muted); font-size:12px;"><?= e($s['sleutel']) ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="actie" value="wijzigen">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="naam" value="<?= e($s['naam']) ?>">
                        <input type="hidden" name="kleur" value="<?= e($s['kleur']) ?>">
                        <select name="categorie" onchange="this.form.submit()" style="padding:5px 8px; font-size:12.5px;">
                            <option value="actief" <?= $s['categorie'] === 'actief' ? 'selected' : '' ?>>Actief</option>
                            <option value="afgerond" <?= $s['categorie'] === 'afgerond' ? 'selected' : '' ?>>Afgerond</option>
                        </select>
                    </form>
                </td>
                <td>
                    <?php if ((int) $s['ingebouwd'] === 1): ?>
                        <span style="color:var(--muted); font-size:12px;">Ingebouwd</span>
                    <?php else: ?>
                        <form method="post" onsubmit="return confirm('Status \'<?= e($s['naam']) ?>\' verwijderen? Kan alleen als er geen meldingen meer deze status hebben.');">
                            <input type="hidden" name="actie" value="verwijderen">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-small btn-danger">Verwijderen</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
