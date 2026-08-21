<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$ALLE_EVENTS = beschikbare_webhook_events();

$fout = '';
$succes = '';
$bewerk = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'opslaan') {
        $id     = (int) ($_POST['id'] ?? 0);
        $naam   = trim($_POST['naam'] ?? '');
        $url    = trim($_POST['url'] ?? '');
        $events = array_values(array_intersect($_POST['events'] ?? [], array_keys($ALLE_EVENTS)));
        $actief = isset($_POST['actief']) ? 1 : 0;

        if ($naam === '' || $url === '') {
            $fout = 'Vul een naam en een URL in.';
        } elseif (!preg_match('#^https?://#i', $url)) {
            $fout = 'De URL moet beginnen met http:// of https://';
        } elseif (!$events) {
            $fout = 'Kies minstens 1 gebeurtenis waarop deze webhook moet reageren.';
        } else {
            $events_json = json_encode($events);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE webhooks SET naam = :n, url = :u, events = :e, actief = :a WHERE id = :id');
                $stmt->execute(['n' => $naam, 'u' => $url, 'e' => $events_json, 'a' => $actief, 'id' => $id]);
                $succes = 'Webhook bijgewerkt.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO webhooks (naam, url, events, actief) VALUES (:n, :u, :e, :a)');
                $stmt->execute(['n' => $naam, 'u' => $url, 'e' => $events_json, 'a' => $actief]);
                $succes = 'Webhook "' . $naam . '" is aangemaakt.';
            }
        }
    }

    if ($actie === 'verwijderen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM webhooks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $succes = 'Webhook verwijderd.';
    }

    if ($actie === 'test_versturen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM webhooks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $webhook = $stmt->fetch();
        if ($webhook) {
            $ok = verstuur_1_webhook($pdo, $webhook, 'test', [
                'bericht' => 'Dit is een testbericht vanuit het meldkamersysteem.',
                'evenement' => event_naam($pdo),
            ]);
            if ($ok) {
                $succes = 'Testbericht succesvol verstuurd naar "' . $webhook['naam'] . '".';
            } else {
                $fout = 'Testbericht naar "' . $webhook['naam'] . '" is mislukt — zie de status hieronder voor details.';
            }
        }
    }
}

if (isset($_GET['bewerk'])) {
    $stmt = $pdo->prepare('SELECT * FROM webhooks WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['bewerk']]);
    $bewerk = $stmt->fetch() ?: null;
}

$webhooks = $pdo->query('SELECT * FROM webhooks ORDER BY naam ASC')->fetchAll();
$bewerk_events = $bewerk ? (json_decode($bewerk['events'], true) ?: []) : [];

$actief = 'admin';
$paginatitel = 'Connectiviteit';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/admin/index.php" style="color:var(--muted); text-decoration:none;">&larr; Beheer</a></p>
        <h1>Connectiviteit</h1>
        <p>Uitgaande webhooks: stuurt automatisch een bericht (HTTP POST met JSON) naar een externe applicatie zodra er iets gebeurt, bv. een nieuwe melding, een statuswijziging of een attentiesignaal.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2><?= $bewerk ? 'Webhook bewerken' : 'Nieuwe webhook' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="opslaan">
        <input type="hidden" name="id" value="<?= $bewerk['id'] ?? 0 ?>">
        <div class="field">
            <label for="naam">Naam</label>
            <input type="text" id="naam" name="naam" required value="<?= e($bewerk['naam'] ?? '') ?>" placeholder="bv. Slack-kanaal meldkamer">
        </div>
        <div class="field">
            <label for="url">URL</label>
            <input type="text" id="url" name="url" required value="<?= e($bewerk['url'] ?? '') ?>" placeholder="https://...">
        </div>
        <div class="field full">
            <label>Gebeurtenissen</label>
            <div style="display:flex; flex-wrap:wrap; gap:16px; margin-top:4px;">
                <?php foreach ($ALLE_EVENTS as $sleutel => $label): ?>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:400;">
                        <input type="checkbox" name="events[]" value="<?= e($sleutel) ?>" <?= in_array($sleutel, $bewerk_events, true) ? 'checked' : '' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="field full">
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" name="actief" <?= (!$bewerk || $bewerk['actief']) ? 'checked' : '' ?>>
                Actief
            </label>
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary"><?= $bewerk ? 'Wijzigingen opslaan' : 'Webhook aanmaken' ?></button>
            <?php if ($bewerk): ?>
                <a href="/admin/connectiviteit.php" class="btn">Annuleren</a>
            <?php endif; ?>
        </div>
    </form>
    <p style="color:var(--muted); font-size:12px; margin:14px 0 0;">
        De verzending gebeurt op het moment zelf (synchroon), met een timeout van 5 seconden — een trage of onbereikbare externe URL kan de betreffende actie (bv. het aanmaken van een melding) dus eventjes vertragen. Een mislukte verzending breekt nooit de actie zelf; je ziet het alleen terug in de status hieronder.
    </p>
</div>

<div class="panel">
    <h2>Bestaande webhooks</h2>
    <?php if (!$webhooks): ?>
        <p style="color:var(--muted);">Nog geen webhooks aangemaakt.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>Naam</th><th>URL</th><th>Gebeurtenissen</th><th>Status</th><th>Laatst verzonden</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($webhooks as $w): ?>
            <?php $w_events = json_decode($w['events'], true) ?: []; ?>
            <tr>
                <td>
                    <?= e($w['naam']) ?>
                    <?php if (!$w['actief']): ?><br><span style="color:var(--muted); font-size:11px;">(uitgeschakeld)</span><?php endif; ?>
                </td>
                <td style="font-family:var(--font-mono); font-size:11.5px; color:var(--muted); max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($w['url']) ?></td>
                <td style="font-size:12px; color:var(--muted);">
                    <?= e(implode(', ', array_map(fn($s) => $ALLE_EVENTS[$s] ?? $s, $w_events))) ?>
                </td>
                <td>
                    <?php if ($w['laatste_status'] === 'ok'): ?>
                        <span class="tag status-afgehandeld">OK</span>
                    <?php elseif ($w['laatste_status'] === 'fout'): ?>
                        <span class="tag status-open" title="<?= e($w['laatste_foutmelding'] ?? '') ?>">Fout</span>
                    <?php else: ?>
                        <span style="color:var(--muted); font-size:12px;">Nog niet verzonden</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--muted); font-size:12px;">
                    <?= $w['laatst_verzonden_op'] ? (new DateTime($w['laatst_verzonden_op']))->format('d-m-Y H:i') : '—' ?>
                </td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="actie" value="test_versturen">
                        <input type="hidden" name="id" value="<?= $w['id'] ?>">
                        <button type="submit" class="btn btn-small">Test versturen</button>
                    </form>
                    <a href="/admin/connectiviteit.php?bewerk=<?= $w['id'] ?>" class="btn btn-small">Bewerken</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Webhook \'<?= e($w['naam']) ?>\' verwijderen?');">
                        <input type="hidden" name="actie" value="verwijderen">
                        <input type="hidden" name="id" value="<?= $w['id'] ?>">
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
