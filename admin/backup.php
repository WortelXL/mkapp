<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$ALLE_SECTIES = ['classificaties', 'statussen', 'protocollen', 'locaties', 'labels', 'crew'];

// ============================================================
// EXPORT — bouwt een JSON-bestand en stuurt het als download,
// vóórdat er ook maar iets van HTML gerenderd wordt.
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'exporteren') {
    $secties = array_values(array_intersect($_POST['secties'] ?? [], $ALLE_SECTIES));
    if (!$secties) {
        $secties = $ALLE_SECTIES;
    }

    $data = [];

    if (in_array('classificaties', $secties, true)) {
        $data['hoofdclassificaties'] = $pdo->query(
            'SELECT naam, kleur, beschrijving FROM hoofdclassificaties ORDER BY naam ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $data['subclassificaties'] = $pdo->query(
            'SELECT s.naam, s.beschrijving, s.standaard_prioriteit, h.naam AS hoofd_naam
             FROM subclassificaties s
             JOIN hoofdclassificaties h ON h.id = s.hoofdclassificatie_id
             ORDER BY h.naam ASC, s.naam ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array('statussen', $secties, true)) {
        // Ingebouwde 4 (open/in_behandeling/afgehandeld/geannuleerd) horen
        // niet in de export -- die bestaan overal al en zijn niet los te
        // "herstellen".
        $data['statussen'] = $pdo->query(
            "SELECT sleutel, naam, kleur, categorie FROM statussen WHERE ingebouwd = 0 ORDER BY volgorde ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array('protocollen', $secties, true)) {
        $protocollen_rijen = $pdo->query(
            'SELECT p.id, p.titel, p.inhoud, s.naam AS sub_naam, h.naam AS hoofd_naam
             FROM protocollen p
             LEFT JOIN subclassificaties s ON s.id = p.subclassificatie_id
             LEFT JOIN hoofdclassificaties h ON h.id = s.hoofdclassificatie_id
             ORDER BY p.titel ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($protocollen_rijen as &$p) {
            $subtaken_stmt = $pdo->prepare('SELECT omschrijving, volgorde FROM protocol_subtaken WHERE protocol_id = :p ORDER BY volgorde ASC');
            $subtaken_stmt->execute(['p' => $p['id']]);
            $p['subtaken'] = $subtaken_stmt->fetchAll(PDO::FETCH_ASSOC);

            $links_stmt = $pdo->prepare('SELECT label, url, volgorde FROM protocol_links WHERE protocol_id = :p ORDER BY volgorde ASC');
            $links_stmt->execute(['p' => $p['id']]);
            $p['links'] = $links_stmt->fetchAll(PDO::FETCH_ASSOC);

            unset($p['id']);
        }
        unset($p);
        $data['protocollen'] = $protocollen_rijen;
    }

    if (in_array('locaties', $secties, true)) {
        $data['locaties'] = $pdo->query(
            'SELECT naam, beschrijving FROM locaties ORDER BY naam ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array('labels', $secties, true)) {
        $data['labels'] = $pdo->query(
            'SELECT naam, kleur, beschrijving FROM labels ORDER BY naam ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array('crew', $secties, true)) {
        $data['crew'] = $pdo->query(
            'SELECT naam, functie, telefoonnummer FROM crew ORDER BY naam ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    $export = [
        'export_versie'   => 1,
        'geexporteerd_op' => date('Y-m-d H:i:s'),
        'evenement'       => event_naam($pdo),
        'secties'         => $secties,
        'data'            => $data,
    ];

    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $bestandsnaam = 'meldkamer-backup-' . date('Y-m-d_His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $bestandsnaam . '"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

// ============================================================
// IMPORT
// ============================================================
$fout = '';
$succes = '';
$samenvatting = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'importeren') {
    if (empty($_FILES['bestand']) || $_FILES['bestand']['error'] !== UPLOAD_ERR_OK) {
        $fout = 'Kies eerst een geldig back-upbestand (.json).';
    } else {
        $inhoud = file_get_contents($_FILES['bestand']['tmp_name']);
        $geparsed = json_decode((string) $inhoud, true);

        if (!is_array($geparsed) || !isset($geparsed['data']) || !is_array($geparsed['data'])) {
            $fout = 'Dit bestand lijkt geen geldige Meldkamer-backup te zijn.';
        } else {
            $data = $geparsed['data'];

            try {
                $pdo->beginTransaction();

                // ---- Classificaties (hoofd eerst, dan sub) --------------
                $hoofd_naam_naar_id = [];
                foreach ($pdo->query('SELECT id, naam FROM hoofdclassificaties') as $rij) {
                    $hoofd_naam_naar_id[$rij['naam']] = (int) $rij['id'];
                }

                if (!empty($data['hoofdclassificaties'])) {
                    $aantal = 0;
                    foreach ($data['hoofdclassificaties'] as $h) {
                        $naam = trim($h['naam'] ?? '');
                        if ($naam === '' || isset($hoofd_naam_naar_id[$naam])) {
                            continue;
                        }
                        $stmt = $pdo->prepare('INSERT INTO hoofdclassificaties (naam, kleur, beschrijving) VALUES (:n, :k, :b)');
                        $stmt->execute(['n' => $naam, 'k' => $h['kleur'] ?? '#f5a524', 'b' => $h['beschrijving'] ?? null]);
                        $hoofd_naam_naar_id[$naam] = (int) $pdo->lastInsertId();
                        $aantal++;
                    }
                    $samenvatting['Hoofdclassificaties'] = $aantal;
                }

                $sub_key_naar_id = [];
                foreach ($pdo->query(
                    'SELECT s.id, s.naam, h.naam AS hoofd_naam FROM subclassificaties s
                     JOIN hoofdclassificaties h ON h.id = s.hoofdclassificatie_id'
                ) as $rij) {
                    $sub_key_naar_id[$rij['hoofd_naam'] . '|' . $rij['naam']] = (int) $rij['id'];
                }

                if (!empty($data['subclassificaties'])) {
                    $aantal = 0;
                    foreach ($data['subclassificaties'] as $s) {
                        $hoofd_naam = trim($s['hoofd_naam'] ?? '');
                        $naam = trim($s['naam'] ?? '');
                        if ($hoofd_naam === '' || $naam === '') {
                            continue;
                        }
                        if (!isset($hoofd_naam_naar_id[$hoofd_naam])) {
                            $stmt = $pdo->prepare('INSERT INTO hoofdclassificaties (naam) VALUES (:n)');
                            $stmt->execute(['n' => $hoofd_naam]);
                            $hoofd_naam_naar_id[$hoofd_naam] = (int) $pdo->lastInsertId();
                        }
                        $key = $hoofd_naam . '|' . $naam;
                        if (isset($sub_key_naar_id[$key])) {
                            continue;
                        }
                        $stmt = $pdo->prepare(
                            'INSERT INTO subclassificaties (hoofdclassificatie_id, naam, beschrijving, standaard_prioriteit) VALUES (:h, :n, :b, :p)'
                        );
                        $stmt->execute([
                            'h' => $hoofd_naam_naar_id[$hoofd_naam],
                            'n' => $naam,
                            'b' => $s['beschrijving'] ?? null,
                            'p' => $s['standaard_prioriteit'] ?? null,
                        ]);
                        $sub_key_naar_id[$key] = (int) $pdo->lastInsertId();
                        $aantal++;
                    }
                    $samenvatting['Subclassificaties'] = $aantal;
                }

                // ---- Statussen (alleen eigen, niet de 4 ingebouwde) -----
                if (!empty($data['statussen'])) {
                    $bestaande_sleutels = statussen_sleutels(get_statussen($pdo));
                    $aantal = 0;
                    $volgorde_stmt = $pdo->query('SELECT COALESCE(MAX(volgorde), 0) FROM statussen');
                    $volgende_volgorde = (int) $volgorde_stmt->fetchColumn();
                    foreach ($data['statussen'] as $s) {
                        $sleutel = trim($s['sleutel'] ?? '');
                        $naam = trim($s['naam'] ?? '');
                        if ($sleutel === '' || $naam === '' || in_array($sleutel, $bestaande_sleutels, true)) {
                            continue;
                        }
                        $categorie = ($s['categorie'] ?? 'actief') === 'afgerond' ? 'afgerond' : 'actief';
                        $volgende_volgorde++;
                        $stmt = $pdo->prepare(
                            'INSERT INTO statussen (sleutel, naam, kleur, categorie, ingebouwd, volgorde) VALUES (:s, :n, :k, :c, 0, :v)'
                        );
                        $stmt->execute([
                            's' => $sleutel, 'n' => $naam, 'k' => $s['kleur'] ?? '#6b7280',
                            'c' => $categorie, 'v' => $volgende_volgorde,
                        ]);
                        $bestaande_sleutels[] = $sleutel;
                        $aantal++;
                    }
                    $samenvatting['Statussen'] = $aantal;
                }

                // ---- Protocollen (+ subtaken + links) -------------------
                if (!empty($data['protocollen'])) {
                    $aantal = 0;
                    foreach ($data['protocollen'] as $p) {
                        $titel = trim($p['titel'] ?? '');
                        if ($titel === '') {
                            continue;
                        }
                        $sub_id = null;
                        $hoofd_naam = trim($p['hoofd_naam'] ?? '');
                        $sub_naam = trim($p['sub_naam'] ?? '');
                        if ($hoofd_naam !== '' && $sub_naam !== '' && isset($sub_key_naar_id[$hoofd_naam . '|' . $sub_naam])) {
                            $sub_id = $sub_key_naar_id[$hoofd_naam . '|' . $sub_naam];
                        }

                        $stmt = $pdo->prepare('INSERT INTO protocollen (titel, subclassificatie_id, inhoud) VALUES (:t, :s, :i)');
                        $stmt->execute(['t' => $titel, 's' => $sub_id, 'i' => $p['inhoud'] ?? '']);
                        $nieuw_protocol_id = (int) $pdo->lastInsertId();

                        foreach (($p['subtaken'] ?? []) as $t) {
                            if (trim($t['omschrijving'] ?? '') === '') {
                                continue;
                            }
                            $stmt = $pdo->prepare('INSERT INTO protocol_subtaken (protocol_id, omschrijving, volgorde) VALUES (:p, :o, :v)');
                            $stmt->execute(['p' => $nieuw_protocol_id, 'o' => $t['omschrijving'], 'v' => (int) ($t['volgorde'] ?? 0)]);
                        }
                        foreach (($p['links'] ?? []) as $l) {
                            if (trim($l['label'] ?? '') === '' || trim($l['url'] ?? '') === '') {
                                continue;
                            }
                            $stmt = $pdo->prepare('INSERT INTO protocol_links (protocol_id, label, url, volgorde) VALUES (:p, :l, :u, :v)');
                            $stmt->execute(['p' => $nieuw_protocol_id, 'l' => $l['label'], 'u' => $l['url'], 'v' => (int) ($l['volgorde'] ?? 0)]);
                        }
                        $aantal++;
                    }
                    $samenvatting['Protocollen'] = $aantal;
                }

                // ---- Locaties (overslaan bij bestaande naam) ------------
                if (!empty($data['locaties'])) {
                    $aantal = 0;
                    $stmt = $pdo->prepare('INSERT IGNORE INTO locaties (naam, beschrijving) VALUES (:n, :b)');
                    foreach ($data['locaties'] as $l) {
                        $naam = trim($l['naam'] ?? '');
                        if ($naam === '') {
                            continue;
                        }
                        $stmt->execute(['n' => $naam, 'b' => $l['beschrijving'] ?? null]);
                        $aantal += $stmt->rowCount();
                    }
                    $samenvatting['Locaties'] = $aantal;
                }

                // ---- Labels (overslaan bij bestaande naam) --------------
                if (!empty($data['labels'])) {
                    $aantal = 0;
                    $stmt = $pdo->prepare('INSERT IGNORE INTO labels (naam, kleur, beschrijving) VALUES (:n, :k, :b)');
                    foreach ($data['labels'] as $l) {
                        $naam = trim($l['naam'] ?? '');
                        if ($naam === '') {
                            continue;
                        }
                        $stmt->execute(['n' => $naam, 'k' => $l['kleur'] ?? '#f5a524', 'b' => $l['beschrijving'] ?? null]);
                        $aantal += $stmt->rowCount();
                    }
                    $samenvatting['Labels'] = $aantal;
                }

                // ---- Crew (altijd toevoegen als nieuw contact) ----------
                if (!empty($data['crew'])) {
                    $aantal = 0;
                    $stmt = $pdo->prepare('INSERT INTO crew (naam, functie, telefoonnummer) VALUES (:n, :f, :t)');
                    foreach ($data['crew'] as $c) {
                        $naam = trim($c['naam'] ?? '');
                        if ($naam === '') {
                            continue;
                        }
                        $stmt->execute(['n' => $naam, 'f' => $c['functie'] ?? null, 't' => $c['telefoonnummer'] ?? null]);
                        $aantal++;
                    }
                    $samenvatting['Crew'] = $aantal;
                }

                $pdo->commit();
                $succes = 'Import voltooid.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                $fout = 'Er ging iets mis tijdens de import, er is niets opgeslagen. Foutmelding: ' . $e->getMessage();
                $samenvatting = [];
            }
        }
    }
}

$actief = 'admin';
$paginatitel = 'Backup & Restore';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Backup &amp; Restore</h1>
        <p>Exporteer of importeer de basisinstellingen van je meldkamer — handig om snel een nieuw evenement met dezelfde configuratie op te zetten, of als reservekopie. Meldingen, gebruikers en API-tokens zitten hier bewust niet bij.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?>
    <div class="alert alert-success">
        <?= e($succes) ?>
        <?php if ($samenvatting): ?>
            <ul style="margin:8px 0 0; padding-left:18px;">
                <?php foreach ($samenvatting as $label => $aantal): ?>
                    <li><?= e($label) ?>: <?= (int) $aantal ?> nieuw toegevoegd</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>Exporteren</h2>
    <p style="color:var(--muted); margin-top:-8px;">Kies wat je wil meenemen. Het resultaat is één .json-bestand dat je kan bewaren of straks weer importeren.</p>
    <form method="post">
        <input type="hidden" name="actie" value="exporteren">
        <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:18px;">
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="secties[]" value="classificaties" checked> Classificaties (hoofd + sub)</label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="secties[]" value="statussen" checked> Statussen (eigen, niet de 4 ingebouwde)</label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="secties[]" value="protocollen" checked> Protocollen (incl. subtaken en links)</label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="secties[]" value="locaties" checked> Locaties</label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="secties[]" value="labels" checked> Labels</label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;"><input type="checkbox" name="secties[]" value="crew" checked> Crew</label>
        </div>
        <button type="submit" class="btn btn-primary">Exporteren als .json</button>
    </form>
</div>

<div class="panel">
    <h2>Importeren</h2>
    <p style="color:var(--muted); margin-top:-8px;">
        Importeert alles wat in het gekozen bestand staat. Classificaties, statussen, locaties en labels worden herkend op naam — bestaat iets al, dan wordt het overgeslagen (dus veilig om dezelfde backup meerdere keren te importeren). Protocollen en crew worden altijd als nieuw toegevoegd, dus die kunnen bij herhaald importeren dubbel komen te staan.
    </p>
    <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;" onsubmit="return confirm('Bestand importeren? Dit voegt de gegevens toe aan de huidige database (er wordt niets verwijderd of overschreven).');">
        <input type="hidden" name="actie" value="importeren">
        <input type="file" name="bestand" accept="application/json,.json" required>
        <button type="submit" class="btn btn-primary">Importeren</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
