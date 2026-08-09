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
        $stmt = $pdo->prepare("DELETE FROM meldingen WHERE status IN ('afgehandeld', 'geannuleerd')");
        $stmt->execute();
        $aantal_verwijderd = $stmt->rowCount();
        $succes = $aantal_verwijderd > 0
            ? $aantal_verwijderd . ' melding(en) uit het archief verwijderd.'
            : 'Het archief was al leeg, er was niets te verwijderen.';
    }
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
$aantal_in_archief = (int) $pdo->query(
    "SELECT COUNT(*) FROM meldingen WHERE status IN ('afgehandeld', 'geannuleerd')"
)->fetchColumn();
?>
<div class="panel">
    <h2>Archief beheren</h2>
    <p style="color:var(--muted); margin-top:-8px;">
        Er <?= $aantal_in_archief === 1 ? 'staat' : 'staan' ?> op dit moment
        <strong style="color:var(--text);"><?= $aantal_in_archief ?></strong>
        afgehandelde/geannuleerde melding<?= $aantal_in_archief === 1 ? '' : 'en' ?> in het archief.
    </p>
    <form method="post" onsubmit="return confirm('Weet je zeker dat je het hele archief wilt legen?\n\nDit verwijdert alle <?= $aantal_in_archief ?> afgehandelde/geannuleerde melding(en) — inclusief hun logboek, protocollen, subtaken en labels — permanent uit de database.\n\nDit kan niet ongedaan worden gemaakt.');">
        <input type="hidden" name="actie" value="archief_legen">
        <button type="submit" class="btn btn-danger" <?= $aantal_in_archief === 0 ? 'disabled' : '' ?>>Archief leegmaken</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
