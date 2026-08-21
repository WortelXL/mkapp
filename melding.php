<?php
require_once __DIR__ . '/includes/functions.php';
vereis_volledige_toegang();
$pdo = get_pdo();

$id = (int) ($_GET['id'] ?? 0);
$melding_stmt = $pdo->prepare(
    'SELECT m.*, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur, s.naam AS sub_naam,
            g1.naam AS aangemaakt_door_naam, g2.naam AS bijgewerkt_door_naam,
            c.naam AS toegewezen_aan_naam, c.functie AS toegewezen_aan_functie, c.telefoonnummer AS toegewezen_aan_telefoon,
            g4.naam AS toegewezen_centralist_naam, g4.functie AS toegewezen_centralist_functie
     FROM meldingen m
     LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
     LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
     LEFT JOIN gebruikers g1 ON g1.id = m.aangemaakt_door_id
     LEFT JOIN gebruikers g2 ON g2.id = m.bijgewerkt_door_id
     LEFT JOIN crew c ON c.id = m.toegewezen_aan_crew_id
     LEFT JOIN gebruikers g4 ON g4.id = m.toegewezen_centralist_id
     WHERE m.id = :id'
);
$melding_stmt->execute(['id' => $id]);
$melding = $melding_stmt->fetch();

if (!$melding) {
    http_response_code(404);
    $actief = 'dashboard';
    $paginatitel = 'Niet gevonden';
    include __DIR__ . '/includes/header.php';
    echo '<div class="empty">Deze melding bestaat niet (meer).</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$melding_uitgevoerd = '';

// ---- Acties (POST) -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'classificatie_bijwerken') {
        $hoofd_id = $_POST['hoofdclassificatie_id'] !== '' ? (int) $_POST['hoofdclassificatie_id'] : null;
        $sub_id   = $_POST['subclassificatie_id'] !== '' ? (int) $_POST['subclassificatie_id'] : null;
        if ($sub_id !== null && $hoofd_id === null) {
            $sub_id = null;
        }
        $titel = bereken_melding_titel($pdo, $hoofd_id, $sub_id);
        $stmt = $pdo->prepare(
            'UPDATE meldingen SET titel = :titel, hoofdclassificatie_id = :h, subclassificatie_id = :s, bijgewerkt_door_id = :gebruiker WHERE id = :id'
        );
        $stmt->execute([
            'titel' => $titel,
            'h' => $hoofd_id,
            's' => $sub_id,
            'gebruiker' => $_SESSION['gebruiker_id'],
            'id' => $id,
        ]);
        koppel_protocollen_automatisch($pdo, $id, $sub_id);
        header('Location: /melding.php?id=' . $id . '&bijgewerkt=1');
        exit;
    }

    if ($actie === 'status_bijwerken') {
        $status      = $_POST['status'] ?? $melding['status'];
        $prioriteit  = $_POST['prioriteit'] ?? $melding['prioriteit'];
        $toegewezen_aan_crew_id = $_POST['toegewezen_aan_crew_id'] !== '' ? (int) $_POST['toegewezen_aan_crew_id'] : null;
        $toegewezen_centralist_id = $_POST['toegewezen_centralist_id'] !== '' ? (int) $_POST['toegewezen_centralist_id'] : null;
        if (is_geldige_status($pdo, $status)
            && in_array($prioriteit, ['laag','normaal','hoog','kritiek'], true)) {
            $stmt = $pdo->prepare(
                'UPDATE meldingen SET status = :status, prioriteit = :prioriteit,
                    toegewezen_aan_crew_id = :toegewezen_aan, toegewezen_centralist_id = :centralist,
                    bijgewerkt_door_id = :gebruiker WHERE id = :id'
            );
            $stmt->execute([
                'status'       => $status,
                'prioriteit'   => $prioriteit,
                'toegewezen_aan' => $toegewezen_aan_crew_id,
                'centralist'   => $toegewezen_centralist_id,
                'gebruiker'    => $_SESSION['gebruiker_id'],
                'id'           => $id,
            ]);
            if ($status !== $melding['status']) {
                log_status($pdo, $id, $status, $_SESSION['gebruiker_id']);
            }
        }
        header('Location: /melding.php?id=' . $id . '&bijgewerkt=1');
        exit;
    }

    if ($actie === 'attentie_wisselen') {
        $nieuw = $melding['attentie'] ? 0 : 1;
        $stmt = $pdo->prepare(
            'UPDATE meldingen SET attentie = :a, attentie_door_id = :g, attentie_op = :t WHERE id = :id'
        );
        $stmt->execute([
            'a' => $nieuw,
            'g' => $nieuw ? $_SESSION['gebruiker_id'] : null,
            't' => $nieuw ? date('Y-m-d H:i:s') : null,
            'id' => $id,
        ]);
        header('Location: /melding.php?id=' . $id);
        exit;
    }

    if ($actie === 'protocol_koppelen') {
        $protocol_id = (int) ($_POST['protocol_id'] ?? 0);
        if ($protocol_id > 0) {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO melding_protocollen (melding_id, protocol_id) VALUES (:m, :p)'
            );
            $stmt->execute(['m' => $id, 'p' => $protocol_id]);
        }
        header('Location: /melding.php?id=' . $id . '#protocollen');
        exit;
    }

    if ($actie === 'protocol_ontkoppelen') {
        $protocol_id = (int) ($_POST['protocol_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM melding_protocollen WHERE melding_id = :m AND protocol_id = :p');
        $stmt->execute(['m' => $id, 'p' => $protocol_id]);
        header('Location: /melding.php?id=' . $id . '#protocollen');
        exit;
    }

    if ($actie === 'subtaak_wisselen') {
        $subtaak_id = (int) ($_POST['subtaak_id'] ?? 0);
        if ($subtaak_id > 0) {
            $huidige_stmt = $pdo->prepare(
                'SELECT afgevinkt FROM melding_subtaak_status WHERE melding_id = :m AND subtaak_id = :s'
            );
            $huidige_stmt->execute(['m' => $id, 's' => $subtaak_id]);
            $huidig = (int) $huidige_stmt->fetchColumn();
            $nieuw = $huidig ? 0 : 1;

            $stmt = $pdo->prepare(
                'INSERT INTO melding_subtaak_status (melding_id, subtaak_id, afgevinkt, afgevinkt_door_id, afgevinkt_op)
                 VALUES (:m, :s, :a, :g, :t)
                 ON DUPLICATE KEY UPDATE afgevinkt = VALUES(afgevinkt), afgevinkt_door_id = VALUES(afgevinkt_door_id), afgevinkt_op = VALUES(afgevinkt_op)'
            );
            $stmt->execute([
                'm' => $id,
                's' => $subtaak_id,
                'a' => $nieuw,
                'g' => $nieuw ? $_SESSION['gebruiker_id'] : null,
                't' => $nieuw ? date('Y-m-d H:i:s') : null,
            ]);
        }
        header('Location: /melding.php?id=' . $id . '#protocollen');
        exit;
    }

    if ($actie === 'taak_toevoegen') {
        $omschrijving = trim($_POST['omschrijving'] ?? '');
        if ($omschrijving !== '') {
            $volgorde_stmt = $pdo->prepare('SELECT COALESCE(MAX(volgorde), 0) + 1 FROM melding_taken WHERE melding_id = :m');
            $volgorde_stmt->execute(['m' => $id]);
            $volgende_volgorde = (int) $volgorde_stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO melding_taken (melding_id, omschrijving, aangemaakt_door_id, volgorde) VALUES (:m, :o, :g, :v)'
            );
            $stmt->execute(['m' => $id, 'o' => $omschrijving, 'g' => $_SESSION['gebruiker_id'], 'v' => $volgende_volgorde]);
        }
        header('Location: /melding.php?id=' . $id . '#taken');
        exit;
    }

    if ($actie === 'taak_wisselen') {
        $taak_id = (int) ($_POST['taak_id'] ?? 0);
        $huidige_stmt = $pdo->prepare('SELECT afgevinkt FROM melding_taken WHERE id = :id AND melding_id = :m');
        $huidige_stmt->execute(['id' => $taak_id, 'm' => $id]);
        $huidig = $huidige_stmt->fetchColumn();
        if ($huidig !== false) {
            $nieuw = $huidig ? 0 : 1;
            $stmt = $pdo->prepare(
                'UPDATE melding_taken SET afgevinkt = :a, afgevinkt_door_id = :g, afgevinkt_op = :t WHERE id = :id'
            );
            $stmt->execute([
                'a' => $nieuw,
                'g' => $nieuw ? $_SESSION['gebruiker_id'] : null,
                't' => $nieuw ? date('Y-m-d H:i:s') : null,
                'id' => $taak_id,
            ]);
        }
        header('Location: /melding.php?id=' . $id . '#taken');
        exit;
    }

    if ($actie === 'taak_verwijderen') {
        $taak_id = (int) ($_POST['taak_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM melding_taken WHERE id = :id AND melding_id = :m');
        $stmt->execute(['id' => $taak_id, 'm' => $id]);
        header('Location: /melding.php?id=' . $id . '#taken');
        exit;
    }

    if ($actie === 'notitie_toevoegen') {
        $notitie = trim($_POST['notitie'] ?? '');
        if ($notitie !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO melding_notities (melding_id, notitie, auteur, gebruiker_id) VALUES (:m, :n, :a, :g)'
            );
            $stmt->execute([
                'm' => $id,
                'n' => $notitie,
                'a' => huidige_gebruiker_naam(),
                'g' => $_SESSION['gebruiker_id'],
            ]);

            // ;locatienaam-commando in de notitie werkt het locatieveld bij
            $locatie_via_commando = vind_locatie_commando($pdo, $notitie);
            if ($locatie_via_commando) {
                $loc_stmt = $pdo->prepare(
                    'UPDATE meldingen SET locatie = :loc, bijgewerkt_door_id = :gebruiker WHERE id = :id'
                );
                $loc_stmt->execute([
                    'loc' => $locatie_via_commando['naam'],
                    'gebruiker' => $_SESSION['gebruiker_id'],
                    'id' => $id,
                ]);
            }
        }
        header('Location: /melding.php?id=' . $id . '#log');
        exit;
    }

    if ($actie === 'label_koppelen') {
        $label_id = (int) ($_POST['label_id'] ?? 0);
        if ($label_id > 0) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO melding_labels (melding_id, label_id) VALUES (:m, :l)');
            $stmt->execute(['m' => $id, 'l' => $label_id]);
        }
        header('Location: /melding.php?id=' . $id . '#labels');
        exit;
    }

    if ($actie === 'label_ontkoppelen') {
        $label_id = (int) ($_POST['label_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM melding_labels WHERE melding_id = :m AND label_id = :l');
        $stmt->execute(['m' => $id, 'l' => $label_id]);
        header('Location: /melding.php?id=' . $id . '#labels');
        exit;
    }

    // herlaad melding na eventuele wijziging
    $melding_stmt->execute(['id' => $id]);
    $melding = $melding_stmt->fetch();
}

