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
        $functie        = trim($_POST['functie'] ?? '');

        if ($naam === '' || $gebruikersnaam === '' || $wachtwoord === '') {
            $fout = 'Vul naam, gebruikersnaam en wachtwoord in.';
        } elseif (strlen($wachtwoord) < 8) {
            $fout = 'Gebruik een wachtwoord van minimaal 8 tekens.';
        } elseif (!in_array($rol, ['beheerder', 'medewerker', 'view'], true)) {
            $fout = 'Ongeldige rol.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO gebruikers (gebruikersnaam, wachtwoord_hash, naam, rol, functie) VALUES (:u, :p, :n, :r, :f)'
                );
                $stmt->execute([
                    'u' => $gebruikersnaam,
                    'p' => password_hash($wachtwoord, PASSWORD_DEFAULT),
                    'n' => $naam,
                    'r' => $rol,
                    'f' => $functie ?: null,
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
        if (in_array($rol, ['beheerder', 'medewerker', 'view'], true)) {
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

    if ($actie === 'functie_wijzigen') {
        $id      = (int) ($_POST['id'] ?? 0);
        $functie = trim($_POST['functie'] ?? '');
        $stmt = $pdo->prepare('UPDATE gebruikers SET functie = :f WHERE id = :id');
        $stmt->execute(['f' => $functie ?: null, 'id' => $id]);
        $succes = 'Functie bijgewerkt.';
    }

    if ($actie === 'wachtwoord_wijzigen') {
        $id            = (int) ($_POST['id'] ?? 0);
        $nieuw_wachtwoord = $_POST['nieuw_wachtwoord'] ?? '';

        if (strlen($nieuw_wachtwoord) < 8) {
            $fout = 'Gebruik een wachtwoord van minimaal 8 tekens.';
        } else {
            $stmt = $pdo->prepare('UPDATE gebruikers SET wachtwoord_hash = :h WHERE id = :id');
            $stmt->execute(['h' => password_hash($nieuw_wachtwoord, PASSWORD_DEFAULT), 'id' => $id]);
            $succes = 'Wachtwoord bijgewerkt.';
        }
    }

    if ($actie === 'token_genereren') {
        $id = (int) ($_POST['id'] ?? 0);
        $doel_stmt = $pdo->prepare('SELECT rol FROM gebruikers WHERE id = :id');
        $doel_stmt->execute(['id' => $id]);
        if ($doel_stmt->fetchColumn() !== 'beheerder') {
            $fout = 'API-tokens zijn alleen beschikbaar voor accounts met de rol beheerder.';
        } else {
            $stmt = $pdo->prepare('UPDATE gebruikers SET api_token = :t WHERE id = :id');
            $stmt->execute(['t' => genereer_api_token(), 'id' => $id]);
            $succes = 'Nieuw API-token gegenereerd.';
        }
    }

    if ($actie === 'token_instellen') {
        $id     = (int) ($_POST['id'] ?? 0);
        $handmatig_token = trim($_POST['handmatig_token'] ?? '');

        $doel_stmt = $pdo->prepare('SELECT rol FROM gebruikers WHERE id = :id');
        $doel_stmt->execute(['id' => $id]);
        $doel_rol = $doel_stmt->fetchColumn();

        if ($doel_rol !== 'beheerder') {
            $fout = 'API-tokens zijn alleen beschikbaar voor accounts met de rol beheerder.';
        } elseif ($handmatig_token === '' || preg_match('/\s/', $handmatig_token)) {
            $fout = 'Vul een token zonder spaties in.';
        } elseif (strlen($handmatig_token) < 12 || strlen($handmatig_token) > 64) {
            $fout = 'Een token moet tussen 12 en 64 tekens lang zijn.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE gebruikers SET api_token = :t WHERE id = :id');
                $stmt->execute(['t' => $handmatig_token, 'id' => $id]);
                $succes = 'Token ingesteld.';
            } catch (PDOException $e) {
                $fout = (int) $e->getCode() === 23000
                    ? 'Dit token is al bij een ander account in gebruik.'
                    : 'Er ging iets mis bij het instellen van het token.';
            }
        }
    }

    if ($actie === 'token_intrekken') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE gebruikers SET api_token = NULL WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'API-token ingetrokken.';
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
        <p>Beheerders hebben toegang tot classificaties, protocollen en gebruikersbeheer. Medewerkers kunnen meldingen bekijken, aanmaken en bijwerken. Viewers mogen alleen de Overview-pagina bekijken (meekijken, verder nergens bij kunnen).</p>
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
                <option value="view">Viewer (alleen Overview)</option>
            </select>
        </div>
        <div class="field">
            <label for="functie">Functie (optioneel)</label>
            <input type="text" id="functie" name="functie" placeholder="bv. Centralist, Hoofd EHBO">
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
            <tr><th>Naam</th><th>Gebruikersnaam</th><th>Rol</th><th>Functie</th><th>Status</th><th>Wachtwoord</th><th>API-token (Stream Deck e.d.)</th><th></th></tr>
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
                            <option value="view" <?= $g['rol'] === 'view' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                    </form>
                </td>
                <td>
                    <form method="post" style="display:flex; gap:4px;">
                        <input type="hidden" name="actie" value="functie_wijzigen">
                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                        <input type="text" name="functie" value="<?= e($g['functie'] ?? '') ?>" placeholder="bv. Centralist" style="width:120px; font-size:12.5px; padding:5px 7px;">
                        <button type="submit" class="btn btn-small">Opslaan</button>
                    </form>
                </td>
                <td>
                    <span class="tag <?= $g['actief'] ? 'status-afgehandeld' : 'status-geannuleerd' ?>">
                        <span class="tag-dot"></span><?= $g['actief'] ? 'Actief' : 'Gedeactiveerd' ?>
                    </span>
                </td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:flex; gap:4px;" onsubmit="return confirm('Wachtwoord van \'<?= e($g['naam']) ?>\' wijzigen naar het ingevulde wachtwoord?');">
                        <input type="hidden" name="actie" value="wachtwoord_wijzigen">
                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                        <input type="password" name="nieuw_wachtwoord" placeholder="nieuw wachtwoord" minlength="8" style="width:130px; font-size:12px; padding:4px 6px;" required>
                        <button type="submit" class="btn btn-small">Wijzigen</button>
                    </form>
                </td>
                <td style="white-space:nowrap;">
                    <?php if ($g['api_token']): ?>
                        <input type="text" readonly value="<?= e($g['api_token']) ?>" onclick="this.select()" style="width:220px; font-family:var(--font-mono); font-size:11.5px; padding:5px 7px; background:var(--panel-2); border:1px solid var(--border); color:var(--text); border-radius:4px;">
                        <form method="post" style="display:inline;" onsubmit="return confirm('Token intrekken? Knoppen/koppelingen die dit token gebruiken werken dan niet meer.');">
                            <input type="hidden" name="actie" value="token_intrekken">
                            <input type="hidden" name="id" value="<?= $g['id'] ?>">
                            <button type="submit" class="btn btn-small btn-danger">Intrekken</button>
                        </form>
                    <?php elseif ($g['rol'] === 'beheerder'): ?>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="actie" value="token_genereren">
                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                <button type="submit" class="btn btn-small">Token genereren</button>
                            </form>
                            <form method="post" style="display:flex; gap:4px;">
                                <input type="hidden" name="actie" value="token_instellen">
                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                <input type="text" name="handmatig_token" placeholder="of zelf token invullen..." style="width:150px; font-family:var(--font-mono); font-size:11px; padding:4px 6px;">
                                <button type="submit" class="btn btn-small">Instellen</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span style="color:var(--muted); font-size:12px;">— (nieuw token alleen voor beheerders)</span>
                    <?php endif; ?>
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
    <p style="color:var(--muted); font-size:12px; margin:14px 0 0;">
        Een API-token geeft toegang tot het aanmaken van meldingen via <code>/api/melding.php</code> (bv. vanaf een Stream Deck), zonder in te loggen. Behandel een token als een wachtwoord. Een bestaand token blijft altijd zichtbaar en intrekbaar, ongeacht de rol van dat account — maar een <strong>nieuw</strong> token aanmaken (automatisch genereren of zelf invullen) kan alleen bij accounts met de rol beheerder.
    </p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
