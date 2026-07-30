<?php
require_once __DIR__ . '/../includes/functions.php';
$pdo = get_pdo();

// Deze pagina werkt alleen zolang er nog helemaal geen gebruiker bestaat.
if (heeft_gebruiker($pdo)) {
    header('Location: /admin/login.php');
    exit;
}

$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam           = trim($_POST['naam'] ?? '');
    $gebruikersnaam = trim($_POST['gebruikersnaam'] ?? '');
    $wachtwoord     = $_POST['wachtwoord'] ?? '';
    $wachtwoord2    = $_POST['wachtwoord2'] ?? '';

    if ($naam === '' || $gebruikersnaam === '' || $wachtwoord === '') {
        $fout = 'Vul je naam, een gebruikersnaam en een wachtwoord in.';
    } elseif (strlen($wachtwoord) < 8) {
        $fout = 'Gebruik een wachtwoord van minimaal 8 tekens.';
    } elseif ($wachtwoord !== $wachtwoord2) {
        $fout = 'De wachtwoorden komen niet overeen.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO gebruikers (gebruikersnaam, wachtwoord_hash, naam, rol) VALUES (:u, :p, :n, :r)'
        );
        $stmt->execute([
            'u' => $gebruikersnaam,
            'p' => password_hash($wachtwoord, PASSWORD_DEFAULT),
            'n' => $naam,
            'r' => 'beheerder',
        ]);
        header('Location: /admin/login.php?aangemaakt=1');
        exit;
    }
}

$actief = 'login';
$paginatitel = 'Eerste beheerder instellen';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Eenmalige instelling</p>
        <h1>Eerste beheerdersaccount aanmaken</h1>
        <p>Er bestaat nog geen enkele gebruiker. Maak het eerste account aan — dit wordt automatisch een beheerder die daarna meer gebruikers kan toevoegen.</p>
    </div>
</div>

<?php if ($fout): ?>
    <div class="alert alert-error"><?= e($fout) ?></div>
<?php endif; ?>

<div class="panel" style="max-width:420px;">
    <form method="post">
        <div class="field">
            <label for="naam">Volledige naam</label>
            <input type="text" id="naam" name="naam" required autofocus placeholder="bv. Sanne de Vries">
        </div>
        <div class="field">
            <label for="gebruikersnaam">Gebruikersnaam</label>
            <input type="text" id="gebruikersnaam" name="gebruikersnaam" required>
        </div>
        <div class="field">
            <label for="wachtwoord">Wachtwoord</label>
            <input type="password" id="wachtwoord" name="wachtwoord" required minlength="8">
        </div>
        <div class="field">
            <label for="wachtwoord2">Herhaal wachtwoord</label>
            <input type="password" id="wachtwoord2" name="wachtwoord2" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Account aanmaken</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