$hoofdclassificaties = get_hoofdclassificaties($pdo);
$gekoppelde_labels = get_labels_voor_melding($pdo, $id);
$toewijsbare_gebruikers = get_toewijsbare_gebruikers($pdo);
$alle_statussen = get_statussen($pdo);
$crew_lijst = get_crew($pdo);
$gekoppelde_label_ids = array_column($gekoppelde_labels, 'id');
$alle_labels = get_labels($pdo);
$beschikbare_labels = array_filter($alle_labels, fn($l) => !in_array($l['id'], $gekoppelde_label_ids, true));
$subs_per_hoofd = get_subclassificaties_gegroepeerd($pdo);

// ---- Gekoppelde protocollen --------------------------------------------
$gekoppeld_stmt = $pdo->prepare(
    'SELECT p.*, s.naam AS sub_naam, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur
     FROM protocollen p
     LEFT JOIN subclassificaties s ON s.id = p.subclassificatie_id
     LEFT JOIN hoofdclassificaties h ON h.id = s.hoofdclassificatie_id
     JOIN melding_protocollen mp ON mp.protocol_id = p.id
     WHERE mp.melding_id = :id ORDER BY p.titel'
);
$gekoppeld_stmt->execute(['id' => $id]);
$gekoppelde_protocollen = $gekoppeld_stmt->fetchAll();
$gekoppelde_ids = array_column($gekoppelde_protocollen, 'id');

