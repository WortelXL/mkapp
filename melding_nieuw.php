<?php
require_once __DIR__ . '/includes/functions.php';
vereis_volledige_toegang();
$pdo = get_pdo();

$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $omschrijving          = trim($_POST['omschrijving'] ?? '');
    $hoofdclassificatie_id = $_POST['hoofdclassificatie_id'] !== '' ? (int) $_POST['hoofdclassificatie_id'] : null;
    $subclassificatie_id   = $_POST['subclassificatie_id'] !== '' ? (int) $_POST['subclassificatie_id'] : null;
    $locatie               = trim($_POST['locatie'] ?? '');
    $prioriteit            = $_POST['prioriteit'] ?? 'normaal';
    $gemeld_door           = trim($_POST['gemeld_door'] ?? '');

    // Een subclassificatie zonder de bijbehorende hoofdclassificatie is ongeldig
    if ($subclassificatie_id !== null && $hoofdclassificatie_id === null) {
        $subclassificatie_id = null;
    }

    // ;locatienaam-commando in de omschrijving overschrijft het locatieveld
    // als het een vooraf ingestelde locatie matcht
    $locatie_via_commando = vind_locatie_commando($pdo, $omschrijving);
    if ($locatie_via_commando) {
        $locatie = $locatie_via_commando['naam'];
    }

    if (!in_array($prioriteit, ['laag','normaal','hoog','kritiek'], true)) {
        $fout = 'Ongeldige prioriteit.';
    } else {
        $titel = bereken_melding_titel($pdo, $hoofdclassificatie_id, $subclassificatie_id);
        $meld_id = genereer_meld_id($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO meldingen (meld_id, titel, omschrijving, hoofdclassificatie_id, subclassificatie_id, locatie, prioriteit, gemeld_door, aangemaakt_door_id)
             VALUES (:meld_id, :titel, :omschrijving, :hoofd, :sub, :locatie, :prioriteit, :gemeld_door, :aangemaakt_door_id)'
        );
        $stmt->execute([
            'meld_id'            => $meld_id,
            'titel'              => $titel,
            'omschrijving'       => $omschrijving ?: null,
            'hoofd'              => $hoofdclassificatie_id,
            'sub'                => $subclassificatie_id,
            'locatie'            => $locatie ?: null,
            'prioriteit'         => $prioriteit,
            'gemeld_door'        => $gemeld_door ?: null,
            'aangemaakt_door_id' => $_SESSION['gebruiker_id'],
        ]);
        $nieuwe_melding_id = (int) $pdo->lastInsertId();
        log_status($pdo, $nieuwe_melding_id, 'open', $_SESSION['gebruiker_id']);
        koppel_protocollen_automatisch($pdo, $nieuwe_melding_id, $subclassificatie_id);
        header('Location: /melding.php?id=' . $nieuwe_melding_id . '&aangemaakt=1');
        exit;
    }
}

$hoofdclassificaties = get_hoofdclassificaties($pdo);
$subs_per_hoofd = get_subclassificaties_gegroepeerd($pdo);
$gekozen_hoofd = $_POST['hoofdclassificatie_id'] ?? '';
$gekozen_sub   = $_POST['subclassificatie_id'] ?? '';

$actief = 'nieuw';
$paginatitel = 'Nieuwe melding';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Dag <?= bepaal_evenement_dag($pdo) ?></p>
        <h1>Nieuwe melding</h1>
        <p>Volgend meld-ID: <strong style="font-family: var(--font-mono); color: var(--amber);"><?= e(genereer_meld_id($pdo)) ?></strong></p>
    </div>
</div>

<?php if ($fout): ?>
    <div class="alert alert-error"><?= e($fout) ?></div>
<?php endif; ?>

