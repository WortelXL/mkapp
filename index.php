<?php
require_once __DIR__ . '/includes/functions.php';
vereis_volledige_toegang();
$pdo = get_pdo();

// ---- Filters ---------------------------------------------------------
$f_status     = $_GET['status'] ?? '';
$f_hoofd      = $_GET['hoofd'] ?? '';
$f_sub        = $_GET['sub'] ?? '';
$f_prioriteit = $_GET['prioriteit'] ?? '';
$f_zoek       = trim($_GET['q'] ?? '');
$f_sort       = $_GET['sort'] ?? 'prioriteit';
if (!in_array($f_sort, ['prioriteit', 'nieuwste'], true)) {
    $f_sort = 'prioriteit';
}

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

$actieve_statussen = get_actieve_statussen($pdo);
$actieve_sleutels = statussen_sleutels($actieve_statussen);

// Dashboard toont alleen actieve meldingen (afgehandeld/geannuleerd staan
// vanaf nu in het archief, zie /archief.php) — status-filter is dus beperkt
// tot de statussen met categorie "actief".
if ($f_status !== '' && in_array($f_status, $actieve_sleutels, true)) {
    $where  = ['m.status = :status'];
    $params = ['status' => $f_status];
} else {
    $f_status = '';
    $where  = [];
    $params = [];
    $status_placeholders = [];
    foreach ($actieve_sleutels as $i => $sleutel) {
        $status_placeholders[] = ':actief_status' . $i;
        $params['actief_status' . $i] = $sleutel;
    }
    $where[] = $status_placeholders
        ? 'm.status IN (' . implode(',', $status_placeholders) . ')'
        : '1 = 0';
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
               g.naam AS aangemaakt_door_naam,
               EXISTS(SELECT 1 FROM melding_koppelingen k WHERE k.melding_id = m.id OR k.gekoppelde_melding_id = m.id) AS heeft_koppeling
        FROM meldingen m
        LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
        LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
        LEFT JOIN gebruikers g ON g.id = m.aangemaakt_door_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= $f_sort === 'nieuwste'
    ? ' ORDER BY m.aangemaakt_op DESC'
    : ' ORDER BY FIELD(m.prioriteit,"kritiek","hoog","normaal","laag"), m.aangemaakt_op DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meldingen = $stmt->fetchAll();

// Labels per melding ophalen voor weergave op elke rij
$labels_per_melding = [];
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
}

// ---- Statusbord (tellingen) -------------------------------------------
$tellingen = $pdo->query(
    "SELECT status, COUNT(*) AS aantal FROM meldingen GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$afgeronde_statussen = get_afgeronde_statussen($pdo);
$totaal_afgerond = 0;
foreach ($afgeronde_statussen as $s) {
    $totaal_afgerond += (int) ($tellingen[$s['sleutel']] ?? 0);
}

$kritiek_placeholders = [];
$kritiek_params = [];
foreach ($actieve_sleutels as $i => $sleutel) {
    $kritiek_placeholders[] = ':ks' . $i;
    $kritiek_params['ks' . $i] = $sleutel;
}
if ($kritiek_placeholders) {
    $kritiek_stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM meldingen WHERE prioriteit='kritiek' AND status IN (" . implode(',', $kritiek_placeholders) . ')'
    );
    $kritiek_stmt->execute($kritiek_params);
    $kritiek_open = (int) $kritiek_stmt->fetchColumn();
} else {
    $kritiek_open = 0;
}

$hoofdclassificaties = get_hoofdclassificaties($pdo);
$alle_statussen = get_statussen($pdo);

// Hoogste ID onder de actieve meldingen, ongeacht filters -- gebruikt om
// clientside te bepalen of er een nieuwe melding is bijgekomen (voor het
// geluidssignaal), ook als de huidige filter die melding niet toont.
if ($actieve_sleutels) {
    $hoogste_placeholders = [];
    $hoogste_params = [];
    foreach ($actieve_sleutels as $i => $sleutel) {
        $hoogste_placeholders[] = ':hs' . $i;
        $hoogste_params['hs' . $i] = $sleutel;
    }
    $hoogste_stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(id), 0) FROM meldingen WHERE status IN (' . implode(',', $hoogste_placeholders) . ')'
    );
    $hoogste_stmt->execute($hoogste_params);
    $globale_hoogste_id = (int) $hoogste_stmt->fetchColumn();

    $attentie_placeholders = [];
    $attentie_params = [];
    foreach ($actieve_sleutels as $i => $sleutel) {
        $attentie_placeholders[] = ':ats' . $i;
        $attentie_params['ats' . $i] = $sleutel;
    }
    $attentie_stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(id), 0) FROM meldingen WHERE attentie = 1 AND status IN (' . implode(',', $attentie_placeholders) . ')'
    );
    $attentie_stmt->execute($attentie_params);
    $hoogste_attentie_id = (int) $attentie_stmt->fetchColumn();
} else {
    $globale_hoogste_id = 0;
    $hoogste_attentie_id = 0;
}

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