// beschikbare protocollen om te koppelen (nog niet gekoppeld)
$alle_protocollen = get_protocollen($pdo);
$beschikbare_protocollen = array_filter($alle_protocollen, fn($p) => !in_array($p['id'], $gekoppelde_ids, true));
$losse_taken = get_losse_taken($pdo, $id);

// ---- Notities / log -----------------------------------------------------
$notities_stmt = $pdo->prepare('SELECT * FROM melding_notities WHERE melding_id = :id ORDER BY aangemaakt_op DESC');
$notities_stmt->execute(['id' => $id]);
$notities = $notities_stmt->fetchAll();

$mijn_instellingen = huidige_gebruiker_instellingen($pdo);

$actief = 'dashboard';
$paginatitel = $melding['meld_id'];
include __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['aangemaakt'])): ?>
    <div class="alert alert-success">Melding <?= e($melding['meld_id']) ?> is aangemaakt.</div>
<?php elseif (isset($_GET['bijgewerkt'])): ?>
    <div class="alert alert-success">Melding is bijgewerkt.</div>
<?php endif; ?>

<div class="detail-head">
    <div>
        <p class="eyebrow"><?= $melding['attentie'] ? '⚠️ ' : '' ?><?= e($melding['meld_id']) ?></p>
        <h1><?= e($melding['titel']) ?></h1>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <span class="tag <?= prioriteit_class($melding['prioriteit']) ?>"><?= prioriteit_label($melding['prioriteit']) ?></span>
        <?= status_tag_html($melding['status']) ?>
        <form method="post" style="margin:0;">
            <input type="hidden" name="actie" value="attentie_wisselen">
            <button type="submit" class="btn btn-small <?= $melding['attentie'] ? 'btn-danger' : '' ?>" title="<?= $melding['attentie'] ? 'Attentiesignaal wegnemen' : 'Attentiesignaal geven — geeft een geluidssignaal en ⚠️ op alle overzichten' ?>">
                <?= $melding['attentie'] ? '✓ Attentie wegnemen' : '⚠️ Attentie geven' ?>
            </button>
        </form>
    </div>
