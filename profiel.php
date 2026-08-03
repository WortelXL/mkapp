<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$fout = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auto_refresh = (int) ($_POST['auto_refresh_seconden'] ?? 20);
    $geluid       = isset($_POST['geluid_nieuwe_melding']) ? 1 : 0;

    $toegestane_intervallen = [0, 10, 15, 20, 30, 60];
    if (!in_array($auto_refresh, $toegestane_intervallen, true)) {
        $fout = 'Ongeldige verversingstijd gekozen.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE gebruikers SET auto_refresh_seconden = :a, geluid_nieuwe_melding = :g WHERE id = :id'
        );
        $stmt->execute([
            'a' => $auto_refresh,
            'g' => $geluid,
            'id' => $_SESSION['gebruiker_id'],
        ]);
        $succes = 'Instellingen opgeslagen.';
    }
}

$instellingen = huidige_gebruiker_instellingen($pdo);

$actief = '';
$paginatitel = 'Mijn instellingen';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Persoonlijk</p>
        <h1>Mijn instellingen</h1>
        <p>Deze instellingen gelden alleen voor jouw account, <?= e(huidige_gebruiker_naam()) ?>.</p>
    </div>
</div>

<?php if ($fout): ?><div class="alert alert-error"><?= e($fout) ?></div><?php endif; ?>
<?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

<div class="panel">
    <h2>Dashboard &amp; melddetail</h2>
    <form method="post" class="form-grid">
        <div class="field">
            <label for="auto_refresh_seconden">Automatisch verversen elke</label>
            <select id="auto_refresh_seconden" name="auto_refresh_seconden">
                <?php foreach ([0 => 'Uit', 10 => '10 seconden', 15 => '15 seconden', 20 => '20 seconden', 30 => '30 seconden', 60 => '60 seconden'] as $waarde => $label): ?>
                    <option value="<?= $waarde ?>" <?= (int) $instellingen['auto_refresh_seconden'] === $waarde ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p style="color:var(--muted); font-size:12px; margin:6px 0 0;">Geldt voor het dashboard en de melddetailpagina. Pauzeert altijd vanzelf zolang je aan het typen bent.</p>
        </div>

        <div class="field full">
            <label style="display:flex; align-items:center; gap:8px; text-transform:none; font-size:14px; color:var(--text); font-weight:500;">
                <input type="checkbox" name="geluid_nieuwe_melding" value="1" <?= $instellingen['geluid_nieuwe_melding'] ? 'checked' : '' ?> style="width:16px; height:16px; accent-color:var(--amber);">
                Speel een geluid af bij een nieuwe melding op het dashboard
            </label>
            <p style="color:var(--muted); font-size:12px; margin:6px 0 0;">
                Werkt alleen als het dashboard open staat in je browser. Sommige browsers blokkeren geluid totdat je ergens op de pagina hebt geklikt — dat is een browserbeveiliging, geen fout.
            </p>
        </div>

        <div class="actions full">
            <button type="submit" class="btn btn-primary">Opslaan</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
