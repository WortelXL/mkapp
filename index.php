<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

// ---- Filters ---------------------------------------------------------
$f_status     = $_GET['status'] ?? '';
$f_hoofd      = $_GET['hoofd'] ?? '';
$f_sub        = $_GET['sub'] ?? '';
$f_prioriteit = $_GET['prioriteit'] ?? '';
$f_zoek       = trim($_GET['q'] ?? '');

// ---- Commando in de zoekbalk: "-medisch" of "-reanimatie" -----------
// Vervangt de zoekterm door een echt hoofd-/subclassificatiefilter en
// ververst de pagina met een schone URL.
$commando_niet_gevonden = null;
if ($f_zoek !== '' && $f_zoek[0] === '-') {
    $commando = trim(substr($f_zoek, 1));
    $gevonden = $commando !== '' ? vind_classificatie_commando($pdo, $commando) : null;

    if ($gevonden) {
        $redirect_params = ['hoofd' => $gevonden['hoofd_id'], 'via' => 'commando'];
        if (!empty($gevonden['sub_id'])) {
            $redirect_params['sub'] = $gevonden['sub_id'];
        }
        if ($f_status !== '') {
            $redirect_params['status'] = $f_status;
        }
        if ($f_prioriteit !== '') {
            $redirect_params['prioriteit'] = $f_prioriteit;
        }
        header('Location: /index.php?' . http_build_query($redirect_params));
        exit;
    }

    // Geen classificatie gevonden voor dit commando: laat het zien, en
    // val terug op een gewone tekstzoekopdracht zonder het streepje.
    $commando_niet_gevonden = $commando;
    $f_zoek = $commando;
}

// Dashboard toont alleen actieve meldingen (afgehandeld/geannuleerd staan
// vanaf nu in het archief, zie /archief.php) — status-filter is dus beperkt
// tot de 2 actieve statussen.
if ($f_status === 'open' || $f_status === 'in_behandeling') {
    $where  = ['m.status = :status'];
    $params = ['status' => $f_status];
} else {
    $f_status = '';
    $where  = ["m.status IN ('open', 'in_behandeling')"];
    $params = [];
}

if ($f_hoofd !== '' && ctype_digit($f_hoofd)) {
    $where[] = 'm.hoofdclassificatie_id = :hoofd';
    $params['hoofd'] = (int) $f_hoofd;
}
if ($f_sub !== '' && ctype_digit($f_sub)) {
    $where[] = 'm.subclassificatie_id = :sub';
    $params['sub'] = (int) $f_sub;
}
if ($f_prioriteit !== '' && in_array($f_prioriteit, ['laag','normaal','hoog','kritiek'], true)) {
    $where[] = 'm.prioriteit = :prioriteit';
    $params['prioriteit'] = $f_prioriteit;
}
if ($f_zoek !== '') {
    $where[] = '(m.titel LIKE :zoek OR m.meld_id LIKE :zoek OR m.locatie LIKE :zoek)';
    $params['zoek'] = '%' . $f_zoek . '%';
}

$sql = "SELECT m.*,
               h.naam AS hoofd_naam, h.kleur AS hoofd_kleur,
               s.naam AS sub_naam,
               g.naam AS aangemaakt_door_naam
        FROM meldingen m
        LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
        LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
        LEFT JOIN gebruikers g ON g.id = m.aangemaakt_door_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY FIELD(m.prioriteit,"kritiek","hoog","normaal","laag"), m.aangemaakt_op DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meldingen = $stmt->fetchAll();