</div>

<div class="info-compact">
    <div class="info-compact-item"><div class="k">Locatie</div><div class="v"><?= e($melding['locatie'] ?: '—') ?></div></div>
    <div class="info-compact-item"><div class="k">Hoofdclassificatie</div><div class="v"><?= e($melding['hoofd_naam'] ?: '—') ?></div></div>
    <div class="info-compact-item"><div class="k">Subclassificatie</div><div class="v"><?= e($melding['sub_naam'] ?: '—') ?></div></div>
    <div class="info-compact-item"><div class="k">Gemeld door</div><div class="v"><?= e($melding['gemeld_door'] ?: '—') ?></div></div>
    <div class="info-compact-item"><div class="k">Aangemaakt</div><div class="v"><?= (new DateTime($melding['aangemaakt_op']))->format('d-m-Y H:i') ?></div></div>
    <div class="info-compact-item"><div class="k">Toegewezen aan</div><div class="v"><?= e($melding['toegewezen_aan_naam'] ?: '—') ?><?= $melding['toegewezen_aan_functie'] ? ' <span class="v-sub">(' . e($melding['toegewezen_aan_functie']) . ')</span>' : '' ?><?= $melding['toegewezen_aan_telefoon'] ? ' <a href="tel:' . e(preg_replace('/[^0-9+]/', '', $melding['toegewezen_aan_telefoon'])) . '" class="v-sub" style="color:var(--amber);">' . e($melding['toegewezen_aan_telefoon']) . '</a>' : '' ?></div></div>
    <div class="info-compact-item"><div class="k">Toegewezen centralist</div><div class="v"><?= e($melding['toegewezen_centralist_naam'] ?: '—') ?><?= $melding['toegewezen_centralist_functie'] ? ' <span class="v-sub">(' . e($melding['toegewezen_centralist_functie']) . ')</span>' : '' ?></div></div>
    <div class="info-compact-item"><div class="k">Ingevoerd door</div><div class="v"><?= e($melding['aangemaakt_door_naam'] ?: 'onbekend') ?></div></div>
    <div class="info-compact-item"><div class="k">Laatst bijgewerkt door</div><div class="v"><?= e($melding['bijgewerkt_door_naam'] ?: '—') ?></div></div>
    <div class="info-compact-item">
        <div class="k">Labels</div>
        <div class="v">
            <div style="display:flex; flex-wrap:wrap; gap:4px; <?= $gekoppelde_labels ? 'margin-bottom:5px;' : '' ?>">
                <?php foreach ($gekoppelde_labels as $l): ?>
                    <form method="post" style="display:inline-flex;">
                        <input type="hidden" name="actie" value="label_ontkoppelen">
                        <input type="hidden" name="label_id" value="<?= $l['id'] ?>">
                        <button type="submit" class="cat-chip" style="border:none; cursor:pointer; display:inline-flex; align-items:center; gap:4px; background: <?= e($l['kleur']) ?>22; color: <?= e($l['kleur']) ?>; font-family:inherit;" title="Klik om dit label te verwijderen">
                            <?= e($l['naam']) ?> &times;
                        </button>
                    </form>
                <?php endforeach; ?>
                <?php if (!$gekoppelde_labels): ?>—<?php endif; ?>
            </div>
            <?php if ($beschikbare_labels): ?>
                <form method="post">
                    <input type="hidden" name="actie" value="label_koppelen">
                    <select name="label_id" onchange="this.form.submit()" style="width:100%; font-size:11px; padding:3px 5px; font-family: var(--font-body);">
                        <option value="" selected disabled>+ label toevoegen...</option>
                        <?php foreach ($beschikbare_labels as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= e($l['naam']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php elseif (!$alle_labels): ?>
                <a href="/admin/labels.php" style="color: var(--amber); font-size:11px;">+ label aanmaken</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="panel">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h2 style="margin:0;">Logboek</h2>
        <label for="logboek-toggle" class="log-toggle-wrap" title="Logboek in-/uitklappen">
            <span class="log-toggle-switch"></span> In-/uitklappen
        </label>
    </div>
    <input type="checkbox" id="logboek-toggle" class="log-toggle-checkbox" checked>
    <div class="row-log">
        <?php if (!$notities): ?>
            <p style="color: var(--muted);">Nog geen notities.</p>
        <?php endif; ?>
        <?php foreach ($notities as $n): ?>
            <div class="note">
                <div><?= nl2br(e($n['notitie'])) ?></div>
                <div class="meta"><?= e($n['auteur'] ?: 'Onbekend') ?> · <?= (new DateTime($n['aangemaakt_op']))->format('d-m-Y H:i') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($melding['omschrijving']): ?>
    <div class="panel">
        <h2>Omschrijving</h2>
        <p style="white-space:pre-line; color: var(--text); margin:0;"><?= e($melding['omschrijving']) ?></p>
    </div>
<?php endif; ?>

<div class="split-columns">
    <div class="panel">
        <h2>Classificatie &amp; status</h2>

        <div class="subsectie">
            <h3>Classificatie</h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="actie" value="classificatie_bijwerken">
                <div class="field">
                    <label for="hoofdclassificatie_id2">Hoofdclassificatie</label>
                    <select id="hoofdclassificatie_id2" name="hoofdclassificatie_id">
                        <option value="">Geen hoofdclassificatie</option>
                        <?php foreach ($hoofdclassificaties as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= (int) $melding['hoofdclassificatie_id'] === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['naam']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="subclassificatie_id2">Subclassificatie</label>
                    <select id="subclassificatie_id2" name="subclassificatie_id">
                        <option value="">Geen subclassificatie</option>
                    </select>
                </div>
                <div class="actions full">
                    <button type="submit" class="btn btn-primary">Classificatie opslaan</button>
                </div>
            </form>
        </div>

        <div class="subsectie">
            <h3>Status</h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="actie" value="status_bijwerken">
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($alle_statussen as $s): ?>
                            <option value="<?= e($s['sleutel']) ?>" <?= $melding['status'] === $s['sleutel'] ? 'selected' : '' ?>><?= e($s['naam']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="prioriteit2">Prioriteit</label>
                    <select id="prioriteit2" name="prioriteit">
                        <?php foreach (['laag','normaal','hoog','kritiek'] as $p): ?>
                            <option value="<?= $p ?>" <?= $melding['prioriteit'] === $p ? 'selected' : '' ?>><?= prioriteit_label($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="toegewezen_aan_zoek">Toegewezen aan</label>
                    <div class="combo-search" data-combo="toegewezen_aan">
                        <input type="text" id="toegewezen_aan_zoek" class="combo-input" autocomplete="off" placeholder="Zoek crewlid..."
                               value="<?= $melding['toegewezen_aan_naam'] ? e($melding['toegewezen_aan_naam']) . ($melding['toegewezen_aan_functie'] ? ' (' . e($melding['toegewezen_aan_functie']) . ')' : '') : '' ?>">
                        <input type="hidden" name="toegewezen_aan_crew_id" class="combo-value" value="<?= (int) ($melding['toegewezen_aan_crew_id'] ?? 0) ?: '' ?>">
                        <div class="combo-opties" hidden>
                            <div class="combo-optie combo-optie-leeg" data-id="" data-zoek="">— Niemand toegewezen —</div>
                            <?php foreach ($crew_lijst as $c): ?>
                                <div class="combo-optie" data-id="<?= $c['id'] ?>" data-naam="<?= e($c['naam']) ?><?= $c['functie'] ? ' (' . e($c['functie']) . ')' : '' ?>" data-zoek="<?= e(mb_strtolower($c['naam'] . ' ' . ($c['functie'] ?? ''))) ?>">
                                    <span><?= e($c['naam']) ?></span>
                                    <?php if ($c['functie']): ?><span class="combo-functie"><?= e($c['functie']) ?></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$crew_lijst): ?>
                                <div class="combo-geen-resultaat">Nog geen crew aangemaakt. Ga naar Beheer &rarr; Crew.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label for="toegewezen_centralist_zoek">Toegewezen centralist</label>
                    <div class="combo-search" data-combo="toegewezen_centralist">
                        <input type="text" id="toegewezen_centralist_zoek" class="combo-input" autocomplete="off" placeholder="Zoek gebruiker..."
                               value="<?= $melding['toegewezen_centralist_naam'] ? e($melding['toegewezen_centralist_naam']) . ($melding['toegewezen_centralist_functie'] ? ' (' . e($melding['toegewezen_centralist_functie']) . ')' : '') : '' ?>">
                        <input type="hidden" name="toegewezen_centralist_id" class="combo-value" value="<?= (int) ($melding['toegewezen_centralist_id'] ?? 0) ?: '' ?>">
                        <div class="combo-opties" hidden>
                            <div class="combo-optie combo-optie-leeg" data-id="" data-zoek="">— Geen centralist toegewezen —</div>
                            <?php foreach ($toewijsbare_gebruikers as $g): ?>
                                <div class="combo-optie" data-id="<?= $g['id'] ?>" data-naam="<?= e($g['naam']) ?><?= $g['functie'] ? ' (' . e($g['functie']) . ')' : '' ?>" data-zoek="<?= e(mb_strtolower($g['naam'] . ' ' . ($g['functie'] ?? ''))) ?>">
                                    <span><?= e($g['naam']) ?></span>
                                    <?php if ($g['functie']): ?><span class="combo-functie"><?= e($g['functie']) ?></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="actions full">
                    <button type="submit" class="btn btn-primary">Status opslaan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-stack">
        <div class="panel" id="log">
            <h2>Nieuwe update</h2>
            <form method="post">
                <input type="hidden" name="actie" value="notitie_toevoegen">
                <div class="field">
                    <label for="notitie">Nieuwe notitie (als <?= e(huidige_gebruiker_naam()) ?>)</label>
                    <textarea id="notitie" name="notitie" placeholder="Update, actie ondernomen, overdracht..." required></textarea>
                    <p style="color:var(--muted); font-size:11.5px; margin:6px 0 0;">Enter slaat de notitie meteen op (Shift+Enter voor een nieuwe regel binnen dezelfde notitie). Tip: typ <code>;locatienaam</code> om de locatie van deze melding automatisch bij te werken.</p>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Notitie toevoegen</button>
                </div>
            </form>
        </div>

        <div class="panel" id="protocollen">
            <h2>Protocol + Taken</h2>

            <div class="subsectie">
                <h3>Protocol</h3>

                <?php if ($beschikbare_protocollen): ?>
                    <form method="post" style="display:flex; gap:10px; margin-bottom:14px;">
                        <input type="hidden" name="actie" value="protocol_koppelen">
                        <select name="protocol_id" style="flex:1;">
                            <?php foreach ($beschikbare_protocollen as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['titel']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn">Koppelen</button>
                    </form>
                <?php elseif (!$alle_protocollen && is_beheerder()): ?>
                    <p style="color: var(--muted);">
                        Er zijn nog geen protocollen aangemaakt. Ga naar
                        <a href="/admin/protocollen.php" style="color: var(--amber);">Beheer &rarr; Protocollen</a> om er een toe te voegen.
                    </p>
                <?php endif; ?>

                <?php if (!$gekoppelde_protocollen): ?>
                    <p style="color: var(--muted); margin-top:0;">Nog geen protocollen gekoppeld aan deze melding.</p>
                <?php endif; ?>

                <?php foreach ($gekoppelde_protocollen as $p): ?>
                    <div class="protocol-card">
                        <div class="row-top">
                            <h3><?= e($p['titel']) ?></h3>
                            <form method="post" onsubmit="return confirm('Protocol loskoppelen van deze melding?');">
                                <input type="hidden" name="actie" value="protocol_ontkoppelen">
                                <input type="hidden" name="protocol_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger">Loskoppelen</button>
                            </form>
                        </div>
                        <?php if ($p['sub_naam']): ?>
                            <p class="cat-chip" style="display:inline-block; background: <?= e($p['hoofd_kleur']) ?>22; color: <?= e($p['hoofd_kleur']) ?>; margin: 0 0 8px;">
                                <?= e($p['hoofd_naam']) ?> &middot; <?= e($p['sub_naam']) ?>
                            </p>
                        <?php endif; ?>
                        <p><?= e($p['inhoud']) ?></p>

                        <?php $protocol_links = get_protocol_links($pdo, $p['id']); ?>
                        <?php if ($protocol_links): ?>
                            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
                                <?php foreach ($protocol_links as $link): ?>
                                    <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener" class="btn btn-small" style="text-decoration:none;">
                                        <?= e($link['label']) ?> &#8599;
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php $subtaken = get_subtaken_met_status($pdo, $p['id'], $id); ?>
                        <?php if ($subtaken): ?>
                            <?php
                                $aantal_afgevinkt = count(array_filter($subtaken, fn($t) => (int) $t['afgevinkt'] === 1));
                            ?>
                            <div class="subtaken-blok">
                                <p class="subtaken-kop">Subtaken (<?= $aantal_afgevinkt ?>/<?= count($subtaken) ?>)</p>
                                <?php foreach ($subtaken as $t): ?>
                                    <form method="post" class="subtaak-regel">
                                        <input type="hidden" name="actie" value="subtaak_wisselen">
                                        <input type="hidden" name="subtaak_id" value="<?= $t['id'] ?>">
                                        <label>
                                            <input type="checkbox" onchange="this.form.submit()" <?= $t['afgevinkt'] ? 'checked' : '' ?>>
                                            <span class="<?= $t['afgevinkt'] ? 'afgevinkt' : '' ?>"><?= e($t['omschrijving']) ?></span>
                                        </label>
                                        <?php if ($t['afgevinkt'] && $t['afgevinkt_door_naam']): ?>
                                            <span class="subtaak-meta"><?= e($t['afgevinkt_door_naam']) ?> · <?= (new DateTime($t['afgevinkt_op']))->format('d-m H:i') ?></span>
                                        <?php endif; ?>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="subsectie" id="taken">
                <h3>Taken</h3>

                <?php if (!$losse_taken): ?>
                    <p style="color: var(--muted); margin-top:0;">Nog geen losse taken.</p>
                <?php endif; ?>
                <?php foreach ($losse_taken as $t): ?>
                    <div class="losse-taak-regel">
                        <form method="post" style="flex:1; margin:0;">
                            <input type="hidden" name="actie" value="taak_wisselen">
                            <input type="hidden" name="taak_id" value="<?= $t['id'] ?>">
                            <label>
                                <input type="checkbox" onchange="this.form.submit()" <?= $t['afgevinkt'] ? 'checked' : '' ?>>
                                <span class="<?= $t['afgevinkt'] ? 'afgevinkt' : '' ?>"><?= e($t['omschrijving']) ?></span>
                            </label>
                        </form>
                        <?php if ($t['afgevinkt'] && $t['afgevinkt_door_naam']): ?>
                            <span class="subtaak-meta"><?= e($t['afgevinkt_door_naam']) ?> · <?= (new DateTime($t['afgevinkt_op']))->format('d-m H:i') ?></span>
                        <?php endif; ?>
                        <form method="post" style="margin:0;" onsubmit="return confirm('Taak verwijderen?');">
                            <input type="hidden" name="actie" value="taak_verwijderen">
                            <input type="hidden" name="taak_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-small btn-danger" title="Verwijderen">&times;</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <form method="post" style="display:flex; gap:6px; margin-top:10px;">
                    <input type="hidden" name="actie" value="taak_toevoegen">
                    <input type="text" name="omschrijving" placeholder="+ taak toevoegen..." style="flex:1; font-size:12.5px; padding:5px 8px;" required>
                    <button type="submit" class="btn btn-small">Toevoegen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const subclassificaties2 = <?= json_encode($subs_per_hoofd, JSON_UNESCAPED_UNICODE) ?>;
const gekozenSub2 = <?= json_encode((string) $melding['subclassificatie_id']) ?>;

const hoofdSelect2 = document.getElementById('hoofdclassificatie_id2');
const subSelect2 = document.getElementById('subclassificatie_id2');

function vulSubclassificaties2() {
    const hoofdId = hoofdSelect2.value;
    subSelect2.innerHTML = '<option value="">Geen subclassificatie</option>';
    const lijst = subclassificaties2[hoofdId] || [];
    lijst.forEach(function (sub) {
        const optie = document.createElement('option');
        optie.value = sub.id;
        optie.textContent = sub.naam;
        if (String(sub.id) === gekozenSub2) {
            optie.selected = true;
        }
        subSelect2.appendChild(optie);
    });
    subSelect2.disabled = lijst.length === 0;
}

hoofdSelect2.addEventListener('change', vulSubclassificaties2);
vulSubclassificaties2();

// Doorzoekbare gebruikers-dropdown (combobox) voor "Toegewezen aan" en
// "Toegewezen centralist". Typen filtert de lijst; klikken op een optie
// zet zowel de zichtbare tekst als het verborgen ID-veld dat echt wordt
// opgeslagen.
(function () {
    document.querySelectorAll('.combo-search').forEach(function (container) {
        const input = container.querySelector('.combo-input');
        const hidden = container.querySelector('.combo-value');
        const opties = container.querySelector('.combo-opties');
        const alleOpties = Array.from(opties.querySelectorAll('.combo-optie'));

        function toonOpties() {
            const zoekterm = input.value.trim().toLowerCase();
            alleOpties.forEach(function (optie) {
                const match = optie.classList.contains('combo-optie-leeg')
                    || optie.dataset.zoek.includes(zoekterm);
                optie.hidden = !match;
            });
            opties.hidden = false;
        }

        input.addEventListener('focus', toonOpties);
        input.addEventListener('input', toonOpties);
        input.addEventListener('blur', function () {
            setTimeout(function () { opties.hidden = true; }, 150);
        });

        alleOpties.forEach(function (optie) {
            optie.addEventListener('mousedown', function (e) {
                e.preventDefault(); // voorkomt dat blur eerder vuurt dan de klik
                hidden.value = optie.dataset.id || '';
                input.value = optie.classList.contains('combo-optie-leeg') ? '' : optie.dataset.naam;
                opties.hidden = true;
            });
        });
    });
})();

// Enter slaat de notitie meteen op (Shift+Enter voegt een nieuwe regel toe
// binnen dezelfde notitie, zoals in de meeste chatprogramma's).
(function () {
    const notitieVeld = document.getElementById('notitie');
    if (!notitieVeld) {
        return;
    }
    notitieVeld.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (notitieVeld.value.trim() !== '') {
                notitieVeld.form.submit();
            }
        }
    });
})();

// Ververst deze melding automatisch zodat wijzigingen van collega's
// (status, notities, subtaken, etc.) live zichtbaar worden bij iedereen
// die deze melding open heeft staan. Pauzeert zolang iemand aan het typen
// is in een tekstveld, textarea, of terwijl er tekst geselecteerd is
// (bv. om te kopieren), zodat niemand onderbroken wordt. Interval komt uit
// de persoonlijke instellingen (/profiel.php); op "Uit" gebeurt er niets.
(function () {
    const ververs_seconden = <?= (int) $mijn_instellingen['auto_refresh_seconden'] ?>;

    let veld_actief = false;
    document.querySelectorAll('input[type="text"], textarea').forEach(function (veld) {
        veld.addEventListener('focus', function () { veld_actief = true; });
        veld.addEventListener('blur', function () { veld_actief = false; });
    });

    // Na een opgeslagen notitie ververst de pagina met #log in de URL --
    // zet de focus dan automatisch terug in het notitieveld zodat je
    // meteen door kan typen voor de volgende update. Gebeurt na het
    // registreren van de listeners hierboven, zodat de auto-refresh het
    // veld meteen als actief herkent en niet halverwege ververst.
    if (window.location.hash === '#log') {
        const notitieVeld = document.getElementById('notitie');
        if (notitieVeld) {
            notitieVeld.focus();
        }
    }

    if (ververs_seconden <= 0) {
        return;
    }

    setInterval(function () {
        const heeft_selectie = (window.getSelection() || '').toString().length > 0;
        if (!veld_actief && !heeft_selectie && document.visibilityState === 'visible') {
            sessionStorage.setItem('meldkamer_scroll_' + location.pathname, window.scrollY);
            window.location.reload();
        }
    }, ververs_seconden * 1000);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
