<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

// Alleen actieve meldingen (open + in behandeling), net als het dashboard.
// Geen filters/zoekbalk hier bewust: dit is een passief overzichtsscherm,
// geen werkscherm -- en er wordt vanuit hier niet doorgeklikt naar een
// melding. Wel is het logboek per melding direct in te klappen.
$sql = "SELECT m.*,
               h.naam AS hoofd_naam, h.kleur AS hoofd_kleur,
               s.naam AS sub_naam,
               g.naam AS aangemaakt_door_naam
        FROM meldingen m
        LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
        LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
        LEFT JOIN gebruikers g ON g.id = m.aangemaakt_door_id
        WHERE m.status IN ('open', 'in_behandeling')
        ORDER BY FIELD(m.prioriteit,'kritiek','hoog','normaal','laag'), m.aangemaakt_op DESC";
$meldingen = $pdo->query($sql)->fetchAll();

// Labels + logboek (omschrijving + notities) per melding in 1 keer ophalen
$labels_per_melding = [];
$notities_per_melding = [];
if ($meldingen) {
    $ids = array_column($meldingen, 'id');
    $plekhouders = implode(',', array_fill(0, count($ids), '?'));

    $labels_stmt = $pdo->prepare(
        "SELECT ml.melding_id, l.* FROM melding_labels ml
         JOIN labels l ON l.id = ml.label_id
         WHERE ml.melding_id IN ($plekhouders)
         ORDER BY l.naam ASC"
    );
    $labels_stmt->execute($ids);
    foreach ($labels_stmt->fetchAll() as $rij) {
        $labels_per_melding[$rij['melding_id']][] = $rij;
    }

    $notities_stmt = $pdo->prepare(
        "SELECT * FROM melding_notities WHERE melding_id IN ($plekhouders) ORDER BY aangemaakt_op DESC"
    );
    $notities_stmt->execute($ids);
    foreach ($notities_stmt->fetchAll() as $rij) {
        $notities_per_melding[$rij['melding_id']][] = $rij;
    }
}

// ---- Statusbord (tellingen), zelfde als dashboard ----------------------
$tellingen = $pdo->query(
    "SELECT status, COUNT(*) AS aantal FROM meldingen GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$open           = $tellingen['open'] ?? 0;
$in_behandeling = $tellingen['in_behandeling'] ?? 0;
$afgehandeld    = $tellingen['afgehandeld'] ?? 0;
$kritiek_open   = (int) $pdo->query(
    "SELECT COUNT(*) FROM meldingen WHERE prioriteit='kritiek' AND status NOT IN ('afgehandeld','geannuleerd')"
)->fetchColumn();

$mijn_instellingen = huidige_gebruiker_instellingen($pdo);

$actief = 'overview';
$paginatitel = 'Overview';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Dag <?= bepaal_evenement_dag($pdo) ?> van <?= event_aantal_dagen($pdo) ?></p>
        <h1>Overview</h1>
        <p>Passief overzicht van alle actieve meldingen. Klik op het schakelaartje bij een melding om het logboek in te zien — meldingen zelf openen kan hier niet.</p>
    </div>
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

<div class="melding-list">
    <?php if (!$meldingen): ?>
        <div class="empty">Geen actieve meldingen.</div>
    <?php endif; ?>

    <?php foreach ($meldingen as $m): ?>
        <?php
            $toggle_id = 'log-toggle-' . (int) $m['id'];
            $notities = $notities_per_melding[$m['id']] ?? [];
        ?>
        <div class="melding-row prio-<?= e($m['prioriteit']) ?>" style="cursor:default;">
            <span class="melding-id"><?= e($m['meld_id']) ?></span>
            <span class="melding-main">
                <span class="titel"><?= e($m['titel']) ?></span>
                <span class="meta">
                    <?= e($m['locatie'] ?: 'Geen locatie opgegeven') ?>
                    · <?= (new DateTime($m['aangemaakt_op']))->format('d-m H:i') ?>
                    · ingevoerd door <?= e($m['aangemaakt_door_naam'] ?: 'onbekend') ?>
                    <label for="<?= $toggle_id ?>" class="log-toggle-wrap" title="Logboek in-/uitklappen">
                        <span class="log-toggle-switch"></span> Logboek
                    </label>
                </span>
                <?php if (!empty($labels_per_melding[$m['id']])): ?>
                    <span class="meta">
                        <?php foreach ($labels_per_melding[$m['id']] as $l): ?>
                            <span class="cat-chip" style="background: <?= e($l['kleur']) ?>22; color: <?= e($l['kleur']) ?>; margin-right:4px;"><?= e($l['naam']) ?></span>
                        <?php endforeach; ?>
                    </span>
                <?php endif; ?>
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

            <input type="checkbox" id="<?= $toggle_id ?>" class="log-toggle-checkbox">
            <div class="row-log">
                <?php if ($m['omschrijving']): ?>
                    <div class="entry">
                        <div class="kop"><?= (new DateTime($m['aangemaakt_op']))->format('d-m-Y H:i') ?> · <?= e($m['aangemaakt_door_naam'] ?: 'onbekend') ?></div>
                        <div><?= nl2br(e($m['omschrijving'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!$notities && !$m['omschrijving']): ?>
                    <div class="leeg">Nog geen logboekregels.</div>
                <?php endif; ?>
                <?php foreach ($notities as $n): ?>
                    <div class="entry">
                        <div class="kop"><?= (new DateTime($n['aangemaakt_op']))->format('d-m-Y H:i') ?> · <?= e($n['auteur'] ?: 'onbekend') ?></div>
                        <div><?= nl2br(e($n['notitie'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Zelfde auto-refresh/geluid-logica als het dashboard, op basis van de
// persoonlijke instellingen (/profiel.php). Let op: opengeklapte logboeken
// klappen dicht bij een ververssing (nieuwe paginalading), dat is een
// bewuste, simpele keuze.
(function () {
    const ververs_seconden = <?= (int) $mijn_instellingen['auto_refresh_seconden'] ?>;
    const geluid_aan = <?= $mijn_instellingen['geluid_nieuwe_melding'] ? 'true' : 'false' ?>;
    const globale_hoogste_id = <?= (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM meldingen WHERE status IN ('open', 'in_behandeling')")->fetchColumn() ?>;
    const OPSLAG_SLEUTEL = 'meldkamer_laatste_gezien_id';

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
        } catch (e) { /* geluid geblokkeerd door browser, negeer stil */ }
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
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, ververs_seconden * 1000);
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
