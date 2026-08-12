<?php
require_once __DIR__ . '/includes/functions.php';
vereis_volledige_toegang();
$pdo = get_pdo();

$formaat = $_REQUEST['formaat'] ?? 'csv';
if (!in_array($formaat, ['csv', 'pdf'], true)) {
    $formaat = 'csv';
}

// ---- Bepalen welke meldingen geëxporteerd worden -------------------------
$geselecteerde_ids = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    $geselecteerde_ids = array_values(array_unique(array_map('intval', $_POST['ids'])));
}

$where  = [];
$params = [];

if ($geselecteerde_ids) {
    $plekhouders = implode(',', array_fill(0, count($geselecteerde_ids), '?'));
    $where[] = "m.id IN ($plekhouders)";
    $params = $geselecteerde_ids;
} else {
    // Zelfde filters als het archief (via GET), zodat "exporteer alle
    // gefilterde resultaten" precies aansluit op wat er op het scherm stond.
    $f_status     = $_GET['status'] ?? '';
    $f_hoofd      = $_GET['hoofd'] ?? '';
    $f_sub        = $_GET['sub'] ?? '';
    $f_prioriteit = $_GET['prioriteit'] ?? '';
    $f_label      = $_GET['label'] ?? '';
    $f_zoek       = trim($_GET['q'] ?? '');

    $afgeronde_sleutels_export = statussen_sleutels(get_afgeronde_statussen($pdo));

    if ($f_status !== '' && in_array($f_status, $afgeronde_sleutels_export, true)) {
        $where[] = 'm.status = :status';
        $params['status'] = $f_status;
    } else {
        $status_placeholders_export = [];
        foreach ($afgeronde_sleutels_export as $i => $sleutel) {
            $status_placeholders_export[] = ':exp_status' . $i;
            $params['exp_status' . $i] = $sleutel;
        }
        $where[] = $status_placeholders_export
            ? 'm.status IN (' . implode(',', $status_placeholders_export) . ')'
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
    if ($f_label !== '' && ctype_digit($f_label)) {
        $where[] = 'EXISTS (SELECT 1 FROM melding_labels ml2 WHERE ml2.melding_id = m.id AND ml2.label_id = :label)';
        $params['label'] = (int) $f_label;
    }
    if ($f_zoek !== '') {
        $where[] = '(m.titel LIKE :zoek OR m.meld_id LIKE :zoek OR m.locatie LIKE :zoek)';
        $params['zoek'] = '%' . $f_zoek . '%';
    }
}

if (!$where) {
    $where[] = '1 = 0'; // geen ids en geen filters: exporteer niets i.p.v. de hele tabel
}

$sql = "SELECT m.*,
               h.naam AS hoofd_naam,
               s.naam AS sub_naam,
               g.naam AS aangemaakt_door_naam,
               g2.naam AS bijgewerkt_door_naam
        FROM meldingen m
        LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
        LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
        LEFT JOIN gebruikers g ON g.id = m.aangemaakt_door_id
        LEFT JOIN gebruikers g2 ON g2.id = m.bijgewerkt_door_id
        WHERE " . implode(' AND ', $where) . '
        ORDER BY m.bijgewerkt_op DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meldingen = $stmt->fetchAll();

// Labels per melding erbij
$labels_per_melding = [];
$notities_per_melding = [];
$protocollen_per_melding = [];
$subtaken_per_melding = [];
$losse_taken_per_melding = [];
if ($meldingen) {
    $ids = array_column($meldingen, 'id');
    $plekhouders = implode(',', array_fill(0, count($ids), '?'));

    $labels_stmt = $pdo->prepare(
        "SELECT ml.melding_id, l.naam FROM melding_labels ml
         JOIN labels l ON l.id = ml.label_id
         WHERE ml.melding_id IN ($plekhouders) ORDER BY l.naam ASC"
    );
    $labels_stmt->execute($ids);
    foreach ($labels_stmt->fetchAll() as $rij) {
        $labels_per_melding[$rij['melding_id']][] = $rij['naam'];
    }

    // Chronologisch (oud -> nieuw), logischer om te lezen in een rapport
    $notities_stmt = $pdo->prepare(
        "SELECT * FROM melding_notities WHERE melding_id IN ($plekhouders) ORDER BY aangemaakt_op ASC"
    );
    $notities_stmt->execute($ids);
    foreach ($notities_stmt->fetchAll() as $rij) {
        $notities_per_melding[$rij['melding_id']][] = $rij;
    }

    $protocollen_stmt = $pdo->prepare(
        "SELECT mp.melding_id, p.id AS protocol_id, p.titel, p.inhoud FROM melding_protocollen mp
         JOIN protocollen p ON p.id = mp.protocol_id
         WHERE mp.melding_id IN ($plekhouders) ORDER BY p.titel ASC"
    );
    $protocollen_stmt->execute($ids);
    foreach ($protocollen_stmt->fetchAll() as $rij) {
        $protocollen_per_melding[$rij['melding_id']][] = $rij;
    }

    // Subtaken (van gekoppelde protocollen) met afvinkstatus per melding
    $subtaken_stmt = $pdo->prepare(
        "SELECT mp.melding_id, ps.protocol_id, ps.omschrijving, ps.volgorde,
                mss.afgevinkt, mss.afgevinkt_op, g.naam AS afgevinkt_door_naam
         FROM melding_protocollen mp
         JOIN protocol_subtaken ps ON ps.protocol_id = mp.protocol_id
         LEFT JOIN melding_subtaak_status mss ON mss.subtaak_id = ps.id AND mss.melding_id = mp.melding_id
         LEFT JOIN gebruikers g ON g.id = mss.afgevinkt_door_id
         WHERE mp.melding_id IN ($plekhouders)
         ORDER BY ps.volgorde ASC, ps.id ASC"
    );
    $subtaken_stmt->execute($ids);
    foreach ($subtaken_stmt->fetchAll() as $rij) {
        $subtaken_per_melding[$rij['melding_id']][$rij['protocol_id']][] = $rij;
    }

    // Losse taken (los van protocollen) per melding
    $losse_taken_stmt = $pdo->prepare(
        "SELECT lt.*, g.naam AS afgevinkt_door_naam
         FROM melding_taken lt
         LEFT JOIN gebruikers g ON g.id = lt.afgevinkt_door_id
         WHERE lt.melding_id IN ($plekhouders)
         ORDER BY lt.volgorde ASC, lt.id ASC"
    );
    $losse_taken_stmt->execute($ids);
    foreach ($losse_taken_stmt->fetchAll() as $rij) {
        $losse_taken_per_melding[$rij['melding_id']][] = $rij;
    }
}

// Statusgeschiedenis + doorlooptijden per melding
$status_tijdvakken_per_melding = [];
foreach ($meldingen as $m) {
    $geschiedenis = get_status_geschiedenis($pdo, $m['id']);
    $status_tijdvakken_per_melding[$m['id']] = bereken_status_tijdvakken($geschiedenis, $m['status'], $m['bijgewerkt_op']);
}

$datumstempel = date('Y-m-d_His');

// ============================================================
// CSV
// ============================================================
if ($formaat === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="archief_' . $datumstempel . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM, zodat Excel het correct opent

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Meld-ID', 'Titel', 'Hoofdclassificatie', 'Subclassificatie', 'Prioriteit', 'Status',
        'Locatie', 'Gemeld door', 'Ingevoerd door', 'Laatst bijgewerkt door',
        'Aangemaakt', 'Laatst bijgewerkt', 'Labels', 'Omschrijving', 'Logboek', 'Protocollen',
        'Subtaken', 'Losse taken', 'Doorlooptijd per status', 'Totale doorlooptijd',
    ], ';');

    foreach ($meldingen as $m) {
        $logboek_tekst = '';
        if (!empty($notities_per_melding[$m['id']])) {
            $regels = [];
            foreach ($notities_per_melding[$m['id']] as $n) {
                $regels[] = (new DateTime($n['aangemaakt_op']))->format('d-m-Y H:i') . ' - '
                    . ($n['auteur'] ?: 'onbekend') . ': ' . $n['notitie'];
            }
            $logboek_tekst = implode("\n", $regels);
        }

        $protocollen_tekst = '';
        if (!empty($protocollen_per_melding[$m['id']])) {
            $regels = [];
            foreach ($protocollen_per_melding[$m['id']] as $p) {
                $regels[] = $p['titel'] . ': ' . $p['inhoud'];
            }
            $protocollen_tekst = implode("\n\n", $regels);
        }

        $subtaken_tekst = '';
        if (!empty($protocollen_per_melding[$m['id']])) {
            $regels = [];
            foreach ($protocollen_per_melding[$m['id']] as $p) {
                $subtaken_van_protocol = $subtaken_per_melding[$m['id']][$p['protocol_id']] ?? [];
                foreach ($subtaken_van_protocol as $t) {
                    $vinkje = $t['afgevinkt'] ? '[x]' : '[ ]';
                    $regel = $vinkje . ' ' . $p['titel'] . ' - ' . $t['omschrijving'];
                    if ($t['afgevinkt']) {
                        $regel .= ' (afgevinkt door ' . ($t['afgevinkt_door_naam'] ?: 'onbekend')
                            . ' op ' . (new DateTime($t['afgevinkt_op']))->format('d-m-Y H:i') . ')';
                    }
                    $regels[] = $regel;
                }
            }
            $subtaken_tekst = implode("\n", $regels);
        }

        $losse_taken_tekst = '';
        if (!empty($losse_taken_per_melding[$m['id']])) {
            $regels = [];
            foreach ($losse_taken_per_melding[$m['id']] as $t) {
                $vinkje = $t['afgevinkt'] ? '[x]' : '[ ]';
                $regel = $vinkje . ' ' . $t['omschrijving'];
                if ($t['afgevinkt']) {
                    $regel .= ' (afgevinkt door ' . ($t['afgevinkt_door_naam'] ?: 'onbekend')
                        . ' op ' . (new DateTime($t['afgevinkt_op']))->format('d-m-Y H:i') . ')';
                }
                $regels[] = $regel;
            }
            $losse_taken_tekst = implode("\n", $regels);
        }

        $tijdvakken = $status_tijdvakken_per_melding[$m['id']] ?? [];
        $doorlooptijd_tekst = '';
        $totale_seconden = 0;
        if ($tijdvakken) {
            $regels = [];
            foreach ($tijdvakken as $v) {
                $regels[] = status_label($v['status']) . ': ' . $v['van']->format('d-m-Y H:i')
                    . ' - ' . ($v['lopend'] ? 'nu' : $v['tot']->format('d-m-Y H:i'))
                    . ' (' . format_duur($v['duur_seconden']) . ($v['lopend'] ? ', loopt nog' : '') . ')';
                $totale_seconden += $v['duur_seconden'];
            }
            $doorlooptijd_tekst = implode("\n", $regels);
        }

        fputcsv($out, [
            $m['meld_id'],
            $m['titel'],
            $m['hoofd_naam'] ?: '',
            $m['sub_naam'] ?: '',
            prioriteit_label($m['prioriteit']),
            status_label($m['status']),
            $m['locatie'] ?: '',
            $m['gemeld_door'] ?: '',
            $m['aangemaakt_door_naam'] ?: '',
            $m['bijgewerkt_door_naam'] ?: '',
            (new DateTime($m['aangemaakt_op']))->format('d-m-Y H:i'),
            (new DateTime($m['bijgewerkt_op']))->format('d-m-Y H:i'),
            !empty($labels_per_melding[$m['id']]) ? implode(', ', $labels_per_melding[$m['id']]) : '',
            $m['omschrijving'] ?: '',
            $logboek_tekst,
            $protocollen_tekst,
            $subtaken_tekst,
            $losse_taken_tekst,
            $doorlooptijd_tekst,
            $totale_seconden > 0 ? format_duur($totale_seconden) : '',
        ], ';');
    }
    fclose($out);
    exit;
}

