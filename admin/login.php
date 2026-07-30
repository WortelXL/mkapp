<?php
require_once __DIR__ . '/../includes/functions.php';
$pdo = get_pdo();

// Als er nog helemaal geen gebruiker bestaat, stuur door naar de setup-pagina
if (!heeft_gebruiker($pdo)) {
    header('Location: /admin/setup.php');
    exit;
}

if (is_ingelogd()) {
    header('Location: /index.php');
    exit;
}

$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gebruikersnaam = trim($_POST['gebruikersnaam'] ?? '');
    $wachtwoord     = $_POST['wachtwoord'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM gebruikers WHERE gebruikersnaam = :u');
    $stmt->execute(['u' => $gebruikersnaam]);
    $gebruiker = $stmt->fetch();

    if ($gebruiker && !$gebruiker['actief']) {
        $fout = 'Dit account is gedeactiveerd. Neem contact op met een beheerder.';
    } elseif ($gebruiker && password_verify($wachtwoord, $gebruiker['wachtwoord_hash'])) {
        session_regenerate_id(true);
        $_SESSION['gebruiker_id']   = $gebruiker['id'];
        $_SESSION['gebruiker_naam'] = $gebruiker['naam'];
        $_SESSION['gebruiker_rol']  = $gebruiker['rol'];
        header('Location: /index.php');
        exit;
    } else {
        $fout = 'Onjuiste gebruikersnaam of wachtwoord.';
    }
}

$actief = 'login';
$paginatitel = 'Inloggen';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Meldkamer</p>
        <h1>Inloggen</h1>
        <p>Log in met je persoonlijke account om meldingen te bekijken en aan te maken.</p>
    </div>
</div>

<?php if (isset($_GET['aangemaakt'])): ?>
    <div class="alert alert-success">Account is aangemaakt. Je kunt nu inloggen.</div>
<?php endif; ?>
<?php if ($fout): ?>
    <div class="alert alert-error"><?= e($fout) ?></div>
<?php endif; ?>

<div class="panel" style="max-width:380px;">
    <form method="post">
        <div class="field">
            <label for="gebruikersnaam">Gebruikersnaam</label>
            <input type="text" id="gebruikersnaam" name="gebruikersnaam" required autofocus>
        </div>
        <div class="field">
            <label for="wachtwoord">Wachtwoord</label>
            <input type="password" id="wachtwoord" name="wachtwoord" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Inloggen</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
