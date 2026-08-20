<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$fout = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'instellingen_opslaan') {
        $naam          = trim($_POST['event_naam'] ?? '');
        $start_datum   = trim($_POST['event_start_datum'] ?? '');
        $aantal_dagen  = (int) ($_POST['event_aantal_dagen'] ?? 0);

        $datum_geldig = (bool) DateTime::createFromFormat('Y-m-d', $start_datum);

        if ($naam === '') {
            $fout = 'Vul een naam voor het evenement in.';
        } elseif (!$datum_geldig) {
            $fout = 'Vul een geldige startdatum in.';
        } elseif ($aantal_dagen < 1 || $aantal_dagen > 30) {
            $fout = 'Vul een aantal dagen tussen 1 en 30 in.';
        } else {
            set_instelling($pdo, 'event_naam', $naam);
            set_instelling($pdo, 'event_start_datum', $start_datum);
            set_instelling($pdo, 'event_aantal_dagen', (string) $aantal_dagen);
            $succes = 'Instellingen opgeslagen.';
        }
    }

    if ($actie === 'archief_legen') {
        $afgeronde_sleutels = statussen_sleutels(get_afgeronde_statussen($pdo));
        if ($afgeronde_sleutels) {
            $plekhouders = implode(',', array_fill(0, count($afgeronde_sleutels), '?'));
            $stmt = $pdo->prepare("DELETE FROM meldingen WHERE status IN ($plekhouders)");
            $stmt->execute($afgeronde_sleutels);
            $aantal_verwijderd = $stmt->rowCount();
        } else {
            $aantal_verwijderd = 0;
        }
        $succes = $aantal_verwijderd > 0
            ? $aantal_verwijderd . ' melding(en) uit het archief verwijderd.'
            : 'Het archief was al leeg, er was niets te verwijderen.';
    }

    if ($actie === 'versie_opslaan') {
        $id            = (int) ($_POST['id'] ?? 0);
        $versienummer  = trim($_POST['versienummer'] ?? '');
        $datum         = trim($_POST['datum'] ?? '');
        $wijzigingen   = trim($_POST['wijzigingen'] ?? '');

        if ($versienummer === '' || $datum === '' || $wijzigingen === '') {
            $fout = 'Vul een versienummer, datum en de wijzigingen in.';
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('UPDATE versies SET versienummer = :v, datum = :d, wijzigingen = :w WHERE id = :id');
            $stmt->execute(['v' => $versienummer, 'd' => $datum, 'w' => $wijzigingen, 'id' => $id]);
            $succes = 'Versie bijgewerkt.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO versies (versienummer, datum, wijzigingen) VALUES (:v, :d, :w)');
            $stmt->execute(['v' => $versienummer, 'd' => $datum, 'w' => $wijzigingen]);
            $succes = 'Versie "' . $versienummer . '" toegevoegd.';
        }
    }

    if ($actie === 'versie_verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM versies WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Versie verwijderd.';
    }
}