// ============================================================
// PDF
// ============================================================
require_once __DIR__ . '/includes/minipdf.php';

$pdf = new MiniPdf();
$marge = $pdf->marge();
$volleBreedte = $pdf->paginaBreedte();

$pdf->setFontSize(16);
$pdf->tekstOp($marge, 'Archiefexport - ' . event_naam($pdo), true);
$pdf->nieuweRegel();
$pdf->setFontSize(9);
$pdf->tekstOp($marge, 'Gegenereerd op ' . (new DateTime())->format('d-m-Y H:i') . ' door ' . huidige_gebruiker_naam() . ' - ' . count($meldingen) . ' melding(en)');
$pdf->nieuweRegel();
$pdf->nieuweRegel();

if (!$meldingen) {
    $pdf->tekstOp($marge, 'Geen meldingen gevonden voor deze selectie/filters.');
    $pdf->nieuweRegel();
}

foreach ($meldingen as $m) {
    $classificatie = trim(($m['hoofd_naam'] ?: '') . ($m['sub_naam'] ? ' - ' . $m['sub_naam'] : ''));
    $labels_tekst = !empty($labels_per_melding[$m['id']]) ? implode(', ', $labels_per_melding[$m['id']]) : '';

    $pdf->ruimteNodig(50);

    $pdf->setFontSize(12);
    $pdf->tekstOp($marge, $m['meld_id'] . ' - ' . $m['titel'], true);
    $pdf->nieuweRegel();

    $pdf->setFontSize(9);
    $pdf->tekstOp($marge, ($classificatie ?: 'Geen classificatie') . '  |  ' . prioriteit_label($m['prioriteit']) . '  |  ' . status_label($m['status']));
    $pdf->nieuweRegel();
    $pdf->tekstOp($marge, 'Locatie: ' . ($m['locatie'] ?: '-') . '  |  Gemeld door: ' . ($m['gemeld_door'] ?: '-'));
    $pdf->nieuweRegel();
    $pdf->tekstOp($marge, 'Ingevoerd door: ' . ($m['aangemaakt_door_naam'] ?: '-') . '  |  Laatst bijgewerkt door: ' . ($m['bijgewerkt_door_naam'] ?: '-'));
    $pdf->nieuweRegel();
    $pdf->tekstOp($marge, 'Aangemaakt: ' . (new DateTime($m['aangemaakt_op']))->format('d-m-Y H:i') . '  |  Afgerond: ' . (new DateTime($m['bijgewerkt_op']))->format('d-m-Y H:i'));
    $pdf->nieuweRegel();
    if ($labels_tekst) {
        $pdf->tekstOp($marge, 'Labels: ' . $labels_tekst);
        $pdf->nieuweRegel();
    }

    $tijdvakken = $status_tijdvakken_per_melding[$m['id']] ?? [];
    if ($tijdvakken) {
        $pdf->nieuweRegel();
        $totale_seconden = array_sum(array_column($tijdvakken, 'duur_seconden'));
        $pdf->tekstOp($marge, 'Statusverloop (totale doorlooptijd: ' . format_duur($totale_seconden) . '):', true);
        $pdf->nieuweRegel();
        foreach ($tijdvakken as $v) {
            $pdf->ruimteNodig(14);
            $regel = status_label($v['status']) . ': ' . $v['van']->format('d-m-Y H:i')
                . ' tot ' . ($v['lopend'] ? 'nu' : $v['tot']->format('d-m-Y H:i'))
                . '  (' . format_duur($v['duur_seconden']) . ($v['lopend'] ? ', loopt nog' : '') . ')'
                . ($v['gebruiker'] ? '  - ' . $v['gebruiker'] : '');
            $pdf->tekstOp($marge, $regel);
            $pdf->nieuweRegel();
        }
    }

    if ($m['omschrijving']) {
        $pdf->nieuweRegel();
        $pdf->tekstOp($marge, 'Omschrijving:', true);
        $pdf->nieuweRegel();
        $pdf->paragraaf($marge, $m['omschrijving'], $volleBreedte);
    }

    if (!empty($notities_per_melding[$m['id']])) {
        $pdf->nieuweRegel();
        $pdf->tekstOp($marge, 'Logboek:', true);
        $pdf->nieuweRegel();
        foreach ($notities_per_melding[$m['id']] as $n) {
            $pdf->ruimteNodig(20);
            $pdf->tekstOp($marge, (new DateTime($n['aangemaakt_op']))->format('d-m-Y H:i') . ' - ' . ($n['auteur'] ?: 'onbekend'), true);
            $pdf->nieuweRegel();
            $pdf->paragraaf($marge + 12, $n['notitie'], $volleBreedte - 12);
        }
    }

    if (!empty($protocollen_per_melding[$m['id']])) {
        $pdf->nieuweRegel();
        $pdf->tekstOp($marge, 'Gekoppelde protocollen:', true);
        $pdf->nieuweRegel();
        foreach ($protocollen_per_melding[$m['id']] as $p) {
            $pdf->ruimteNodig(20);
            $pdf->tekstOp($marge, $p['titel'], true);
            $pdf->nieuweRegel();
            $pdf->paragraaf($marge + 12, $p['inhoud'], $volleBreedte - 12);

            $subtaken_van_protocol = $subtaken_per_melding[$m['id']][$p['protocol_id']] ?? [];
            if ($subtaken_van_protocol) {
                foreach ($subtaken_van_protocol as $t) {
                    $pdf->ruimteNodig(14);
                    $vinkje = $t['afgevinkt'] ? '[x]' : '[ ]';
                    $regel = $vinkje . ' ' . $t['omschrijving'];
                    if ($t['afgevinkt']) {
                        $regel .= ' - afgevinkt door ' . ($t['afgevinkt_door_naam'] ?: 'onbekend')
                            . ' op ' . (new DateTime($t['afgevinkt_op']))->format('d-m-Y H:i');
                    }
                    $pdf->paragraaf($marge + 12, $regel, $volleBreedte - 12);
                }
            }
        }
    }

    if (!empty($losse_taken_per_melding[$m['id']])) {
        $pdf->nieuweRegel();
        $pdf->tekstOp($marge, 'Losse taken:', true);
        $pdf->nieuweRegel();
        foreach ($losse_taken_per_melding[$m['id']] as $t) {
            $pdf->ruimteNodig(14);
            $vinkje = $t['afgevinkt'] ? '[x]' : '[ ]';
            $regel = $vinkje . ' ' . $t['omschrijving'];
            if ($t['afgevinkt']) {
                $regel .= ' - afgevinkt door ' . ($t['afgevinkt_door_naam'] ?: 'onbekend')
                    . ' op ' . (new DateTime($t['afgevinkt_op']))->format('d-m-Y H:i');
            }
            $pdf->paragraaf($marge, $regel, $volleBreedte);
        }
    }

    $pdf->nieuweRegel();
    $pdf->lijn();
    $pdf->nieuweRegel();
    $pdf->nieuweRegel();
}

$pdf->versturen('archief_' . $datumstempel . '.pdf');
