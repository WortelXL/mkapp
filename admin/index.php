<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$aantal_categorieen = (int) $pdo->query('SELECT COUNT(*) FROM categorieen')->fetchColumn();
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
        <p>Beheer gebruikers, categorieen en protocollen voor de meldkamer.</p>
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
        <div class="num c-muted"><?= $aantal_categorieen ?></div>
        <div class="lbl">Categorieen</div>
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
        <h2>Categorieen</h2>
        <p style="color:var(--muted); margin-top:-8px;">Maak meldingscategorieen aan (bv. Medisch, Beveiliging) of verwijder ze.</p>
        <a href="/admin/categorieen.php" class="btn btn-primary">Categorieen beheren</a>
    </div>
    <div class="panel">
        <h2>Protocollen</h2>
        <p style="color:var(--muted); margin-top:-8px;">Beheer standaardprocedures die aan meldingen gekoppeld kunnen worden.</p>
        <a href="/admin/protocollen.php" class="btn btn-primary">Protocollen beheren</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