// ---- Statusbord (tellingen) -------------------------------------------
$tellingen = $pdo->query(
    "SELECT status, COUNT(*) AS aantal FROM meldingen GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$open           = $tellingen['open'] ?? 0;
$in_behandeling = $tellingen['in_behandeling'] ?? 0;
$afgehandeld    = $tellingen['afgehandeld'] ?? 0;
$kritiek_open   = (int) $pdo->query(
    "SELECT COUNT(*) FROM meldingen WHERE prioriteit='kritiek' AND status NOT IN ('afgehandeld','geannuleerd')"
)->fetchColumn();

$hoofdclassificaties = get_hoofdclassificaties($pdo);

// Hoogste ID onder de actieve meldingen, ongeacht filters -- gebruikt om
// clientside te bepalen of er een nieuwe melding is bijgekomen (voor het
// geluidssignaal), ook als de huidige filter die melding niet toont.
$globale_hoogste_id = (int) $pdo->query(
    "SELECT COALESCE(MAX(id), 0) FROM meldingen WHERE status IN ('open', 'in_behandeling')"
)->fetchColumn();

$mijn_instellingen = huidige_gebruiker_instellingen($pdo);

$actief = 'dashboard';
$paginatitel = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Dag <?= bepaal_evenement_dag($pdo) ?> van <?= event_aantal_dagen($pdo) ?></p>
        <h1>Overzicht meldingen</h1>
        <p>Actieve meldingen tijdens het evenement. Afgeronde meldingen staan in het <a href="/archief.php" style="color:var(--amber);">archief</a>.</p>
    </div>
    <a href="/melding_nieuw.php" class="btn btn-primary">+ Nieuwe melding</a>
</div>

<div class="board">
    <div class="board-cell <?= $kritiek_open > 0 ? 'pulse' : '' ?>">
        <div class="num c-red"><?= $kritiek_open ?></div>
        <div class="lbl">Kritiek &amp; open</div>
    </div>
    <div class="board-cell">
        <div class="num c-red"><?= $open ?></div>
        <div class="lbl">Open</div>
    </div>
    <div class="board-cell">
        <div class="num c-amber"><?= $in_behandeling ?></div>
        <div class="lbl">In behandeling</div>
    </div>
    <a href="/archief.php" class="board-cell" style="text-decoration:none; color:inherit; display:block;">
        <div class="num c-green"><?= $afgehandeld ?></div>
        <div class="lbl">Afgehandeld &rarr; archief</div>
    </a>
</div>

<?php if (isset($_GET['via']) && $_GET['via'] === 'commando' && $f_hoofd): ?>
    <?php
        $hoofd_naam_geselecteerd = '';
        foreach ($hoofdclassificaties as $h) {
            if ((string) $h['id'] === (string) $f_hoofd) {
                $hoofd_naam_geselecteerd = $h['naam'];
                break;
            }
        }
        $sub_naam_geselecteerd = null;
        if ($f_sub) {
            $sub_stmt = $pdo->prepare('SELECT naam FROM subclassificaties WHERE id = :id');
            $sub_stmt->execute(['id' => $f_sub]);
            $sub_naam_geselecteerd = $sub_stmt->fetchColumn() ?: null;
        }
    ?>
    <div class="alert alert-success">
        Filter toegepast via commando: <strong><?= e($hoofd_naam_geselecteerd) ?></strong><?= $sub_naam_geselecteerd ? ' · ' . e($sub_naam_geselecteerd) : '' ?>
    </div>
<?php endif; ?>

<?php if ($commando_niet_gevonden !== null): ?>
    <div class="alert alert-error">
        Geen classificatie gevonden voor "-<?= e($commando_niet_gevonden) ?>" — in plaats daarvan gezocht op tekst.
    </div>
<?php endif; ?>

<form class="filters" method="get">
    <input type="text" name="q" placeholder="Zoek, of typ -classificatienaam..." value="<?= e($f_zoek) ?>">
    <select name="status">
        <option value="">Open + in behandeling</option>
        <?php foreach (['open','in_behandeling'] as $s): ?>
            <option value="<?= $s ?>" <?= $f_status === $s ? 'selected' : '' ?>>Alleen <?= lcfirst(status_label($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="prioriteit">
        <option value="">Alle prioriteiten</option>
        <?php foreach (['kritiek','hoog','normaal','laag'] as $p): ?>
            <option value="<?= $p ?>" <?= $f_prioriteit === $p ? 'selected' : '' ?>><?= prioriteit_label($p) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="hoofd">
        <option value="">Alle hoofdclassificaties</option>
        <?php foreach ($hoofdclassificaties as $h): ?>
            <option value="<?= $h['id'] ?>" <?= $f_hoofd == $h['id'] ? 'selected' : '' ?>><?= e($h['naam']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($f_sub): ?>
        <input type="hidden" name="sub" value="<?= (int) $f_sub ?>">
    <?php endif; ?>
    <button type="submit" class="btn btn-small">Filteren</button>
    <?php if ($f_status || $f_hoofd || $f_sub || $f_prioriteit || $f_zoek): ?>
        <a href="/index.php" class="btn btn-small">Wissen</a>
    <?php endif; ?>
</form>

<div class="melding-list">
    <?php if (!$meldingen): ?>
        <div class="empty">Geen meldingen gevonden. Pas de filters aan of maak een nieuwe melding aan.</div>
    <?php endif; ?>

    <?php foreach ($meldingen as $m): ?>
        <a href="/melding.php?id=<?= (int) $m['id'] ?>" class="melding-row prio-<?= e($m['prioriteit']) ?>">
            <span class="melding-id"><?= e($m['meld_id']) ?></span>
            <span class="melding-main">
                <span class="titel"><?= e($m['titel']) ?></span>
                <span class="meta">
                    <?= e($m['locatie'] ?: 'Geen locatie opgegeven') ?>
                    · <?= (new DateTime($m['aangemaakt_op']))->format('d-m H:i') ?>
                    · ingevoerd door <?= e($m['aangemaakt_door_naam'] ?: 'onbekend') ?>
                </span>
            </span>
            <?php if ($m['hoofd_naam']): ?>
                <span class="cat-chip" style="background: <?= e($m['hoofd_kleur']) ?>22; color: <?= e($m['hoofd_kleur']) ?>;">
                    <?= e($m['hoofd_naam']) ?><?= $m['sub_naam'] ? ' · ' . e($m['sub_naam']) : '' ?>
                </span>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <span class="tag <?= prioriteit_class($m['prioriteit']) ?>"><?= prioriteit_label($m['prioriteit']) ?></span>
            <span class="tag <?= status_class($m['status']) ?>">
                <span class="tag-dot"></span><?= status_label($m['status']) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<script>
// Ververst het dashboard automatisch zodat nieuwe meldingen (bv. via de
// Stream Deck-koppeling aangemaakt) ook zichtbaar worden bij mensen die
// deze pagina al open hebben staan. Pauzeert zolang iemand aan het typen
// is in het zoekveld, zodat niemand halverwege een zoekopdracht onderbroken
// wordt. Actieve filters blijven behouden, want de URL zelf verandert niet.
// Interval en geluid komen uit de persoonlijke instellingen (/profiel.php).
(function () {
    const ververs_seconden = <?= (int) $mijn_instellingen['auto_refresh_seconden'] ?>;
    const geluid_aan = <?= $mijn_instellingen['geluid_nieuwe_melding'] ? 'true' : 'false' ?>;
    const globale_hoogste_id = <?= $globale_hoogste_id ?>;
    const OPSLAG_SLEUTEL = 'meldkamer_laatste_gezien_id';

    let zoekveld_actief = false;
    const zoekveld = document.querySelector('.filters input[name="q"]');
    if (zoekveld) {
        zoekveld.addEventListener('focus', function () { zoekveld_actief = true; });
        zoekveld.addEventListener('blur', function () { zoekveld_actief = false; });
    }

    // Geluidje bij een nieuwe melding: vergelijkt het hoogste ID met wat we
    // de vorige keer in deze browser hebben gezien (localStorage, dus puur
    // clientside -- geen serveraanroep nodig).
    function speel_meldingsgeluid() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [880, 660].forEach(function (freq, i) {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.2, ctx.currentTime + i * 0.15);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.15 + 0.25);
                osc.connect(gain).connect(ctx.destination);
                osc.start(ctx.currentTime + i * 0.15);
                osc.stop(ctx.currentTime + i * 0.15 + 0.3);
            });
        } catch (e) {
            // Geluid kan geblokkeerd zijn door de browser (autoplay-beleid);
            // negeer dat dan stil, de melding zelf is al zichtbaar.
        }
    }

    if (geluid_aan) {
        const opgeslagen = localStorage.getItem(OPSLAG_SLEUTEL);
        if (opgeslagen !== null && globale_hoogste_id > parseInt(opgeslagen, 10)) {
            speel_meldingsgeluid();
        }
    }
    localStorage.setItem(OPSLAG_SLEUTEL, String(globale_hoogste_id));

    if (ververs_seconden > 0) {
        setInterval(function () {
            if (!zoekveld_actief && document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, ververs_seconden * 1000);
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
