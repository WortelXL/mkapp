<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$aantal_hoofdclassificaties = (int) $pdo->query('SELECT COUNT(*) FROM hoofdclassificaties')->fetchColumn();
$aantal_protocollen = (int) $pdo->query('SELECT COUNT(*) FROM protocollen')->fetchColumn();
$aantal_meldingen    = (int) $pdo->query('SELECT COUNT(*) FROM meldingen')->fetchColumn();
$aantal_gebruikers   = (int) $pdo->query('SELECT COUNT(*) FROM gebruikers')->fetchColumn();

$actief = 'admin';
$paginatitel = 'Beheer';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Beheer</p>
        <h1>Welkom, <?= e(huidige_gebruiker_naam()) ?></h1>
        <p>Beheer gebruikers, classificaties en protocollen voor de meldkamer.</p>
    </div>
</div>

<div class="board">
    <div class="board-cell">
        <div class="num c-muted"><?= $aantal_meldingen ?></div>
        <div class="lbl">Totaal meldingen</div>
    </div>
    <div class="board-cell">
        <div class="num c-muted"><?= $aantal_gebruikers ?></div>
        <div class="lbl">Gebruikers</div>
    </div>
    <div class="board-cell">
        <div class="num c-muted"><?= $aantal_hoofdclassificaties ?></div>
        <div class="lbl">Hoofdclassificaties</div>
    </div>
    <div class="board-cell">
        <div class="num c-muted"><?= $aantal_protocollen ?></div>
        <div class="lbl">Protocollen</div>
    </div>
</div>

<div class="form-grid">
    <div class="panel">
        <h2>Gebruikers</h2>
        <p style="color:var(--muted); margin-top:-8px;">Voeg accounts toe voor je team, met de rol beheerder of medewerker.</p>
        <a href="/admin/gebruikers.php" class="btn btn-primary">Gebruikers beheren</a>
    </div>
    <div class="panel">
        <h2>Classificaties</h2>
        <p style="color:var(--muted); margin-top:-8px;">Beheer hoofd- en subclassificaties waarmee meldingen ingedeeld worden.</p>
        <a href="/admin/classificaties.php" class="btn btn-primary">Classificaties beheren</a>
    </div>
    <div class="panel">
        <h2>Protocollen</h2>
        <p style="color:var(--muted); margin-top:-8px;">Beheer standaardprocedures die aan meldingen gekoppeld kunnen worden.</p>
        <a href="/admin/protocollen.php" class="btn btn-primary">Protocollen beheren</a>
    </div>
    <div class="panel">
        <h2>Instellingen</h2>
        <p style="color:var(--muted); margin-top:-8px;">Naam, startdatum en aantal dagen van het evenement.</p>
        <a href="/admin/instellingen.php" class="btn btn-primary">Instellingen beheren</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