<div class="panel">
    <form method="post">
        <div class="form-grid">
            <div class="field full">
                <label>Titel (automatisch, op basis van classificatie)</label>
                <div id="titel_voorbeeld" style="padding:10px 12px; background:var(--panel-2); border:1px solid var(--border); border-radius:6px; color:var(--muted); font-size:14px;">Ongeclassificeerde melding</div>
            </div>

            <div class="field full">
                <label for="omschrijving">Omschrijving</label>
                <textarea id="omschrijving" name="omschrijving" placeholder="Wat is er precies aan de hand?"><?= e($_POST['omschrijving'] ?? '') ?></textarea>
                <p style="color:var(--muted); font-size:12px; margin:6px 0 0;">Tip: typ <code>;locatienaam</code> ergens in de tekst (bv. <code>;podium1</code>) om het locatieveld hieronder automatisch in te vullen met een vooraf ingestelde locatie.</p>
            </div>

            <div class="field">
                <label for="locatie">Locatie</label>
                <input type="text" id="locatie" name="locatie" value="<?= e($_POST['locatie'] ?? '') ?>" placeholder="bv. Podium 2, ingang Noord">
            </div>

            <div class="field">
                <label for="gemeld_door">Gemeld door</label>
                <input type="text" id="gemeld_door" name="gemeld_door" value="<?= e($_POST['gemeld_door'] ?? '') ?>" placeholder="Naam / callsign van de melder">
                <p style="color:var(--muted); font-size:12px; margin:6px 0 0;">Wie de melding doorgaf (bezoeker, portofoonverkeer). Wordt automatisch ook vastgelegd onder jouw account (<?= e(huidige_gebruiker_naam()) ?>) als invoerder.</p>
            </div>

            <div class="field">
                <label for="hoofdclassificatie_id">Hoofdclassificatie</label>
                <select id="hoofdclassificatie_id" name="hoofdclassificatie_id">
                    <option value="">Geen hoofdclassificatie</option>
                    <?php foreach ($hoofdclassificaties as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= (string) $gekozen_hoofd === (string) $h['id'] ? 'selected' : '' ?>><?= e($h['naam']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="subclassificatie_id">Subclassificatie</label>
                <select id="subclassificatie_id" name="subclassificatie_id">
                    <option value="">Geen subclassificatie</option>
                </select>
            </div>

            <div class="field">
                <label for="prioriteit">Prioriteit</label>
                <select id="prioriteit" name="prioriteit">
                    <?php foreach (['laag','normaal','hoog','kritiek'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($_POST['prioriteit'] ?? 'normaal') === $p ? 'selected' : '' ?>><?= prioriteit_label($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Melding aanmaken</button>
            <a href="/index.php" class="btn">Annuleren</a>
        </div>
    </form>
</div>

<script>
// Subclassificaties per hoofdclassificatie, aangeleverd door de server
const subclassificaties = <?= json_encode($subs_per_hoofd, JSON_UNESCAPED_UNICODE) ?>;
const gekozenSub = <?= json_encode((string) $gekozen_sub) ?>;

const hoofdSelect = document.getElementById('hoofdclassificatie_id');
const subSelect = document.getElementById('subclassificatie_id');

function vulSubclassificaties() {
    const hoofdId = hoofdSelect.value;
    subSelect.innerHTML = '<option value="">Geen subclassificatie</option>';
    const lijst = subclassificaties[hoofdId] || [];
    lijst.forEach(function (sub) {
        const optie = document.createElement('option');
        optie.value = sub.id;
        optie.textContent = sub.naam;
        optie.dataset.standaardPrioriteit = sub.standaard_prioriteit || '';
        if (String(sub.id) === gekozenSub) {
            optie.selected = true;
        }
        subSelect.appendChild(optie);
    });
    subSelect.disabled = lijst.length === 0;
}

// Stelt de prioriteit automatisch voor op basis van de gekozen
// subclassificatie (blijft gewoon handmatig aanpasbaar door de gebruiker)
const prioriteitSelect = document.getElementById('prioriteit');
subSelect.addEventListener('change', function () {
    const optie = subSelect.options[subSelect.selectedIndex];
    const standaard = optie ? optie.dataset.standaardPrioriteit : '';
    if (standaard) {
        prioriteitSelect.value = standaard;
    }
});

// Toont een live voorbeeld van de titel die wordt opgeslagen: "Hoofd - Sub"
const titelVoorbeeld = document.getElementById('titel_voorbeeld');
function werkTitelVoorbeeldBij() {
    const hoofdTekst = hoofdSelect.value ? hoofdSelect.options[hoofdSelect.selectedIndex].text : '';
    const subTekst = subSelect.value ? subSelect.options[subSelect.selectedIndex].text : '';
    if (!hoofdTekst) {
        titelVoorbeeld.textContent = 'Ongeclassificeerde melding';
    } else if (subTekst) {
        titelVoorbeeld.textContent = hoofdTekst + ' - ' + subTekst;
    } else {
        titelVoorbeeld.textContent = hoofdTekst;
    }
}
subSelect.addEventListener('change', werkTitelVoorbeeldBij);

hoofdSelect.addEventListener('change', function () {
    vulSubclassificaties();
    werkTitelVoorbeeldBij();
});
vulSubclassificaties();
werkTitelVoorbeeldBij();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