<div class="overzicht-top">
    <div class="stats-lijst">
        <div class="stats-lijst-item <?= $kritiek_open > 0 ? 'pulse' : '' ?>">
            <span class="num c-red"><?= $kritiek_open ?></span>
            <span class="lbl">Kritiek &amp; open</span>
        </div>
        <?php foreach ($actieve_statussen as $s): ?>
            <div class="stats-lijst-item">
                <span class="num" style="color:<?= e($s['kleur']) ?>;"><?= (int) ($tellingen[$s['sleutel']] ?? 0) ?></span>
                <span class="lbl"><?= e($s['naam']) ?></span>
            </div>
        <?php endforeach; ?>
        <a href="/archief.php" class="stats-lijst-item stats-lijst-item-link">
            <span class="num c-green"><?= $totaal_afgerond ?></span>
            <span class="lbl">Afgerond &rarr; archief</span>
        </a>
    </div>

    <div>
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

        <form class="filters filters-verticaal" method="get">
            <input type="text" name="q" placeholder="Zoek, of typ -classificatienaam..." value="<?= e($f_zoek) ?>">
            <select name="status">
                <option value="">Alle actieve statussen</option>
                <?php foreach ($actieve_statussen as $s): ?>
                    <option value="<?= e($s['sleutel']) ?>" <?= $f_status === $s['sleutel'] ? 'selected' : '' ?>>Alleen <?= lcfirst(e($s['naam'])) ?></option>
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
            <select name="sort">
                <option value="prioriteit" <?= $f_sort === 'prioriteit' ? 'selected' : '' ?>>Sorteer op prioriteit</option>
                <option value="nieuwste" <?= $f_sort === 'nieuwste' ? 'selected' : '' ?>>Nieuwste bovenaan</option>
            </select>
            <?php if ($f_sub): ?>
                <input type="hidden" name="sub" value="<?= (int) $f_sub ?>">
            <?php endif; ?>
            <div class="filters-verticaal-acties">
                <button type="submit" class="btn btn-small">Filteren</button>
                <?php if ($f_status || $f_hoofd || $f_sub || $f_prioriteit || $f_zoek || $f_sort !== 'prioriteit'): ?>
                    <a href="/index.php" class="btn btn-small">Wissen</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="melding-list">
    <?php if (!$meldingen): ?>
        <div class="empty">Geen meldingen gevonden. Pas de filters aan of maak een nieuwe melding aan.</div>
    <?php endif; ?>

    <?php foreach ($meldingen as $m): ?>
        <div class="melding-row prio-<?= e($m['prioriteit']) ?>" onclick="if (!event.target.closest('.status-form')) { window.location = '/melding.php?id=<?= (int) $m['id'] ?>'; }">
            <span class="melding-id"><?= $m['attentie'] ? '⚠️ ' : '' ?><?= $m['heeft_koppeling'] ? '🔗 ' : '' ?><?= e($m['meld_id']) ?></span>
            <span class="melding-main">
                <span class="titel"><?= e($m['titel']) ?></span>
                <span class="meta">
                    <?= e($m['locatie'] ?: 'Geen locatie opgegeven') ?>
                    · <?= (new DateTime($m['aangemaakt_op']))->format('d-m H:i') ?>
                    · ingevoerd door <?= e($m['aangemaakt_door_naam'] ?: 'onbekend') ?>
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
            <form method="post" action="/snel_status.php" class="status-form" title="Klik om de status te wijzigen">
                <input type="hidden" name="melding_id" value="<?= (int) $m['id'] ?>">
                <input type="hidden" name="terug" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                <select name="status" class="tag" style="background:<?= status_kleur($m['status']) ?>22; color:<?= status_kleur($m['status']) ?>;" onchange="this.form.submit()" onclick="event.stopPropagation()">
                    <?php foreach ($alle_statussen as $s): ?>
                        <option value="<?= e($s['sleutel']) ?>" <?= $m['status'] === $s['sleutel'] ? 'selected' : '' ?>><?= e($s['naam']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
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
    const hoogste_attentie_id = <?= $hoogste_attentie_id ?>;
    const OPSLAG_SLEUTEL = 'meldkamer_laatste_gezien_id';
    const OPSLAG_SLEUTEL_ATTENTIE = 'meldkamer_laatste_gezien_attentie_id';

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

    // Attentiesignaal: één toon, twee keer (belletje) -- bewust anders dan
    // het "nieuwe melding"-geluid hierboven, zodat je meteen het verschil
    // hoort.
    function speel_attentiegeluid() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.35].forEach(function (vertraging) {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 1046; // C6, klinkt als een belletje
                gain.gain.setValueAtTime(0.001, ctx.currentTime + vertraging);
                gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + vertraging + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + vertraging + 0.5);
                osc.connect(gain).connect(ctx.destination);
                osc.start(ctx.currentTime + vertraging);
                osc.stop(ctx.currentTime + vertraging + 0.55);
            });
        } catch (e) {
            // Geluid kan geblokkeerd zijn door de browser, negeer dan stil.
        }
    }

    if (geluid_aan) {
        const opgeslagen = localStorage.getItem(OPSLAG_SLEUTEL);
        if (opgeslagen !== null && globale_hoogste_id > parseInt(opgeslagen, 10)) {
            speel_meldingsgeluid();
        }

        const opgeslagen_attentie = localStorage.getItem(OPSLAG_SLEUTEL_ATTENTIE);
        if (opgeslagen_attentie !== null && hoogste_attentie_id > parseInt(opgeslagen_attentie, 10)) {
            speel_attentiegeluid();
        }
    }
    localStorage.setItem(OPSLAG_SLEUTEL, String(globale_hoogste_id));
    localStorage.setItem(OPSLAG_SLEUTEL_ATTENTIE, String(hoogste_attentie_id));

    if (ververs_seconden > 0) {
        setInterval(function () {
            if (!zoekveld_actief && document.visibilityState === 'visible') {
                sessionStorage.setItem('meldkamer_scroll_' + location.pathname, window.scrollY);
                window.location.reload();
            }
        }, ververs_seconden * 1000);
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
