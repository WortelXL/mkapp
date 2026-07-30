<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$fout = '';
$succes = '';

function aantal_actieve_beheerders(PDO $pdo): int
{
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM gebruikers WHERE rol = 'beheerder' AND actief = 1"
    )->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'aanmaken') {
        $naam           = trim($_POST['naam'] ?? '');
        $gebruikersnaam = trim($_POST['gebruikersnaam'] ?? '');
        $wachtwoord     = $_POST['wachtwoord'] ?? '';
        $rol            = $_POST['rol'] ?? 'medewerker';

        if ($naam === '' || $gebruikersnaam === '' || $wachtwoord === '') {
            $fout = 'Vul naam, gebruikersnaam en wachtwoord in.';
        } elseif (strlen($wachtwoord) < 8) {
            $fout = 'Gebruik een wachtwoord van minimaal 8 tekens.';
        } elseif (!in_array($rol, ['beheerder', 'medewerker'], true)) {
            $fout = 'Ongeldige rol.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO gebruikers (gebruikersnaam, wachtwoord_hash, naam, rol) VALUES (:u, :p, :n, :r)'
                );
                $stmt->execute([
                    'u' => $gebruikersnaam,
                    'p' => password_hash($wachtwoord, PASSWORD_DEFAULT),
                    'n' => $naam,
                    'r' => $rol,
                ]);
                $succes = 'Gebruiker "' . $naam . '" is aangemaakt.';
            } catch (PDOException $e) {
                $fout = (int) $e->getCode() === 23000
                    ? 'Deze gebruikersnaam is al in gebruik.'
                    : 'Er ging iets mis bij het aanmaken van de gebruiker.';
            }
        }
    }

    if ($actie === 'rol_wijzigen') {
        $id  = (int) ($_POST['id'] ?? 0);
        $rol = $_POST['rol'] ?? 'medewerker';
        if (in_array($rol, ['beheerder', 'medewerker'], true)) {
            $huidige = $pdo->prepare('SELECT rol, actief FROM gebruikers WHERE id = :id');
            $huidige->execute(['id' => $id]);
            $rij = $huidige->fetch();
            if ($rij && $rij['rol'] === 'beheerder' && $rol !== 'beheerder' && $rij['actief']
                && aantal_actieve_beheerders($pdo) <= 1) {
                $fout = 'Dit is de laatste actieve beheerder; wijzig eerst een andere gebruiker naar beheerder.';
            } else {
                $stmt = $pdo->prepare('UPDATE gebruikers SET rol = :r WHERE id = :id');
                $stmt->execute(['r' => $rol, 'id' => $id]);
                $succes = 'Rol bijgewerkt.';
            }
        }
    }

    if ($actie === 'actief_wisselen') {
        $id = (int) ($_POST['id'] ?? 0);
        $rij_stmt = $pdo->prepare('SELECT rol, actief FROM gebruikers WHERE id = :id');
        $rij_stmt->execute(['id' => $id]);
        $rij = $rij_stmt->fetch();
        if ($rij) {
            if ($rij['rol'] === 'beheerder' && $rij['actief'] && aantal_actieve_beheerders($pdo) <= 1) {
                $fout = 'Je kunt de laatste actieve beheerder niet deactiveren.';
            } else {
                $stmt = $pdo->prepare('UPDATE gebruikers SET actief = NOT actief WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $succes = 'Status bijgewerkt.';
            }
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $rij_stmt = $pdo->prepare('SELECT rol, actief FROM gebruikers WHERE id = :id');
        $rij_stmt->execute(['id' => $id]);
        $rij = $rij_stmt->fetch();
        if ($id === (int) $_SESSION['gebruiker_id']) {
            $fout = 'Je kunt je eigen account niet verwijderen.';
        } elseif ($rij && $rij['rol'] === 'beheerder' && $rij['actief'] && aantal_actieve_beheerders($pdo) <= 1) {
            $fout = 'Je kunt de laatste actieve beheerder niet verwijderen.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM gebruikers WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $succes = 'Gebruiker verwijderd.';
        }
    }
}

$gebruikers = $pdo->query('SELECT * FROM gebruikers ORDER BY actief DESC, naam ASC')->fetchAll();

$actief = 'admin';
$paginatitel = 'Gebruikers beheren';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Gebruikers</h1>
        <p>Beheerders hebben toegang tot categorieen, protocollen en gebruikersbeheer. Medewerkers kunnen meldingen bekijken, aanmaken en bijwerken.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2>Nieuwe gebruiker</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="aanmaken">
        <div class="field">
            <label for="naam">Volledige naam</label>
            <input type="text" id="naam" name="naam" required placeholder="bv. Sanne de Vries">
        </div>
        <div class="field">
            <label for="gebruikersnaam">Gebruikersnaam</label>
            <input type="text" id="gebruikersnaam" name="gebruikersnaam" required placeholder="bv. sanne">
        </div>
        <div class="field">
            <label for="wachtwoord">Tijdelijk wachtwoord</label>
            <input type="password" id="wachtwoord" name="wachtwoord" required minlength="8">
        </div>
        <div class="field">
            <label for="rol">Rol</label>
            <select id="rol" name="rol">
                <option value="medewerker">Medewerker</option>
                <option value="beheerder">Beheerder</option>
            </select>
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary">Gebruiker aanmaken</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Bestaande gebruikers</h2>
    <table class="admin-table">
        <thead>
            <tr><th>Naam</th><th>Gebruikersnaam</th><th>Rol</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($gebruikers as $g): ?>
            <tr>
                <td><?= e($g['naam']) ?><?= (int) $g['id'] === (int) $_SESSION['gebruiker_id'] ? ' <span style="color:var(--muted);">(jij)</span>' : '' ?></td>
                <td style="color:var(--muted); font-family: var(--font-mono);"><?= e($g['gebruikersnaam']) ?></td>
                <td>
                    <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
                        <input type="hidden" name="actie" value="rol_wijzigen">
                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                        <select name="rol" onchange="this.form.submit()" style="padding:5px 8px; font-size:12.5px;">
                            <option value="medewerker" <?= $g['rol'] === 'medewerker' ? 'selected' : '' ?>>Medewerker</option>
                            <option value="beheerder" <?= $g['rol'] === 'beheerder' ? 'selected' : '' ?>>Beheerder</option>
                        </select>
                    </form>
                </td>
                <td>
                    <span class="tag <?= $g['actief'] ? 'status-afgehandeld' : 'status-geannuleerd' ?>">
                        <span class="tag-dot"></span><?= $g['actief'] ? 'Actief' : 'Gedeactiveerd' ?>
                    </span>
                </td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="actie" value="actief_wisselen">
                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                        <button type="submit" class="btn btn-small"><?= $g['actief'] ? 'Deactiveren' : 'Activeren' ?></button>
                    </form>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Gebruiker \'<?= e($g['naam']) ?>\' definitief verwijderen?');">
                        <input type="hidden" name="actie" value="verwijderen">
                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                        <button type="submit" class="btn btn-small btn-danger">Verwijderen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
