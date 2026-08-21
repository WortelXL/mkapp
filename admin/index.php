<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_beheerder();
$pdo = get_pdo();

$aantal_hoofdclassificaties = (int) $pdo->query('SELECT COUNT(*) FROM hoofdclassificaties')->fetchColumn();
$aantal_protocollen = (int) $pdo->query('SELECT COUNT(*) FROM protocollen')->fetchColumn();
$aantal_meldingen    = (int) $pdo->query('SELECT COUNT(*) FROM meldingen')->fetchColumn();
$aantal_gebruikers   = (int) $pdo->query('SELECT COUNT(*) FROM gebruikers')->fetchColumn();
$aantal_crew         = (int) $pdo->query('SELECT COUNT(*) FROM crew')->fetchColumn();
$aantal_statussen    = (int) $pdo->query('SELECT COUNT(*) FROM statussen')->fetchColumn();

$actief = 'admin';
$paginatitel = 'Beheer';
include __DIR__ . '/../includes/header.php';

$onderdelen = [
    [
        'titel' => 'Gebruikers',
        'omschrijving' => 'Accounts voor je team (beheerder/medewerker/viewer), functie, wachtwoorden en API-tokens.',
        'link' => '/admin/gebruikers.php',
        'aantal' => $aantal_gebruikers,
    ],
    [
        'titel' => 'Crew',
        'omschrijving' => 'Contactpersonen zonder login (naam, functie, telefoonnummer) — koppelbaar aan "Toegewezen aan" op een melding.',
        'link' => '/admin/crew.php',
        'aantal' => $aantal_crew,
    ],
    [
        'titel' => 'Classificaties',
        'omschrijving' => 'Hoofd- en subclassificaties waarmee meldingen ingedeeld worden.',
        'link' => '/admin/classificaties.php',
        'aantal' => $aantal_hoofdclassificaties,
    ],
    [
        'titel' => 'Statussen',
        'omschrijving' => 'Ingebouwde statussen aanpassen (naam/kleur/categorie), of eigen statussen toevoegen/verwijderen.',
        'link' => '/admin/statussen.php',
        'aantal' => $aantal_statussen,
    ],
    [
        'titel' => 'Protocollen',
        'omschrijving' => 'Standaardprocedures (met subtaken en naslaglinks) die aan meldingen gekoppeld kunnen worden.',
        'link' => '/admin/protocollen.php',
        'aantal' => $aantal_protocollen,
    ],
    [
        'titel' => 'Locaties',
        'omschrijving' => 'Vooraf ingestelde locaties, oproepbaar met ;naam in omschrijving/notities.',
        'link' => '/admin/locaties.php',
        'aantal' => null,
    ],
    [
        'titel' => 'Labels',
        'omschrijving' => 'Labels om meldingen te markeren voor latere opvolging, ook in het archief.',
        'link' => '/admin/labels.php',
        'aantal' => null,
    ],
    [
        'titel' => 'Instellingen',
        'omschrijving' => 'Naam, startdatum en aantal dagen van het evenement, en het archief leegmaken.',
        'link' => '/admin/instellingen.php',
        'aantal' => null,
    ],
    [
        'titel' => 'Backup & Restore',
        'omschrijving' => 'Basisinstellingen (crew, classificaties, statussen, protocollen, locaties, labels) exporteren of importeren.',
        'link' => '/admin/backup.php',
        'aantal' => null,
    ],
];
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Beheer</p>
        <h1>Welkom, <?= e(huidige_gebruiker_naam()) ?></h1>
        <p>Beheer gebruikers, crew, classificaties en protocollen voor de meldkamer.</p>
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
        <div class="num c-muted"><?= $aantal_crew ?></div>
        <div class="lbl">Crew</div>
    </div>
    <div class="board-cell">
        <div class="num c-muted"><?= $aantal_protocollen ?></div>
        <div class="lbl">Protocollen</div>
    </div>
</div>

<div class="panel" style="padding:8px 20px;">
    <?php foreach ($onderdelen as $o): ?>
        <a href="<?= e($o['link']) ?>" class="beheer-lijst-item">
            <div>
                <div class="beheer-lijst-titel"><?= e($o['titel']) ?><?= $o['aantal'] !== null ? ' <span class="beheer-lijst-aantal">(' . $o['aantal'] . ')</span>' : '' ?></div>
                <div class="beheer-lijst-omschrijving"><?= e($o['omschrijving']) ?></div>
            </div>
            <span class="beheer-lijst-pijl">&rarr;</span>
        </a>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
