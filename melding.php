<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$id = (int) ($_GET['id'] ?? 0);
$melding_stmt = $pdo->prepare(
    'SELECT m.*, c.naam AS categorie_naam, c.kleur AS categorie_kleur,
            g1.naam AS aangemaakt_door_naam, g2.naam AS bijgewerkt_door_naam
     FROM meldingen m
     LEFT JOIN categorieen c ON c.id = m.categorie_id
     LEFT JOIN gebruikers g1 ON g1.id = m.aangemaakt_door_id
     LEFT JOIN gebruikers g2 ON g2.id = m.bijgewerkt_door_id
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

    if ($actie === 'status_bijwerken') {
        $status      = $_POST['status'] ?? $melding['status'];
        $prioriteit  = $_POST['prioriteit'] ?? $melding['prioriteit'];
        $toegewezen  = trim($_POST['toegewezen_aan'] ?? '');
        if (in_array($status, ['open','in_behandeling','afgehandeld','geannuleerd'], true)
            && in_array($prioriteit, ['laag','normaal','hoog','kritiek'], true)) {
            $stmt = $pdo->prepare(
                'UPDATE meldingen SET status = :status, prioriteit = :prioriteit, toegewezen_aan = :toegewezen, bijgewerkt_door_id = :gebruiker WHERE id = :id'
            );
            $stmt->execute([
                'status'      => $status,
                'prioriteit'  => $prioriteit,
                'toegewezen'  => $toegewezen ?: null,
                'gebruiker'   => $_SESSION['gebruiker_id'],
                'id'          => $id,
            ]);
        }
        header('Location: /melding.php?id=' . $id . '&bijgewerkt=1');
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
        }
        header('Location: /melding.php?id=' . $id . '#log');
        exit;
    }

    // herlaad melding na eventuele wijziging
    $melding_stmt->execute(['id' => $id]);
    $melding = $melding_stmt->fetch();
}

// ---- Gekoppelde protocollen --------------------------------------------
$gekoppeld_stmt = $pdo->prepare(
    'SELECT p.* FROM protocollen p
     JOIN melding_protocollen mp ON mp.protocol_id = p.id
     WHERE mp.melding_id = :id ORDER BY p.titel'
);
$gekoppeld_stmt->execute(['id' => $id]);
$gekoppelde_protocollen = $gekoppeld_stmt->fetchAll();
$gekoppelde_ids = array_column($gekoppelde_protocollen, 'id');

// beschikbare protocollen om te koppelen (nog niet gekoppeld)
$alle_protocollen = get_protocollen($pdo);
$beschikbare_protocollen = array_filter($alle_protocollen, fn($p) => !in_array($p['id'], $gekoppelde_ids, true));

// ---- Notities / log -----------------------------------------------------
$notities_stmt = $pdo->prepare('SELECT * FROM melding_notities WHERE melding_id = :id ORDER BY aangemaakt_op DESC');
$notities_stmt->execute(['id' => $id]);
$notities = $notities_stmt->fetchAll();

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
        <p class="eyebrow"><?= e($melding['meld_id']) ?></p>
        <h1><?= e($melding['titel']) ?></h1>
    </div>
    <div style="display:flex; gap:8px;">
        <span class="tag <?= prioriteit_class($melding['prioriteit']) ?>"><?= prioriteit_label($melding['prioriteit']) ?></span>
        <span class="tag <?= status_class($melding['status']) ?>"><span class="tag-dot"></span><?= status_label($melding['status']) ?></span>
    </div>
</div>

<div class="kv-grid">
    <div class="kv"><div class="k">Locatie</div><div class="v"><?= e($melding['locatie'] ?: '—') ?></div></div>
    <div class="kv"><div class="k">Categorie</div><div class="v"><?= e($melding['categorie_naam'] ?: '—') ?></div></div>
    <div class="kv"><div class="k">Gemeld door</div><div class="v"><?= e($melding['gemeld_door'] ?: '—') ?></div></div>
    <div class="kv"><div class="k">Aangemaakt</div><div class="v"><?= (new DateTime($melding['aangemaakt_op']))->format('d-m-Y H:i') ?></div></div>
    <div class="kv"><div class="k">Ingevoerd door</div><div class="v"><?= e($melding['aangemaakt_door_naam'] ?: 'onbekend') ?></div></div>
    <div class="kv"><div class="k">Laatst bijgewerkt door</div><div class="v"><?= e($melding['bijgewerkt_door_naam'] ?: '—') ?></div></div>
</div>

<?php if ($melding['omschrijving']): ?>
    <div class="panel">
        <h2>Omschrijving</h2>
        <p style="white-space:pre-line; color: var(--text); margin:0;"><?= e($melding['omschrijving']) ?></p>
    </div>
<?php endif; ?>

<div class="panel">
    <h2>Status bijwerken</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="status_bijwerken">
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['open','in_behandeling','afgehandeld','geannuleerd'] as $s): ?>
                    <option value="<?= $s ?>" <?= $melding['status'] === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
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
        <div class="field full">
            <label for="toegewezen_aan">Toegewezen aan</label>
            <input type="text" id="toegewezen_aan" name="toegewezen_aan" value="<?= e($melding['toegewezen_aan'] ?? '') ?>" placeholder="Naam / team dat de melding oppakt">
        </div>
        <div class="actions full">
            <button type="submit" class="btn btn-primary">Bijwerken</button>
        </div>
    </form>
</div>

<div class="panel" id="protocollen">
    <h2>Gekoppelde protocollen</h2>

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
            <p><?= e($p['inhoud']) ?></p>
        </div>
    <?php endforeach; ?>

    <?php if ($beschikbare_protocollen): ?>
        <form method="post" style="display:flex; gap:10px; margin-top:14px;">
            <input type="hidden" name="actie" value="protocol_koppelen">
            <select name="protocol_id" style="flex:1;">
                <?php foreach ($beschikbare_protocollen as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['titel']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Koppelen</button>
        </form>
    <?php elseif (!$alle_protocollen): ?>
        <p style="color: var(--muted);">
            Er zijn nog geen protocollen aangemaakt. Ga naar
            <a href="/admin/protocollen.php" style="color: var(--amber);">Beheer &rarr; Protocollen</a> om er een toe te voegen.
        </p>
    <?php endif; ?>
</div>

<div class="panel" id="log">
    <h2>Logboek / notities</h2>

    <form method="post" style="margin-bottom:20px;">
        <input type="hidden" name="actie" value="notitie_toevoegen">
        <div class="field">
            <label for="notitie">Nieuwe notitie (als <?= e(huidige_gebruiker_naam()) ?>)</label>
            <textarea id="notitie" name="notitie" placeholder="Update, actie ondernomen, overdracht..." required></textarea>
        </div>
        <div class="actions">
            <button type="submit" class="btn">Notitie toevoegen</button>
        </div>
    </form>

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

<?php include __DIR__ . '/includes/footer.php'; ?>