$versie_bewerk = null;
if (isset($_GET['versie_bewerk'])) {
    $stmt = $pdo->prepare('SELECT * FROM versies WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['versie_bewerk']]);
    $versie_bewerk = $stmt->fetch() ?: null;
}

$huidige_naam   = $_POST['event_naam'] ?? event_naam($pdo);
$huidige_start  = $_POST['event_start_datum'] ?? event_start_datum($pdo);
$huidige_dagen  = $_POST['event_aantal_dagen'] ?? event_aantal_dagen($pdo);

$actief = 'admin';
$paginatitel = 'Instellingen';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Instellingen</h1>
        <p>Algemene gegevens van het evenement. Bepaalt onder andere de dagnummering in het meld-ID (bv. <code>MK-D2-014</code>).</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2>Evenementgegevens</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="instellingen_opslaan">
        <div class="field full">
            <label for="event_naam">Naam evenement</label>
            <input type="text" id="event_naam" name="event_naam" required value="<?= e($huidige_naam) ?>" placeholder="bv. Zomerfestival 2026">
        </div>
        <div class="field">
            <label for="event_start_datum">Startdatum (dag 1)</label>
            <input type="date" id="event_start_datum" name="event_start_datum" required value="<?= e($huidige_start) ?>">
        </div>
        <div class="field">
            <label for="event_aantal_dagen">Aantal dagen</label>
            <input type="text" id="event_aantal_dagen" name="event_aantal_dagen" required value="<?= e((string) $huidige_dagen) ?>" inputmode="numeric" pattern="[0-9]*">
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary">Instellingen opslaan</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Huidige status</h2>
    <div class="kv-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="kv"><div class="k">Vandaag is</div><div class="v">dag <?= bepaal_evenement_dag($pdo) ?> van <?= event_aantal_dagen($pdo) ?></div></div>
        <div class="kv"><div class="k">Eerste dag</div><div class="v"><?= e((new DateTime(event_start_datum($pdo)))->format('d-m-Y')) ?></div></div>
        <div class="kv"><div class="k">Laatste dag</div><div class="v"><?= e((new DateTime(event_start_datum($pdo)))->modify('+' . (event_aantal_dagen($pdo) - 1) . ' days')->format('d-m-Y')) ?></div></div>
    </div>
    <p style="color:var(--muted); font-size:12.5px; margin: 14px 0 0;">
        Deze instellingen overschrijven de standaardwaarden uit <code>config.php</code> / de omgevingsvariabelen (<code>EVENT_NAAM</code>, <code>EVENT_START_DATUM</code>, <code>EVENT_AANTAL_DAGEN</code>) zodra ze hier zijn opgeslagen.
    </p>
</div>

<?php
$afgeronde_sleutels_tonen = statussen_sleutels(get_afgeronde_statussen($pdo));
$aantal_in_archief = 0;
if ($afgeronde_sleutels_tonen) {
    $plekhouders_tonen = implode(',', array_fill(0, count($afgeronde_sleutels_tonen), '?'));
    $telling_stmt = $pdo->prepare("SELECT COUNT(*) FROM meldingen WHERE status IN ($plekhouders_tonen)");
    $telling_stmt->execute($afgeronde_sleutels_tonen);
    $aantal_in_archief = (int) $telling_stmt->fetchColumn();
}
?>
<div class="panel">
    <h2>Archief beheren</h2>
    <p style="color:var(--muted); margin-top:-8px;">
        Er <?= $aantal_in_archief === 1 ? 'staat' : 'staan' ?> op dit moment
        <strong style="color:var(--text);"><?= $aantal_in_archief ?></strong>
        afgeronde melding<?= $aantal_in_archief === 1 ? '' : 'en' ?> in het archief.
    </p>
    <form method="post" onsubmit="return confirm('Weet je zeker dat je het hele archief wilt legen?\n\nDit verwijdert alle <?= $aantal_in_archief ?> afgeronde melding(en) — inclusief hun logboek, protocollen, subtaken en labels — permanent uit de database.\n\nDit kan niet ongedaan worden gemaakt.');">
        <input type="hidden" name="actie" value="archief_legen">
        <button type="submit" class="btn btn-danger" <?= $aantal_in_archief === 0 ? 'disabled' : '' ?>>Archief leegmaken</button>
    </form>
</div>

<div class="panel">
    <h2>Versiebeheer</h2>
    <p style="color:var(--muted); margin-top:-8px;">Wijzigingenlog dat via het knopje in de footer te bekijken is. Nieuwste versie staat bovenaan.</p>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="versie_opslaan">
        <input type="hidden" name="id" value="<?= $versie_bewerk['id'] ?? 0 ?>">
        <div class="field">
            <label for="versienummer">Versienummer</label>
            <input type="text" id="versienummer" name="versienummer" required value="<?= e($versie_bewerk['versienummer'] ?? '') ?>" placeholder="bv. V1.3.6">
        </div>
        <div class="field">
            <label for="datum">Datum</label>
            <input type="text" id="datum" name="datum" required value="<?= e($versie_bewerk['datum'] ?? '') ?>" placeholder="bv. 13 augustus 2026">
        </div>
        <div class="field full">
            <label for="wijzigingen">Wijzigingen</label>
            <textarea id="wijzigingen" name="wijzigingen" required style="min-height:160px; font-family:var(--font-mono); font-size:13px;" placeholder="## Groepsnaam
- Wijziging 1
- Wijziging 2

## Andere groep
- Nog een wijziging"><?= e($versie_bewerk['wijzigingen'] ?? '') ?></textarea>
            <p style="color:var(--muted); font-size:12px; margin:6px 0 0;">Een regel die begint met <code>## </code> wordt een groepskop, elke andere regel wordt een los punt (een <code>- </code> ervoor mag, maar hoeft niet).</p>
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary"><?= $versie_bewerk ? 'Wijzigingen opslaan' : 'Versie toevoegen' ?></button>
            <?php if ($versie_bewerk): ?>
                <a href="/admin/instellingen.php" class="btn">Annuleren</a>
            <?php endif; ?>
        </div>
    </form>

    <?php $versies_lijst = get_versies($pdo); ?>
    <?php if ($versies_lijst): ?>
        <table class="admin-table" style="margin-top:18px;">
            <thead><tr><th>Versie</th><th>Datum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($versies_lijst as $v): ?>
                <tr>
                    <td style="font-family:var(--font-mono); color:var(--amber);"><?= e($v['versienummer']) ?></td>
                    <td style="color:var(--muted);"><?= e($v['datum']) ?></td>
                    <td style="white-space:nowrap;">
                        <a href="/admin/instellingen.php?versie_bewerk=<?= $v['id'] ?>#versienummer" class="btn btn-small">Bewerken</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Versie \'<?= e($v['versienummer']) ?>\' verwijderen uit het wijzigingenlog?');">
                            <input type="hidden" name="actie" value="versie_verwijderen">
                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
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
